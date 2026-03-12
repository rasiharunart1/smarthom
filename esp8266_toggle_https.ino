// ============================================================
// 📄 ESP8266 SmartHome - HTTPS + MQTTS | Multi-Toggle Support
// 📌 10 Widget Toggle: Subscribe & Publish dengan TLS/SSL
// ============================================================
//
// Library yang dibutuhkan (Install via Library Manager):
//   - PubSubClient      by Nick O'Leary
//   - ArduinoJson       by Benoit Blanchon (v7.x)
//   Board: esp8266 by ESP8266 Community
//   URL  : http://arduino.esp8266.com/stable/package_esp8266com_index.json
//
// ============================================================

#include <ArduinoJson.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <WiFiClientSecureBearSSL.h>

// ============================================================
// ⚙️  KONFIGURASI WAJIB - Sesuaikan bagian ini!
// ============================================================

// --- WiFi ---
const char *WIFI_SSID = "NamaWiFi_Anda";
const char *WIFI_PASSWORD = "PasswordWiFi_Anda";

// --- API Server ---
// http://  → server lokal tanpa SSL
// https:// → server publik dengan SSL
const char *API_BASE_URL = "http://10.199.9.103:8000/api/devices";
const char *DEVICE_CODE = "DEV_JQDK0QYUUJ";

// --- Pin Mapping untuk setiap Toggle ---
// Format: { "widget_key", GPIO_pin }
// Ganti pin sesuai kebutuhan hardware kamu
// -1 = tidak dipetakan ke pin fisik (virtual/remote only)
struct PinMap {
  const char *key;
  int pin;
};
const PinMap PIN_MAP[] = {
    {"toggle1", 2},   // D4  NodeMCU
    {"toggle2", 4},   // D2
    {"toggle3", 5},   // D1
    {"toggle4", 12},  // D6
    {"toggle5", 13},  // D7
    {"toggle6", 14},  // D5
    {"toggle7", 15},  // D8
    {"toggle8", 16},  // D0
    {"toggle9", -1},  // Tidak ada pin fisik
    {"toggle10", -1}, // Tidak ada pin fisik
};
const int PIN_MAP_COUNT = sizeof(PIN_MAP) / sizeof(PIN_MAP[0]);

// --- Relay Logic: ubah false jika relay kamu ACTIVE HIGH ---
// true  = ACTIVE LOW  (ON → pin LOW,  OFF → pin HIGH) ← default relay module
// false = ACTIVE HIGH (ON → pin HIGH, OFF → pin LOW)
const bool RELAY_ACTIVE_LOW = true;

// --- Interval ---
const unsigned long HEARTBEAT_INTERVAL = 60000; // ms

// ============================================================
// 🔧  STRUCT & VARIABEL GLOBAL
// ============================================================

struct ToggleWidget {
  String key;
  String name;
  bool state;
  int pin;             // -1 jika tidak ada pin fisik
  String topicControl; // subscribe: terima perintah dari dashboard
  String topicSensor;  // publish  : kirim status ke dashboard
  bool valid;          // true setelah berhasil di-fetch dari API
};

const int MAX_TOGGLES = 15;
ToggleWidget toggles[MAX_TOGGLES];
int toggleCount = 0;

// Auth info
String mqttServer = "";
int mqttPort = 8883;
String mqttUser = "";
String mqttPassword = "";
int userId = 0;

// Heartbeat topic
String topicHeartbeat = "";

// Timing
unsigned long lastHeartbeat = 0;

// HTTP clients
WiFiClient httpClient;
BearSSL::WiFiClientSecure httpsClient;

// MQTT over TLS
BearSSL::WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);

// Helper: apakah API pakai HTTPS?
bool apiIsHttps() { return String(API_BASE_URL).startsWith("https"); }

// ============================================================
// 🔌  APPLY PIN - Terapkan state ke pin fisik
// ============================================================
void applyPin(ToggleWidget &w) {
  if (w.pin < 0)
    return; // tidak ada pin fisik
  bool level = RELAY_ACTIVE_LOW ? !w.state : w.state;
  digitalWrite(w.pin, level ? HIGH : LOW);
  Serial.printf("   🔌 GPIO%d [%s] → %s\n", w.pin, w.key.c_str(),
                w.state ? "ON" : "OFF");
}

// ============================================================
// 📤  PUBLISH - Kirim status satu toggle ke dashboard
// ============================================================
void publishToggle(ToggleWidget &w) {
  if (!mqttClient.connected())
    return;

  // Dashboard JS (baris updateWidgetUI) menggunakan strict string comparison:
  //   value === '1' || value === 'true' || value === 'on'
  // Jadi harus kirim PLAIN STRING "1" atau "0", BUKAN JSON!
  String payload = w.state ? "1" : "0";

  bool ok = mqttClient.publish(w.topicSensor.c_str(), payload.c_str(),
                               true); // retain=true
  Serial.printf("   📤 [%s] %s → %s %s\n", w.key.c_str(), w.topicSensor.c_str(),
                payload.c_str(), ok ? "✅" : "❌");
}

// Publish semua toggle sekaligus
void publishAllToggles() {
  Serial.println("📤 Publishing all toggle states...");
  for (int i = 0; i < toggleCount; i++) {
    publishToggle(toggles[i]);
    delay(30); // sedikit delay agar buffer MQTT tidak penuh
  }
}

// ============================================================
// 🔔  MQTT CALLBACK - Pesan masuk dari dashboard
// ============================================================
void mqttCallback(char *topic, byte *payload, unsigned int length) {
  String message = "";
  for (unsigned int i = 0; i < length; i++)
    message += (char)payload[i];
  String topicStr = String(topic);

  // Cari toggle yang sesuai dengan topic
  for (int i = 0; i < toggleCount; i++) {
    if (topicStr == toggles[i].topicControl) {
      bool newState = (message == "1" || message == "true" || message == "on");

      Serial.printf("📩 [%s] %s → %s\n", toggles[i].key.c_str(),
                    toggles[i].state ? "ON" : "OFF", newState ? "ON" : "OFF");

      toggles[i].state = newState;
      applyPin(toggles[i]);
      publishToggle(toggles[i]);
      return;
    }
  }

  Serial.println("📩 MQTT (unknown topic): " + topicStr + " = " + message);
}

// ============================================================
// 📡  WIFI
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
    Serial.println("✅ WiFi connected! IP: " + WiFi.localIP().toString());
    Serial.println("📶 RSSI: " + String(WiFi.RSSI()) + " dBm");
  } else {
    Serial.println("❌ WiFi GAGAL! Restart...");
    delay(3000);
    ESP.restart();
  }
}

// ============================================================
// 🔐  AUTHENTICATE - POST /auth
// ============================================================
bool authenticate() {
  bool isHttps = apiIsHttps();
  Serial.println("🔐 Authenticating via " + String(isHttps ? "HTTPS" : "HTTP") +
                 "...");

  HTTPClient http;
  String url = String(API_BASE_URL) + "/auth";
  Serial.println("   URL: " + url);

  bool ok = isHttps ? (httpsClient.setInsecure(), http.begin(httpsClient, url))
                    : http.begin(httpClient, url);

  if (!ok) {
    Serial.println("❌ begin() gagal!");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  JsonDocument req;
  req["device_code"] = DEVICE_CODE;
  String body;
  serializeJson(req, body);

  int code = http.POST(body);
  Serial.println("   HTTP: " + String(code));

  if (code == 200) {
    String resp = http.getString();
    JsonDocument res;
    if (deserializeJson(res, resp) == DeserializationError::Ok &&
        res["success"] == true) {
      userId = res["device"]["user_id"];
      mqttServer = res["mqtt"]["host"].as<String>();
      mqttPort = res["mqtt"]["port"] | 8883;
      mqttUser = res["mqtt"]["username"].as<String>();
      mqttPassword = res["mqtt"]["password"].as<String>();

      Serial.println("✅ Auth OK! user=" + String(userId) +
                     " mqtt=" + mqttServer + ":" + String(mqttPort));
      http.end();
      return true;
    }
    Serial.println("❌ JSON error / success=false → " + resp);
  } else if (code < 0) {
    Serial.println("❌ Connection error: " + http.errorToString(code));
  } else {
    Serial.println("❌ HTTP " + String(code) + ": " + http.getString());
  }
  http.end();
  return false;
}

// ============================================================
// 🗂️  FETCH WIDGETS - GET /widgets → isi array toggles[]
// ============================================================
bool fetchWidgets() {
  bool isHttps = apiIsHttps();
  Serial.println("🗂️  Fetching widgets via " +
                 String(isHttps ? "HTTPS" : "HTTP") + "...");

  HTTPClient http;
  String url = String(API_BASE_URL) + "/" + DEVICE_CODE + "/widgets";
  Serial.println("   URL: " + url);

  bool ok = isHttps ? (httpsClient.setInsecure(), http.begin(httpsClient, url))
                    : http.begin(httpClient, url);

  if (!ok) {
    Serial.println("❌ begin() gagal!");
    return false;
  }

  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  int code = http.GET();
  Serial.println("   HTTP: " + String(code));

  if (code == 200) {
    String payload = http.getString();

    // Alokasi JSON dokumen yang cukup besar untuk 10 widget + config
    JsonDocument doc;
    if (deserializeJson(doc, payload) == DeserializationError::Ok &&
        doc["success"] == true) {
      toggleCount = 0;
      JsonObject widgetsObj = doc["widgets"].as<JsonObject>();
      String base =
          "users/" + String(userId) + "/devices/" + String(DEVICE_CODE);

      for (JsonPair kv : widgetsObj) {
        if (toggleCount >= MAX_TOGGLES)
          break;

        String key = kv.key().c_str();
        String type = kv.value()["type"].as<String>();
        String name = kv.value()["name"].as<String>();
        String val = kv.value()["value"].as<String>();

        if (type != "toggle")
          continue; // skip non-toggle

        ToggleWidget &w = toggles[toggleCount];
        w.key = key;
        w.name = name;
        w.state = (val == "1" || val == "true" || val == "on");
        w.pin = -1;
        w.topicControl = base + "/control/" +
                         key; // ← pakai KEY (toggle1), bukan name (Toggle 1)
        w.topicSensor =
            base + "/sensors/" + key; // dashboard subscribe sensors/#
        w.valid = true;

        // Cari pin dari PIN_MAP
        for (int p = 0; p < PIN_MAP_COUNT; p++) {
          if (key == PIN_MAP[p].key) {
            w.pin = PIN_MAP[p].pin;
            break;
          }
        }

        Serial.printf("   [%d] %-8s | %-10s | %s | pin=%d\n", toggleCount,
                      key.c_str(), name.c_str(), w.state ? "ON " : "OFF",
                      w.pin);

        toggleCount++;
      }

      // Heartbeat topic
      topicHeartbeat = base + "/heartbeat";

      Serial.println("✅ Loaded " + String(toggleCount) + " toggles");
      http.end();
      return true;
    }
    Serial.println("❌ JSON parse gagal / success=false");
  } else if (code < 0) {
    Serial.println("❌ Connection error: " + http.errorToString(code));
  } else {
    Serial.println("❌ HTTP " + String(code) + ": " + http.getString());
  }
  http.end();
  return false;
}

// ============================================================
// 📡  MQTT CONNECT - via MQTTS (TLS port 8883)
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

    // Subscribe semua control topic
    for (int i = 0; i < toggleCount; i++) {
      mqttClient.subscribe(toggles[i].topicControl.c_str());
      Serial.println("   📥 " + toggles[i].topicControl);
    }

    // Publish state awal semua toggle
    publishAllToggles();
    return true;
  }

  Serial.println(" ❌ rc=" + String(mqttClient.state()));
  return false;
}

// ============================================================
// 🔁  RECONNECT MQTT
// ============================================================
void reconnectMQTT() {
  if (mqttClient.connected())
    return;
  Serial.println("⚠️  MQTT putus! Reconnecting...");
  for (int t = 0; t < 3; t++) {
    if (connectMQTT())
      return;
    Serial.println("   Retry " + String(t + 1) + "/3...");
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
  if (!mqttClient.connected() || topicHeartbeat == "")
    return;
  JsonDocument doc;
  doc["status"] = "online";
  doc["uptime"] = millis() / 1000;
  doc["free_heap"] = ESP.getFreeHeap();
  doc["rssi"] = WiFi.RSSI();
  String payload;
  serializeJson(doc, payload);
  mqttClient.publish(topicHeartbeat.c_str(), payload.c_str());
  Serial.println("💓 " + payload);
}

// ============================================================
// ⌨️  SERIAL INPUT
// ============================================================
//  Format perintah:
//    on  [N]   → toggle N ON  (default: semua)
//    off [N]   → toggle N OFF (default: semua)
//    t   [N]   → flip toggle N (default: semua)
//    s         → tampilkan status semua
//    pub       → publish ulang semua state
//  Contoh: "on 1", "off 3", "t 5", "on" (semua ON)
// ============================================================
void handleSerialInput() {
  if (!Serial.available())
    return;
  String input = Serial.readStringUntil('\n');
  input.trim();
  if (input.length() == 0)
    return;

  Serial.println("⌨️  > " + input);

  // Parse perintah dan argumen
  String cmd = input;
  int idx = -1; // -1 = semua toggle

  int space = input.indexOf(' ');
  if (space > 0) {
    cmd = input.substring(0, space);
    String arg = input.substring(space + 1);
    arg.trim();
    // Coba parse angka (1-based) atau key (toggle1, dll.)
    if (arg.toInt() > 0) {
      idx = arg.toInt() - 1; // convert ke 0-based
    } else {
      // Cari berdasarkan key
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

  // Validasi index
  if (idx >= toggleCount) {
    Serial.println("❌ Index tidak ada. Max: " + String(toggleCount));
    return;
  }

  if (cmd == "on" || cmd == "off") {
    bool newState = (cmd == "on");
    if (idx >= 0) {
      // Satu toggle
      toggles[idx].state = newState;
      applyPin(toggles[idx]);
      publishToggle(toggles[idx]);
    } else {
      // Semua toggle
      for (int i = 0; i < toggleCount; i++) {
        toggles[i].state = newState;
        applyPin(toggles[i]);
        publishToggle(toggles[i]);
        delay(30);
      }
      Serial.println("✅ Semua toggle → " + String(newState ? "ON" : "OFF"));
    }

  } else if (cmd == "t") {
    if (idx >= 0) {
      toggles[idx].state = !toggles[idx].state;
      applyPin(toggles[idx]);
      publishToggle(toggles[idx]);
      Serial.println("🔄 " + toggles[idx].key + " → " +
                     (toggles[idx].state ? "ON" : "OFF"));
    } else {
      for (int i = 0; i < toggleCount; i++) {
        toggles[i].state = !toggles[i].state;
        applyPin(toggles[i]);
        publishToggle(toggles[i]);
        delay(30);
      }
      Serial.println("🔄 Semua toggle flipped");
    }

  } else if (cmd == "s") {
    Serial.println("\n📊 ══ STATUS SEMUA TOGGLE ══");
    Serial.printf("%-3s %-10s %-12s %-5s %-4s\n", "#", "Key", "Name", "State",
                  "Pin");
    Serial.println("--------------------------------------------");
    for (int i = 0; i < toggleCount; i++) {
      Serial.printf("%-3d %-10s %-12s %-5s %-4d\n", i + 1,
                    toggles[i].key.c_str(), toggles[i].name.c_str(),
                    toggles[i].state ? "ON" : "OFF", toggles[i].pin);
    }
    Serial.println("--------------------------------------------");
    Serial.println("MQTT   : " + String(mqttClient.connected()
                                            ? "Connected ✅"
                                            : "Disconnected ❌"));
    Serial.println("Uptime : " + String(millis() / 1000) +
                   "s | Heap: " + String(ESP.getFreeHeap()) + " bytes");
    Serial.println();

  } else if (cmd == "pub") {
    publishAllToggles();

  } else {
    Serial.println("❓ Perintah: on [N] | off [N] | t [N] | s | pub");
    Serial.println("   N = nomor toggle (1-10), kosong = semua");
  }
}

// ============================================================
// 🚀  SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println(F("\n╔═══════════════════════════════════════════╗"));
  Serial.println(F("║  ESP8266 SmartHome - Multi Toggle v2.0   ║"));
  Serial.println(F("║  10 Toggle | HTTPS API | MQTTS TLS        ║"));
  Serial.println(F("╚═══════════════════════════════════════════╝\n"));

  // Setup pins dari PIN_MAP
  for (int p = 0; p < PIN_MAP_COUNT; p++) {
    if (PIN_MAP[p].pin >= 0) {
      pinMode(PIN_MAP[p].pin, OUTPUT);
      // Default: semua relay OFF
      digitalWrite(PIN_MAP[p].pin, RELAY_ACTIVE_LOW ? HIGH : LOW);
      Serial.printf("✅ GPIO%-2d → %s (relay OFF)\n", PIN_MAP[p].pin,
                    PIN_MAP[p].key);
    }
  }

  // 1. WiFi
  connectWiFi();

  // 2. Auth → dapat MQTT creds
  if (!authenticate()) {
    Serial.println("❌ Auth gagal! Restart...");
    delay(5000);
    ESP.restart();
  }

  // 3. Fetch widgets → isi toggles[]
  if (!fetchWidgets()) {
    Serial.println("❌ Fetch widget gagal! Restart...");
    delay(5000);
    ESP.restart();
  }

  // 4. Apply initial state ke semua pin
  for (int i = 0; i < toggleCount; i++)
    applyPin(toggles[i]);

  // 5. Connect MQTT + subscribe + publish awal
  if (!connectMQTT()) {
    Serial.println("❌ MQTT gagal! Restart...");
    delay(5000);
    ESP.restart();
  }

  Serial.println(F("\n🚀 Device siap!"));
  Serial.println(F("⌨️  Perintah Serial Monitor:"));
  Serial.println(
      F("   on [N]  → ON toggle N (contoh: on 1) | tanpa N = semua"));
  Serial.println(
      F("   off [N] → OFF toggle N               | tanpa N = semua"));
  Serial.println(
      F("   t [N]   → Flip toggle N              | tanpa N = semua"));
  Serial.println(F("   s       → Status semua toggle"));
  Serial.println(F("   pub     → Re-publish semua state\n"));
}

// ============================================================
// 🔄  LOOP
// ============================================================
// ============================================================
// 🔄  LOOP - Dioptimasi untuk respons MQTT secepat mungkin
// ============================================================
// Strategi: cek WiFi & MQTT hanya setiap 5 detik,
// sehingga mqttClient.loop() bisa berjalan SESERING mungkin
// tanpa hambatan pengecekan status yang mahal.
// ============================================================

unsigned long lastConnCheck = 0;
const unsigned long CONN_CHECK_INTERVAL = 5000; // cek WiFi/MQTT setiap 5 detik

void loop() {
  // ── 1. Prioritas utama: proses pesan MQTT masuk ──────────
  mqttClient.loop();

  // ── 2. Proses Serial Monitor input ───────────────────────
  handleSerialInput();

  // ── 3. Cek koneksi WiFi & MQTT hanya setiap 5 detik ─────
  //  (WiFi.status() cukup mahal, jangan panggil tiap loop!)
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

  // ── 4. Heartbeat setiap 60 detik ─────────────────────────
  if (millis() - lastHeartbeat >= HEARTBEAT_INTERVAL) {
    sendHeartbeat();
    lastHeartbeat = millis();
  }

  // ── 5. Beri waktu WiFi stack untuk proses background ─────
  yield();
}
