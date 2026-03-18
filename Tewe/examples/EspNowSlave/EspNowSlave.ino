// ============================================================
// 📦 Tewe — ESP-NOW Slave (Remote Switch)
// ============================================================
//
// SLAVE: ESP8266 TANPA WiFi/MQTT.
//        Kirim perintah toggle ke MASTER via ESP-NOW.
//        Terima feedback status dari MASTER.
//
// Fitur:
//   - 4 tombol fisik (push button) → kirim toggle ke master
//   - 4 LED indikator → menampilkan status toggle dari master
//   - Tidak perlu WiFi! Hemat daya.
//
// Arsitektur:
//   ┌─────────────┐  ESP-NOW  ┌──────────┐
//   │  SLAVE       │─────────▶│  MASTER  │
//   │  (Tombol +   │◀─────────│ (Gateway)│
//   │   LED)       │ feedback │          │
//   └─────────────┘          └──────────┘
//
// Wiring (sesuaikan dengan hardware kamu):
//   Button 1: GPIO0  (D3/FLASH) → GND  (internal pull-up)
//   Button 2: GPIO2  (D4)       → GND  (internal pull-up)
//   Button 3: GPIO14 (D5)       → GND  (internal pull-up)
//   Button 4: GPIO12 (D6)       → GND  (internal pull-up)
//
//   LED 1:    GPIO4  (D2) → resistor → GND
//   LED 2:    GPIO5  (D1) → resistor → GND
//   LED 3:    GPIO13 (D7) → resistor → GND
//   LED 4:    GPIO15 (D8) → resistor → GND
//
// ============================================================

#include <ESP8266WiFi.h>
#include <espnow.h>

// ── KONFIGURASI ────────────────────────────────────────────

// ID Slave ini (0, 1, 2, ...) — harus unik per slave!
#define SLAVE_ID 0

// MAC Address MASTER — lihat di Serial Monitor master
uint8_t masterMac[] = {0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF};
// ↑ GANTI dengan MAC master yang sebenarnya!

// WiFi channel (harus sama dengan WiFi channel master)
// Cek di master Serial Monitor, atau coba 1-13
#define WIFI_CHANNEL 1

// ── Button Config ──────────────────────────────────────────
struct ButtonConfig {
  int pin;        // GPIO pin tombol
  int toggleIdx;  // index toggle di master (0-based)
  bool lastState; // state terakhir (untuk debounce)
  unsigned long lastPress;
};

ButtonConfig buttons[] = {
    {0, 0, HIGH, 0},  // Button 1 → toggle1 (GPIO0/D3)
    {2, 1, HIGH, 0},  // Button 2 → toggle2 (GPIO2/D4)
    {14, 2, HIGH, 0}, // Button 3 → toggle3 (GPIO14/D5)
    {12, 3, HIGH, 0}, // Button 4 → toggle4 (GPIO12/D6)
};
const int BUTTON_COUNT = sizeof(buttons) / sizeof(buttons[0]);

#define DEBOUNCE_MS 300

// ── LED Indikator ──────────────────────────────────────────
struct LedConfig {
  int pin;       // GPIO pin LED
  int toggleIdx; // toggle index yang ditampilkan
};

LedConfig leds[] = {
    {4, 0},  // LED 1 → toggle1 status (GPIO4/D2)
    {5, 1},  // LED 2 → toggle2 status (GPIO5/D1)
    {13, 2}, // LED 3 → toggle3 status (GPIO13/D7)
    {15, 3}, // LED 4 → toggle4 status (GPIO15/D8)
};
const int LED_COUNT = sizeof(leds) / sizeof(leds[0]);

// ── ESP-NOW Protocol (SAMA dengan master) ──────────────────

// Slave → Master
struct SlaveToMaster {
  uint8_t slaveId;
  uint8_t toggleIdx;
  bool state;
  uint8_t command; // 0=SET, 1=TOGGLE, 2=STATUS_REQUEST
};

// Master → Slave
struct MasterToSlave {
  uint8_t toggleIdx;
  bool state;
  bool mqttOk;
};

// Status tracking
bool toggleStates[10] = {false};
bool masterOnline = false;
unsigned long lastSendTime = 0;

// ── Kirim perintah ke master ───────────────────────────────
void sendToMaster(uint8_t toggleIdx, uint8_t command, bool state = false) {
  SlaveToMaster msg;
  msg.slaveId = SLAVE_ID;
  msg.toggleIdx = toggleIdx;
  msg.state = state;
  msg.command = command;

  int result = esp_now_send(masterMac, (uint8_t *)&msg, sizeof(msg));

  Serial.printf("TX → Master: toggle%d cmd=%d state=%d %s\n", toggleIdx + 1,
                command, state, result == 0 ? "OK" : "FAIL");
  lastSendTime = millis();
}

// ── Terima feedback dari master ────────────────────────────
void onEspNowReceive(uint8_t *mac, uint8_t *data, uint8_t len) {
  if (len != sizeof(MasterToSlave))
    return;

  MasterToSlave fb;
  memcpy(&fb, data, sizeof(fb));

  masterOnline = true;

  if (fb.toggleIdx < 10) {
    toggleStates[fb.toggleIdx] = fb.state;
  }

  // Update LED indikator
  for (int i = 0; i < LED_COUNT; i++) {
    if (leds[i].toggleIdx == fb.toggleIdx) {
      digitalWrite(leds[i].pin, fb.state ? HIGH : LOW);
    }
  }

  Serial.printf("RX ← Master: toggle%d=%s mqtt=%s\n", fb.toggleIdx + 1,
                fb.state ? "ON" : "OFF", fb.mqttOk ? "OK" : "OFF");
}

// ── Send callback (opsional, untuk cek delivery) ───────────
void onEspNowSend(uint8_t *mac, uint8_t status) {
  if (status != 0) {
    Serial.println("ESP-NOW send GAGAL!");
  }
}

// ── Baca tombol (debounced) ────────────────────────────────
void readButtons() {
  for (int i = 0; i < BUTTON_COUNT; i++) {
    bool reading = digitalRead(buttons[i].pin);

    if (reading == LOW && buttons[i].lastState == HIGH &&
        (millis() - buttons[i].lastPress > DEBOUNCE_MS)) {
      buttons[i].lastPress = millis();

      // Kirim perintah TOGGLE ke master
      sendToMaster(buttons[i].toggleIdx, 1); // command=1 → TOGGLE

      Serial.printf("Button %d pressed → toggle%d\n", i + 1,
                    buttons[i].toggleIdx + 1);
    }

    buttons[i].lastState = reading;
  }
}

// ── LED blink saat belum terhubung ke master ───────────────
void statusBlink() {
  static unsigned long blinkTimer = 0;
  static bool blinkState = false;

  if (!masterOnline && millis() - blinkTimer > 500) {
    blinkTimer = millis();
    blinkState = !blinkState;
    // Blink LED pertama sebagai status indicator
    if (LED_COUNT > 0) {
      digitalWrite(leds[0].pin, blinkState ? HIGH : LOW);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println(F("\n╔═══════════════════════════════════════════╗"));
  Serial.println(F("║     Tewe ESP-NOW Slave                    ║"));
  Serial.printf("║     Slave ID: %d                            ║\n", SLAVE_ID);
  Serial.println(F("╚═══════════════════════════════════════════╝\n"));

  // Setup button pins (internal pull-up)
  for (int i = 0; i < BUTTON_COUNT; i++) {
    pinMode(buttons[i].pin, INPUT_PULLUP);
    Serial.printf("Button %d: GPIO%d → toggle%d\n", i + 1, buttons[i].pin,
                  buttons[i].toggleIdx + 1);
  }

  // Setup LED pins
  for (int i = 0; i < LED_COUNT; i++) {
    pinMode(leds[i].pin, OUTPUT);
    digitalWrite(leds[i].pin, LOW);
    Serial.printf("LED %d:    GPIO%d → toggle%d status\n", i + 1, leds[i].pin,
                  leds[i].toggleIdx + 1);
  }

  // WiFi hanya untuk ESP-NOW (tidak connect ke AP)
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);

  Serial.print("SLAVE MAC: ");
  Serial.println(WiFi.macAddress());

  // Init ESP-NOW
  if (esp_now_init() != 0) {
    Serial.println("ESP-NOW init GAGAL!");
    return;
  }

  esp_now_set_self_role(ESP_NOW_ROLE_COMBO);
  esp_now_register_recv_cb(onEspNowReceive);
  esp_now_register_send_cb(onEspNowSend);

  // Daftarkan master sebagai peer
  esp_now_add_peer(masterMac, ESP_NOW_ROLE_COMBO, WIFI_CHANNEL, NULL, 0);

  Serial.println("ESP-NOW Ready (Slave)");
  Serial.printf("Master MAC: %02X:%02X:%02X:%02X:%02X:%02X\n", masterMac[0],
                masterMac[1], masterMac[2], masterMac[3], masterMac[4],
                masterMac[5]);

  // Minta status terkini dari master
  Serial.println("Requesting status dari master...");
  for (int i = 0; i < 4; i++) {
    sendToMaster(i, 2); // command=2 → STATUS_REQUEST
    delay(50);
  }

  Serial.println("\nSlave siap! Tekan tombol untuk toggle.\n");
}

void loop() {
  readButtons();
  statusBlink();
  yield();
}
