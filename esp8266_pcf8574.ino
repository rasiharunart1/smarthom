// ============================================================
// 📄 ESP8266 SmartHome - PCF8574 I2C Expander Support
// 📌 8 Toggle via PCF8574 | HTTPS API | MQTTS TLS
// ============================================================
//
// Library yang dibutuhkan (Install via Library Manager):
//   - PubSubClient      by Nick O'Leary
//   - ArduinoJson       by Benoit Blanchon (v7.x)
//   - Adafruit PCF8574  by Adafruit          ← WAJIB INSTALL
//   Board: esp8266 by ESP8266 Community
//   URL  : http://arduino.esp8266.com/stable/package_esp8266com_index.json
//
// Wiring PCF8574 ke ESP8266 (NodeMCU):
//   PCF8574 VCC  → 3.3V atau 5V
//   PCF8574 GND  → GND
//   PCF8574 SDA  → D2 (GPIO4)
//   PCF8574 SCL  → D1 (GPIO5)
//   PCF8574 A0   → GND  ┐
//   PCF8574 A1   → GND  ├─ Alamat I2C = 0x20
//   PCF8574 A2   → GND  ┘
//   PCF8574 P0..P7 → Relay IN1..IN8
//
// ============================================================

#include <ArduinoJson.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>
#include <Adafruit_PCF8574.h> // Library PCF8574 by Adafruit
#include <PubSubClient.h>
#include <Wire.h>
#include <WiFiClientSecureBearSSL.h>

// ============================================================
// ⚙️  KONFIGURASI WAJIB - Sesuaikan bagian ini!
// ============================================================

// --- WiFi ---
const char *WIFI_SSID     = "NamaWiFi_Anda";
const char *WIFI_PASSWORD = "PasswordWiFi_Anda";

// --- API Server ---
const char *API_BASE_URL = "http://10.199.9.103:8000/api/devices";
const char *DEVICE_CODE  = "DEV_JQDK0QYUUJ";

// --- PCF8574 I2C Address ---
// Default: semua A0/A1/A2 = GND → alamat 0x20
// Jika A0=VCC → 0x21, A1=VCC → 0x22, A0+A1=VCC → 0x23, dst.
#define PCF8574_ADDRESS 0x20

// --- I2C Pins (SDA, SCL) ---
// NodeMCU default: SDA=D2(GPIO4), SCL=D1(GPIO5)
#define I2C_SDA 4
#define I2C_SCL 5

// --- Relay Logic: ubah false jika relay kamu ACTIVE HIGH ---
// true  = ACTIVE LOW  (ON → pin LOW,  OFF → pin HIGH) ← default relay module
// false = ACTIVE HIGH (ON → pin HIGH, OFF → pin LOW)
const bool RELAY_ACTIVE_LOW = true;

// --- Pin Mapping: setiap widget_key → PCF8574 pin (P0..P7) ---
// Format: { "widget_key", pcf_pin }
// -1 = tidak dipetakan (virtual/remote only)
struct PinMap {
  const char *key;
  int8_t pin; // P0=0 .. P7=7, -1=tidak ada
};
const PinMap PIN_MAP[] = {
    {"toggle1", 0},  // PCF8574 P0 → Relay 1
    {"toggle2", 1},  // PCF8574 P1 → Relay 2
    {"toggle3", 2},  // PCF8574 P2 → Relay 3
    {"toggle4", 3},  // PCF8574 P3 → Relay 4
    {"toggle5", 4},  // PCF8574 P4 → Relay 5
    {"toggle6", 5},  // PCF8574 P5 → Relay 6
    {"toggle7", 6},  // PCF8574 P6 → Relay 7
    {"toggle8", 7},  // PCF8574 P7 → Relay 8
};
const int PIN_MAP_COUNT = sizeof(PIN_MAP) / sizeof(PIN_MAP[0]);

// --- Interval ---
const unsigned long HEARTBEAT_INTERVAL = 60000; // ms

// ============================================================
// 🔧  PCF8574 & STRUKTUR GLOBAL
// ============================================================

Adafruit_PCF8574 pcf;
bool pcfFound = false;

struct ToggleWidget {
  String key;
  String name;
  bool state;
  int8_t pin;          // -1 jika tidak ada pin; 0..7 untuk PCF8574
  String topicControl; // subscribe: terima perintah dari dashboard
  String topicSensor;  // publish  : kirim status ke dashboard
  bool valid;
};

const int MAX_TOGGLES = 15;
ToggleWidget toggles[MAX_TOGGLES];
int toggleCount = 0;

// Auth info
String mqttServer   = "";
int    mqttPort     = 8883;
String mqttUser     = "";
String mqttPassword = "";
int    userId       = 0;

// Heartbeat
String topicHeartbeat = "";
unsigned long lastHeartbeat = 0;

// HTTP clients
WiFiClient httpClient;
BearSSL::WiFiClientSecure httpsClient;

// MQTT over TLS
BearSSL::WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);

bool apiIsHttps() { return String(API_BASE_URL).startsWith("https"); }

// ============================================================
// 🔌  APPLY PCF8574 PIN - Terapkan state ke pin expander
// ============================================================
void applyPin(ToggleWidget &w) {
  if (!pcfFound || w.pin < 0) return;

  bool level = RELAY_ACTIVE_LOW ? !w.state : w.state;
  pcf.digitalWrite(w.pin, level ? HIGH : LOW);

  Serial.printf("   🔌 PCF8574-P%d [%s] → %s\n",
                w.pin, w.key.c_str(), w.state ? "ON" : "OFF");
}

// ============================================================
// 📤  PUBLISH - Kirim status satu toggle ke dashboard
// ============================================================
void publishToggle(ToggleWidget &w) {
  if (!mqttClient.connected()) return;
  String payload = w.state ? "1" : "0";
  bool ok = mqttClient.publish(w.topicSensor.c_str(), payload.c_str(), true);
  Serial.printf("   📤 [%s] %s → %s %s\n",
                w.key.c_str(), w.topicSensor.c_str(),
                payload.c_str(), ok ? "✅" : "❌");
}

void publishAllToggles() {
  Serial.println("📤 Publishing all toggle states...");
  for (int i = 0; i < toggleCount; i++) {
    publishToggle(toggles[i]);
    delay(30);
  }
}

// ============================================================
// 🔔  MQTT CALLBACK
// ============================================================
void mqttCallback(char *topic, byte *payload, unsigned int length) {
  String message = "";
  for (unsigned int i = 0; i < length; i++)
    message += (char)payload[i];
  String topicStr = String(topic);

  for (int i = 0; i < toggleCount; i++) {
    if (topicStr == toggles[i].topicControl) {
      bool newState = (message == "1" || message == "true" || message == "on");
      Serial.printf("📩 [%s] %s → %s\n",
                    toggles[i].key.c_str(),
                    toggles[i].state ? "ON" : "OFF",
                    newState ? "ON" : "OFF");
      toggles[i].state = newState;
      applyPin(toggles[i]);
      publishToggle(toggles[i]);
      return;
    }
  }
  Serial.println("📩 MQTT (unknown topic): " + topicStr + " = " + message);
}

// ============================================================
// 📡  WiFi
// ============================================================
void connectWiFi() {
  Serial.print("📡 Connecting WiFi: ");
  Serial.println(WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 40) {
    delay(500);
    Serial.print(".");
    tries++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("✅ WiFi! IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("❌ WiFi GAGAL! Restart...");
    delay(3000);
    ESP.restart();
  }
}

// ============================================================
// 🔐  AUTHENTICATE
// ============================================================
bool authenticate() {
  bool isHttps = apiIsHttps();
  Serial.println("🔐 Auth via " + String(isHttps ? "HTTPS" : "HTTP") + "...");

  HTTPClient http;
  String url = String(API_BASE_URL) + "/auth";

  bool ok = isHttps
    ? (httpsClient.setInsecure(), http.begin(httpsClient, url))
    : http.begin(httpClient, url);
  if (!ok) { Serial.println("❌ begin() gagal!"); return false; }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  JsonDocument req;
  req["device_code"] = DEVICE_CODE;
  String body;
  serializeJson(req, body);

  int code = http.POST(body);
  if (code == 200) {
    String resp = http.getString();
    JsonDocument res;
    if (deserializeJson(res, resp) == DeserializationError::Ok &&
        res["success"] == true) {
      userId       = res["device"]["user_id"];
      mqttServer   = res["mqtt"]["host"].as<String>();
      mqttPort     = res["mqtt"]["port"] | 8883;
      mqttUser     = res["mqtt"]["username"].as<String>();
      mqttPassword = res["mqtt"]["password"].as<String>();
      Serial.println("✅ Auth OK! user=" + String(userId));
      http.end();
      return true;
    }
    Serial.println("❌ JSON error: " + resp);
  } else {
    Serial.println("❌ HTTP " + String(code));
  }
  http.end();
  return false;
}

// ============================================================
// 🗂️  FETCH WIDGETS
// ============================================================
bool fetchWidgets() {
  bool isHttps = apiIsHttps();
  HTTPClient http;
  String url = String(API_BASE_URL) + "/" + DEVICE_CODE + "/widgets";

  bool ok = isHttps
    ? (httpsClient.setInsecure(), http.begin(httpsClient, url))
    : http.begin(httpClient, url);
  if (!ok) { Serial.println("❌ begin() gagal!"); return false; }

  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  int code = http.GET();
  if (code == 200) {
    String payload = http.getString();
    JsonDocument doc;
    if (deserializeJson(doc, payload) == DeserializationError::Ok &&
        doc["success"] == true) {
      toggleCount = 0;
      JsonObject widgetsObj = doc["widgets"].as<JsonObject>();
      String base = "users/" + String(userId) + "/devices/" + String(DEVICE_CODE);

      for (JsonPair kv : widgetsObj) {
        if (toggleCount >= MAX_TOGGLES) break;

        String key  = kv.key().c_str();
        String type = kv.value()["type"].as<String>();
        String name = kv.value()["name"].as<String>();
        String val  = kv.value()["value"].as<String>();

        if (type != "toggle") continue;

        ToggleWidget &w = toggles[toggleCount];
        w.key          = key;
        w.name         = name;
        w.state        = (val == "1" || val == "true" || val == "on");
        w.pin          = -1;
        w.topicControl = base + "/control/" + key;
        w.topicSensor  = base + "/sensors/" + key;
        w.valid        = true;

        // Cari pin dari PIN_MAP
        for (int p = 0; p < PIN_MAP_COUNT; p++) {
          if (key == PIN_MAP[p].key) {
            w.pin = PIN_MAP[p].pin;
            break;
          }
        }

        Serial.printf("   [%d] %-8s | %-10s | %s | PCF_P%d\n",
                      toggleCount, key.c_str(), name.c_str(),
                      w.state ? "ON " : "OFF", w.pin);
        toggleCount++;
      }

      topicHeartbeat = base + "/heartbeat";
      Serial.println("✅ Loaded " + String(toggleCount) + " toggles");
      http.end();
      return true;
    }
  }
  Serial.println("❌ Fetch gagal: HTTP " + String(code));
  http.end();
  return false;
}

// ============================================================
// 📡  MQTT CONNECT
// ============================================================
bool connectMQTT() {
  espClient.setInsecure();
  mqttClient.setServer(mqttServer.c_str(), mqttPort);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setKeepAlive(60);
  mqttClient.setBufferSize(512);

  String cid = "ESP8266-" + String(DEVICE_CODE) + "-" + String(millis());
  Serial.print("📡 MQTTS " + mqttServer + ":" + String(mqttPort) + " ...");

  if (mqttClient.connect(cid.c_str(), mqttUser.c_str(), mqttPassword.c_str())) {
    Serial.println(" ✅");
    for (int i = 0; i < toggleCount; i++) {
      mqttClient.subscribe(toggles[i].topicControl.c_str());
      Serial.println("   📥 " + toggles[i].topicControl);
    }
    publishAllToggles();
    return true;
  }
  Serial.println(" ❌ rc=" + String(mqttClient.state()));
  return false;
}

void reconnectMQTT() {
  if (mqttClient.connected()) return;
  Serial.println("⚠️  MQTT putus! Reconnecting...");
  for (int t = 0; t < 3; t++) {
    if (connectMQTT()) return;
    delay(3000);
  }
  Serial.println("❌ MQTT gagal! Restart...");
  delay(5000);
  ESP.restart();
}

// ============================================================
// 💓  HEARTBEAT
// ============================================================
void sendHeartbeat() {
  if (!mqttClient.connected() || topicHeartbeat == "") return;
  JsonDocument doc;
  doc["status"]    = "online";
  doc["uptime"]    = millis() / 1000;
  doc["free_heap"] = ESP.getFreeHeap();
  doc["rssi"]      = WiFi.RSSI();
  String payload;
  serializeJson(doc, payload);
  mqttClient.publish(topicHeartbeat.c_str(), payload.c_str());
  Serial.println("💓 " + payload);
}

// ============================================================
// ⌨️  SERIAL INPUT
// ============================================================
void handleSerialInput() {
  if (!Serial.available()) return;
  String input = Serial.readStringUntil('\n');
  input.trim();
  if (input.length() == 0) return;

  Serial.println("⌨️  > " + input);

  String cmd = input;
  int idx = -1;

  int space = input.indexOf(' ');
  if (space > 0) {
    cmd = input.substring(0, space);
    String arg = input.substring(space + 1);
    arg.trim();
    if (arg.toInt() > 0) {
      idx = arg.toInt() - 1;
    } else {
      for (int i = 0; i < toggleCount; i++) {
        if (toggles[i].key.equalsIgnoreCase(arg) ||
            toggles[i].name.equalsIgnoreCase(arg)) {
          idx = i;
          break;
        }
      }
    }
  }
  cmd.toLowerCase();

  if (idx >= toggleCount) {
    Serial.println("❌ Index tidak ada. Max: " + String(toggleCount));
    return;
  }

  if (cmd == "on" || cmd == "off") {
    bool newState = (cmd == "on");
    if (idx >= 0) {
      toggles[idx].state = newState;
      applyPin(toggles[idx]);
      publishToggle(toggles[idx]);
    } else {
      for (int i = 0; i < toggleCount; i++) {
        toggles[i].state = newState;
        applyPin(toggles[i]);
        publishToggle(toggles[i]);
        delay(30);
      }
      Serial.println("✅ Semua → " + String(newState ? "ON" : "OFF"));
    }
  } else if (cmd == "t") {
    if (idx >= 0) {
      toggles[idx].state = !toggles[idx].state;
      applyPin(toggles[idx]);
      publishToggle(toggles[idx]);
    } else {
      for (int i = 0; i < toggleCount; i++) {
        toggles[i].state = !toggles[i].state;
        applyPin(toggles[i]);
        publishToggle(toggles[i]);
        delay(30);
      }
    }
  } else if (cmd == "s") {
    Serial.println("\n📊 STATUS SEMUA TOGGLE");
    Serial.printf("%-3s %-10s %-12s %-5s %-6s\n", "#", "Key", "Name", "State", "PCF_Pin");
    Serial.println("------------------------------------------");
    for (int i = 0; i < toggleCount; i++) {
      Serial.printf("%-3d %-10s %-12s %-5s P%-5d\n",
                    i + 1, toggles[i].key.c_str(), toggles[i].name.c_str(),
                    toggles[i].state ? "ON" : "OFF", toggles[i].pin);
    }
    Serial.println("MQTT   : " + String(mqttClient.connected() ? "Connected ✅" : "Disconnected ❌"));
    Serial.println("PCF8574: " + String(pcfFound ? "Detected ✅" : "NOT FOUND ❌"));
    Serial.println("Uptime : " + String(millis() / 1000) + "s");
    Serial.println();
  } else if (cmd == "pub") {
    publishAllToggles();
  } else {
    Serial.println("❓ Perintah: on [N] | off [N] | t [N] | s | pub");
  }
}

// ============================================================
// 🚀  SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println(F("\n╔═══════════════════════════════════════════╗"));
  Serial.println(F("║  ESP8266 SmartHome - PCF8574 v1.0        ║"));
  Serial.println(F("║  8 Toggle | I2C Expander | MQTTS TLS     ║"));
  Serial.println(F("╚═══════════════════════════════════════════╝\n"));

  // ── 1. Init I2C & PCF8574 ────────────────────────────────
  Wire.begin(I2C_SDA, I2C_SCL);

  // Deteksi keberadaan PCF8574
  pcfFound = pcf.begin(PCF8574_ADDRESS, &Wire);

  if (pcfFound) {
    Serial.printf("✅ PCF8574 ditemukan di 0x%02X\n", PCF8574_ADDRESS);
    // Inisialisasi semua pin PCF8574 → relay OFF
    for (int p = 0; p < 8; p++) {
      pcf.pinMode(p, OUTPUT);
      pcf.digitalWrite(p, RELAY_ACTIVE_LOW ? HIGH : LOW);
    }
    Serial.println("✅ Semua relay PCF8574 diset ke OFF");
  } else {
    Serial.printf("❌ PCF8574 TIDAK ditemukan di 0x%02X!\n", PCF8574_ADDRESS);
    Serial.println("   Periksa wiring SDA/SCL dan alamat A0/A1/A2!");
    // Program tetap lanjut tapi pin tidak bisa dikontrol
  }

  // ── 2. WiFi ──────────────────────────────────────────────
  connectWiFi();

  // ── 3. Auth ──────────────────────────────────────────────
  if (!authenticate()) {
    Serial.println("❌ Auth gagal! Restart...");
    delay(5000);
    ESP.restart();
  }

  // ── 4. Fetch widgets ─────────────────────────────────────
  if (!fetchWidgets()) {
    Serial.println("❌ Fetch widget gagal! Restart...");
    delay(5000);
    ESP.restart();
  }

  // ── 5. Apply state awal ke PCF8574 ───────────────────────
  for (int i = 0; i < toggleCount; i++)
    applyPin(toggles[i]);

  // ── 6. Connect MQTT ──────────────────────────────────────
  if (!connectMQTT()) {
    Serial.println("❌ MQTT gagal! Restart...");
    delay(5000);
    ESP.restart();
  }

  Serial.println(F("\n🚀 Device siap!"));
  Serial.println(F("⌨️  Serial: on [N] | off [N] | t [N] | s | pub"));
}

// ============================================================
// 🔄  LOOP
// ============================================================
unsigned long lastConnCheck = 0;
const unsigned long CONN_CHECK_INTERVAL = 5000;

void loop() {
  mqttClient.loop();
  handleSerialInput();

  if (millis() - lastConnCheck >= CONN_CHECK_INTERVAL) {
    lastConnCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️  WiFi putus! Reconnecting...");
      connectWiFi();
      reconnectMQTT();
    } else if (!mqttClient.connected()) {
      reconnectMQTT();
    }
  }

  if (millis() - lastHeartbeat >= HEARTBEAT_INTERVAL) {
    sendHeartbeat();
    lastHeartbeat = millis();
  }

  yield();
}
