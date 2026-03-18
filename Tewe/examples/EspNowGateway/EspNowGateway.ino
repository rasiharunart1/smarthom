// ============================================================
// 📦 Tewe — ESP-NOW Master (Gateway) + Multi-Slave
// ============================================================
//
// MASTER: ESP8266 terhubung WiFi + MQTT via Tewe.
//         Menerima perintah toggle dari banyak SLAVE via ESP-NOW.
//         Juga bisa kirim balik status ke SLAVE.
//
// Arsitektur:
//   ┌─────────┐  ESP-NOW   ┌─────────────┐  MQTT   ┌───────────┐
//   │ SLAVE 1 │───────────▶│   MASTER     │◀──────▶│ Dashboard │
//   │ SLAVE 2 │───────────▶│  (Gateway)   │        │  (Web UI) │
//   │ SLAVE 3 │───────────▶│  Tewe + WiFi │        └───────────┘
//   └─────────┘◀───────────│              │
//     (tombol)   feedback  └─────────────┘
//
// ============================================================

#include <Tewe.h>
#include <espnow.h>

Tewe tewe;

// ── Daftar MAC SLAVE (isi sesuai slave kamu) ───────────────
// Jalankan slave, lihat MAC di Serial Monitor, lalu isi di sini.
uint8_t slaveMacs[][6] = {
    {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x01}, // Slave 1 (Ruang Tamu)
    {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x02}, // Slave 2 (Kamar)
    {0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0x03}, // Slave 3 (Dapur)
};
const int SLAVE_COUNT = sizeof(slaveMacs) / sizeof(slaveMacs[0]);

// ── ESP-NOW Protocol Structs ───────────────────────────────
// Harus SAMA PERSIS di master dan slave!

// Slave → Master: perintah toggle
struct SlaveToMaster {
  uint8_t slaveId;   // ID slave (0, 1, 2, ...)
  uint8_t toggleIdx; // index toggle (0-based)
  bool state;        // true=ON, false=OFF
  uint8_t command;   // 0=SET, 1=TOGGLE, 2=STATUS_REQUEST
};

// Master → Slave: feedback status
struct MasterToSlave {
  uint8_t toggleIdx; // index toggle yang berubah
  bool state;        // state terbaru
  bool mqttOk;       // status MQTT
};

volatile SlaveToMaster rxCmd;
volatile bool rxNewData = false;

// ── Helper: print MAC ──────────────────────────────────────
void printMac(uint8_t *mac) {
  for (int i = 0; i < 6; i++) {
    Serial.printf("%02X", mac[i]);
    if (i < 5)
      Serial.print(":");
  }
}

// ── ESP-NOW: Terima data dari slave ────────────────────────
void onEspNowReceive(uint8_t *mac, uint8_t *data, uint8_t len) {
  if (len != sizeof(SlaveToMaster))
    return;
  memcpy((void *)&rxCmd, data, sizeof(SlaveToMaster));
  rxNewData = true;
  Serial.print("ESP-NOW RX dari ");
  printMac(mac);
  Serial.printf(" | slave=%d toggle=%d cmd=%d\n", rxCmd.slaveId,
                rxCmd.toggleIdx, rxCmd.command);
}

// ── ESP-NOW: Kirim feedback ke slave tertentu ──────────────
void sendFeedback(int slaveId, int toggleIdx, bool state) {
  if (slaveId < 0 || slaveId >= SLAVE_COUNT)
    return;

  MasterToSlave fb;
  fb.toggleIdx = toggleIdx;
  fb.state = state;
  fb.mqttOk = tewe.isMqttConnected();

  esp_now_send(slaveMacs[slaveId], (uint8_t *)&fb, sizeof(fb));
  Serial.printf("ESP-NOW TX → Slave %d: toggle%d=%s\n", slaveId, toggleIdx + 1,
                state ? "ON" : "OFF");
}

// ── Kirim feedback ke SEMUA slave ──────────────────────────
void broadcastFeedback(int toggleIdx, bool state) {
  for (int s = 0; s < SLAVE_COUNT; s++) {
    sendFeedback(s, toggleIdx, state);
    delay(10);
  }
}

// ── Tewe Callback: toggle berubah dari dashboard ───────────
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s (dari dashboard)\n", key, state ? "ON" : "OFF");

  // Kirim feedback ke semua slave agar LED indikator sync
  // Parse "toggleN" → index N-1
  String k = String(key);
  if (k.startsWith("toggle")) {
    int idx = k.substring(6).toInt() - 1;
    if (idx >= 0)
      broadcastFeedback(idx, state);
  }
}

// ── Process ESP-NOW command ────────────────────────────────
void processEspNow() {
  if (!rxNewData)
    return;
  rxNewData = false;

  int idx = rxCmd.toggleIdx;
  int count = tewe.getToggleCount();
  String key = "toggle" + String(idx + 1);

  if (idx < 0 || idx >= count) {
    Serial.printf("ESP-NOW: toggle index %d invalid (max=%d)\n", idx, count);
    return;
  }

  switch (rxCmd.command) {
  case 0: // SET
    tewe.setState(key.c_str(), rxCmd.state);
    broadcastFeedback(idx, rxCmd.state);
    break;

  case 1: // TOGGLE (flip)
    tewe.toggle(key.c_str());
    broadcastFeedback(idx, tewe.getState(key.c_str()));
    break;

  case 2: // STATUS_REQUEST
    sendFeedback(rxCmd.slaveId, idx, tewe.getState(key.c_str()));
    break;
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  // Config Tewe
  tewe.setRelayActiveLow(true);
  tewe.mapPin("toggle1", 2);  // D4
  tewe.mapPin("toggle2", 4);  // D2
  tewe.mapPin("toggle3", 5);  // D1
  tewe.mapPin("toggle4", 12); // D6
  tewe.mapPin("toggle5", 13); // D7
  tewe.mapPin("toggle6", 14); // D5

  tewe.onToggle(onToggleChanged);

  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "https://nh.mdpower.io/api/devices", "DEV_JQDK0QYUUJ");

  // ── Init ESP-NOW ─────────────────────────────────────────
  if (esp_now_init() != 0) {
    Serial.println("ESP-NOW init GAGAL!");
  } else {
    esp_now_set_self_role(ESP_NOW_ROLE_COMBO);
    esp_now_register_recv_cb(onEspNowReceive);

    // Daftarkan semua slave sebagai peer
    for (int i = 0; i < SLAVE_COUNT; i++) {
      esp_now_add_peer(slaveMacs[i], ESP_NOW_ROLE_COMBO, 0, NULL, 0);
      Serial.printf("Peer Slave %d: ", i);
      printMac(slaveMacs[i]);
      Serial.println();
    }

    Serial.println("ESP-NOW Ready (Master/Gateway)");
  }

  Serial.print("MASTER MAC: ");
  Serial.println(WiFi.macAddress());
  Serial.printf("Slaves terdaftar: %d\n\n", SLAVE_COUNT);
}

void loop() {
  tewe.run();
  processEspNow();
}
