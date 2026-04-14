// ============================================================
// esp8266_gateway.ino  — v2.2
// ESP8266 — WiFi/MQTT Gateway + AP Config Portal + Widget Fetch
// ============================================================
// Alur:
//  1. Boot → load EEPROM (ssid, pass, dev, apiBase)
//  2. SSID kosong / WiFi gagal → AP "SmartHome-XXXX" + portal
//  3. WiFi OK → POST /api/devices/auth    → MQTT credentials
//  4. WiFi OK → GET  /api/devices/{dev}/widgets?lite=1 → widget map
//  5. Connect MQTT, subscribe control/# , publish sensors
//  6. Loop: poll Nano I2C 5s, publish MQTT
//
// Widget Key Convention (di Laravel):
//   toggle1, toggle2      → mapped ke Relay 1, Relay 2
//   voltage, current, power, energy, frequency, power_factor
//   batt1, batt2
//   temp1, temp2, temp3, temp4
//
// Reset: tahan FLASH (GPIO0) > 5 detik
// ============================================================

#include <ArduinoJson.h>
#include <EEPROM.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WebServer.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <Wire.h>

// ── Pins ─────────────────────────────────────────────────────
#define NANO_ADDR 0x08
#define I2C_SDA 4     // D2
#define I2C_SCL 5     // D1
#define RELAY1_PIN 14 // D5  Active-LOW
#define RELAY2_PIN 12 // D6  Active-LOW
#define LED_PIN 2     // D4  Active-LOW
#define BTN_PIN 0     // GPIO0 = FLASH button

// ── I2C ───────────────────────────────────────────────────
// Nano slave addr 0x09, 100kHz
// Buffer 48 byte:
//  [0- 5] batt1  "***.**" (6)
//  [6-11] batt2  "***.**" (6)
//  [12-16] temp1 "**.**"  (5)
//  [17-21] temp2 "**.**"  (5)
//  [22-26] temp3 "**.**"  (5)
//  [27-31] temp4 "**.**"  (5)
//  [32-37] voltAC"***.**" (6)
//  [38-42] currAC"**.**"  (5)
//  [43-47] powAC "*****"  (5)
#define NANO_ADDR   0x09
#define NANO_BUFLEN 48

// ── EEPROM ───────────────────────────────────────────────────
#define EEPROM_SIZE 512
// CATATAN: Ganti MAGIC setiap kali struct Config berubah layout
// agar EEPROM lama di-reset otomatis dan user diarahkan ke portal
const uint32_t MAGIC = 0x53484F4E; // "SHON" v2.2

struct Config {
  uint32_t magic;
  char ssid[64];
  char pass[64];
  char dev[32];
  char api[96]; // https://your-server.com
  bool relay[2];
};
Config cfg;

// ── Runtime MQTT (dari API, tidak disimpan EEPROM) ───────────
struct MqttInfo {
  char host[64];
  int port = 8883;
  char user[64];
  char pass[64];
  char base[96]; // users/{uid}/devices/{code}
  bool ok = false;
};
MqttInfo mi;

// ── Widget Toggle Map (dari API) ──────────────────────────────
// ESP hanya perlu track widget toggle untuk relay binding.
// Widget sensor lain (gauge, text) dikenali dari key-nya langsung.
#define MAX_TOGGLES 4
struct ToggleEntry {
  char key[32]; // e.g. "toggle1"
  int relayIdx; // 0 = Relay1, 1 = Relay2, -1 = tidak terpasang
};
ToggleEntry toggles[MAX_TOGGLES];
int toggleCount = 0;

// Widget gauge → sensor mapping (sorted by key)
#define MAX_GAUGES 16
struct GaugeEntry { char key[32]; };
GaugeEntry gauges[MAX_GAUGES];
int gaugeCount = 0;

// ── Sensors ───────────────────────────────────────────────────
struct Sensors {
  float b1 = 0, b2 = 0;             // Battery 1, 2
  float t1 = 0, t2 = 0, t3 = 0, t4 = 0; // DS18B20 Temp 1-4
  float v  = 0, i  = 0, p  = 0;    // PZEM: voltage, current, power
};
Sensors sv;

// Nilai sensor sebagai array untuk mapping ke gauge widgets
// Urutan: [b1, b2, t1, t2, t3, t4, v, i, p]
float sensorArr[9] = {};

// ── Objects ───────────────────────────────────────────────────
ESP8266WebServer server(80);
BearSSL::WiFiClientSecure tlsMqtt; // persistent for MQTT
BearSSL::WiFiClientSecure tlsHttp; // reused per HTTP request
PubSubClient mqtt(tlsMqtt);

// ── Flags / Timers ────────────────────────────────────────────
bool apMode = false;
bool authOk = false;
bool ledState = false;

unsigned long tPoll = 0, tMqttRetry = 0, tBlink = 0, tApiRetry = 0, tBtn = 0;

// ════════════════════════════════════════════════════════════
// RELAY HELPER  (defined early — called from setup)
// ════════════════════════════════════════════════════════════

void applyRelay(int idx, bool on) {
  int pin = (idx == 0) ? RELAY1_PIN : RELAY2_PIN;
  digitalWrite(pin, on ? LOW : HIGH);
  cfg.relay[idx] = on;
}

// ════════════════════════════════════════════════════════════
// EEPROM
// ════════════════════════════════════════════════════════════

void cfgSave() {
  EEPROM.put(0, cfg);
  EEPROM.commit();
}

void cfgLoad() {
  EEPROM.begin(EEPROM_SIZE);
  EEPROM.get(0, cfg);
  if (cfg.magic != MAGIC) {
    cfg.magic = MAGIC;
    strlcpy(cfg.ssid, "", sizeof(cfg.ssid));
    strlcpy(cfg.pass, "", sizeof(cfg.pass));
    strlcpy(cfg.dev, "DEV_XXXXXXXX", sizeof(cfg.dev));
    strlcpy(cfg.api, "https://your-server.com", sizeof(cfg.api));
    cfg.relay[0] = cfg.relay[1] = false;
    cfgSave();
    Serial.println(F("[CFG] Defaults written"));
  } else {
    Serial.println(F("[CFG] Loaded"));
  }
}

void factoryReset() {
  Serial.println(F("[CFG] FACTORY RESET"));
  cfg.magic = 0;
  cfgSave();
  for (int i = 0; i < 10; i++) {
    digitalWrite(LED_PIN, LOW);
    delay(80);
    digitalWrite(LED_PIN, HIGH);
    delay(80);
  }
  ESP.restart();
}

// ════════════════════════════════════════════════════════════
// HTTP HELPER — POST / GET JSON
// ════════════════════════════════════════════════════════════

// Returns HTTP status code, fills resp with response body
int httpGet(const String &url, String &resp) {
  tlsHttp.setInsecure();
  HTTPClient http;
  if (!http.begin(tlsHttp, url))
    return -1;
  http.setTimeout(8000);
  http.addHeader("Accept", "application/json");
  int code = http.GET();
  resp = http.getString();
  http.end();
  return code;
}

int httpPost(const String &url, const String &body, String &resp) {
  tlsHttp.setInsecure();
  HTTPClient http;
  if (!http.begin(tlsHttp, url))
    return -1;
  http.setTimeout(8000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  int code = http.POST(body);
  resp = http.getString();
  http.end();
  return code;
}

// ════════════════════════════════════════════════════════════
// API — Auth (ambil MQTT credentials)
// ════════════════════════════════════════════════════════════

bool apiAuth() {
  String url = String(cfg.api) + "/api/devices/auth";
  String body = "{\"device_code\":\"" + String(cfg.dev) + "\"}";
  String resp;

  Serial.print(F("[API] Auth → "));
  Serial.println(url);
  int code = httpPost(url, body, resp);
  Serial.printf("[API] HTTP %d\n", code);

  if (code != 200) {
    Serial.println("[API] ❌ " + resp.substring(0, 100));
    return false;
  }

  StaticJsonDocument<1024> doc;
  if (deserializeJson(doc, resp) || !doc["success"].as<bool>()) {
    Serial.println(F("[API] ❌ JSON / success=false"));
    return false;
  }

  strlcpy(mi.host, doc["mqtt"]["host"] | "x", sizeof(mi.host));
  strlcpy(mi.user, doc["mqtt"]["username"] | "", sizeof(mi.user));
  strlcpy(mi.pass, doc["mqtt"]["password"] | "", sizeof(mi.pass));
  strlcpy(mi.base, doc["topics"]["base"] | "", sizeof(mi.base));
  mi.port = doc["mqtt"]["port"] | 8883;
  mi.ok = true;

  Serial.printf("[API] ✅  host=%s port=%d base=%s\n", mi.host, mi.port,
                mi.base);
  return true;
}

// ════════════════════════════════════════════════════════════
// API — Fetch Widgets (build toggle relay map)
// ════════════════════════════════════════════════════════════
// GET /api/devices/{dev}/widgets?lite=1
// Response: { "widgets": { "toggle1":{type:"toggle",...}, ... } }

void apiFetchWidgets() {
  String url = String(cfg.api) + "/api/devices/" + cfg.dev + "/widgets?lite=1";
  String resp;

  Serial.print(F("[API] Widgets → "));
  Serial.println(url);
  int code = httpGet(url, resp);
  if (code != 200) {
    Serial.printf("[API] Widget fetch fail %d\n", code);
    return;
  }

  // Use large doc for widget array — lite mode is smaller
  DynamicJsonDocument doc(4096);
  if (deserializeJson(doc, resp)) {
    Serial.println(F("[API] Widget JSON error"));
    return;
  }

  JsonObject widgets = doc["widgets"].as<JsonObject>();
  toggleCount = 0;
  gaugeCount  = 0;
  int relayIdx = 0;

  for (JsonPair kv : widgets) {
    const char *key  = kv.key().c_str();
    const char *type = kv.value()["type"] | "";

    if (strcmp(type, "toggle") == 0 && toggleCount < MAX_TOGGLES) {
      strlcpy(toggles[toggleCount].key, key, sizeof(toggles[0].key));
      toggles[toggleCount].relayIdx = (relayIdx < 2) ? relayIdx : -1;
      Serial.printf("[Widget] %s \u2192 relay%d\n", key, relayIdx);
      toggleCount++;
      relayIdx++;
    }
    else if (gaugeCount < MAX_GAUGES) {
      // Collect semua non-toggle widget (gauge, text, dll) untuk sensor mapping
      strlcpy(gauges[gaugeCount].key, key, sizeof(gauges[0].key));
      gaugeCount++;
    }
  }

  // Sort gauge keys alphabetically (bubble sort) agar urutan deterministik
  for (int a = 0; a < gaugeCount - 1; a++) {
    for (int b = a + 1; b < gaugeCount; b++) {
      if (strcmp(gauges[a].key, gauges[b].key) > 0) {
        GaugeEntry tmp = gauges[a];
        gauges[a] = gauges[b];
        gauges[b] = tmp;
      }
    }
  }

  // Sensor names by index (urutan buffer Nano)
  const char *sName[] = {"batt1","batt2","temp1","temp2",
                          "temp3","temp4","voltage","current","power"};
  Serial.printf("[Widget] %d toggle(s), %d gauge(s) mapped\n",
                toggleCount, gaugeCount);
  for (int i = 0; i < gaugeCount && i < 9; i++) {
    Serial.printf("  %s \u2192 sensor[%d]=%s\n", gauges[i].key, i, sName[i]);
  }
}

// ════════════════════════════════════════════════════════════
// MQTT
// ════════════════════════════════════════════════════════════

void mqttPublish(const char *key, float val, int dec) {
  char topic[96], payload[16];
  snprintf(topic, sizeof(topic), "%s/sensors/%s", mi.base, key);
  if (dec == 0)
    snprintf(payload, sizeof(payload), "%.0f", val);
  else if (dec == 1)
    snprintf(payload, sizeof(payload), "%.1f", val);
  else
    snprintf(payload, sizeof(payload), "%.2f", val);
  mqtt.publish(topic, payload, true);
}

void publishSensors() {
  if (!mqtt.connected() || !mi.ok)
    return;

  // Update sensor value array dari struct sv
  sensorArr[0] = sv.b1;  // gauge1 = batt1
  sensorArr[1] = sv.b2;  // gauge2 = batt2
  sensorArr[2] = sv.t1;  // gauge3 = temp1
  sensorArr[3] = sv.t2;  // gauge4 = temp2
  sensorArr[4] = sv.t3;  // gauge5 = temp3
  sensorArr[5] = sv.t4;  // gauge6 = temp4
  sensorArr[6] = sv.v;   // gauge7 = voltage
  sensorArr[7] = sv.i;   // gauge8 = current
  sensorArr[8] = sv.p;   // gauge9 = power

  // Publish gauge widgets by sorted index order
  for (int i = 0; i < gaugeCount && i < 9; i++) {
    mqttPublish(gauges[i].key, sensorArr[i], 2);
  }

  // Publish relay state ke toggle widget keys
  for (int i = 0; i < toggleCount; i++) {
    int ri = toggles[i].relayIdx;
    if (ri >= 0 && ri < 2) {
      char topic[96];
      snprintf(topic, sizeof(topic), "%s/sensors/%s", mi.base, toggles[i].key);
      mqtt.publish(topic, cfg.relay[ri] ? "1" : "0", true);
    }
  }

  Serial.println(F("[MQTT] ↑ published"));
}


void mqttCallback(char *topic, byte *payload, unsigned int len) {
  String msg, t;
  for (unsigned int i = 0; i < len; i++)
    msg += (char)payload[i];
  t = String(topic);
  Serial.println("📥 " + t + " = " + msg);

  // Check each mapped toggle widget
  for (int i = 0; i < toggleCount; i++) {
    String ctrl = String(mi.base) + "/control/" + toggles[i].key;
    if (t == ctrl) {
      int ri = toggles[i].relayIdx;
      if (ri < 0 || ri >= 2)
        return;
      bool on = (msg == "1" || msg == "true" || msg == "on");
      applyRelay(ri, on);
      cfgSave();
      // ACK
      char ack[96];
      snprintf(ack, sizeof(ack), "%s/sensors/%s", mi.base, toggles[i].key);
      mqtt.publish(ack, on ? "1" : "0", true);
      return;
    }
  }
}

bool mqttConnect() {
  if (!mi.ok || WiFi.status() != WL_CONNECTED)
    return false;
  tlsMqtt.setInsecure();
  tlsMqtt.setBufferSizes(1024, 512);
  mqtt.setServer(mi.host, mi.port);
  mqtt.setCallback(mqttCallback);
  mqtt.setBufferSize(512);
  mqtt.setKeepAlive(30);

  String cid = "ESP-" + String(cfg.dev) + "-" + String(millis() % 9999);
  if (!mqtt.connect(cid.c_str(), mi.user, mi.pass)) {
    Serial.printf("[MQTT] ❌ rc=%d\n", mqtt.state());
    return false;
  }

  Serial.println(F("[MQTT] ✅ Connected"));
  String ctrl = String(mi.base) + "/control/#";
  mqtt.subscribe(ctrl.c_str(), 1);
  Serial.println("  Sub: " + ctrl);
  return true;
}

// ════════════════════════════════════════════════════════════
// I2C Nano — Simple requestFrom() saja, tanpa CMD register
// ════════════════════════════════════════════════════════════

// void pollNano() {
//   uint8_t n = 0;

//   // Retry 3x (sama seperti kode test yang sudah terbukti bekerja)
//   for (int attempt = 0; attempt < 3; attempt++) {
//     n = Wire.requestFrom((uint8_t)NANO_ADDR, (uint8_t)NANO_BUFLEN);
//     if (n == NANO_BUFLEN)
//       break;
//     delay(10);
//   }

//   if (n != NANO_BUFLEN) {
//     Serial.printf("[Nano] \u26a0 Hanya %d byte (butuh %d) \u2014 cek kabel & pullup\n", n, NANO_BUFLEN);
//     while (Wire.available())
//       Wire.read();
//     return;
//   }

//   char buf[NANO_BUFLEN + 1];
//   for (int i = 0; i < NANO_BUFLEN; i++)
//     buf[i] = Wire.read();
//   buf[NANO_BUFLEN] = '\0';

//   Serial.printf("[Nano] RAW: [%s]\n", buf);

//   // Parse by byte position
//   char tmp[8];
//   auto sub = [&](int from, int len) -> float {
//     memcpy(tmp, buf + from, len);
//     tmp[len] = '\0';
//     return atof(tmp);
//   };

//   //  [0-5]=b1  [6-11]=b2  [12-16]=t1 [17-21]=t2
//   // [22-26]=t3 [27-31]=t4 [32-37]=v  [38-42]=i  [43-47]=p
//   sv.b1 = sub(0,  6);
//   sv.b2 = sub(6,  6);
//   sv.t1 = sub(12, 5);
//   sv.t2 = sub(17, 5);
//   sv.t3 = sub(22, 5);
//   sv.t4 = sub(27, 5);
//   sv.v  = sub(32, 6);
//   sv.i  = sub(38, 5);
//   sv.p  = sub(43, 5);

//   Serial.printf("[Nano] B=%.2f/%.2f | T=%.1f/%.1f/%.1f/%.1f | V=%.1f I=%.2f P=%.0f\n",
//                 sv.b1, sv.b2, sv.t1, sv.t2, sv.t3, sv.t4, sv.v, sv.i, sv.p);
// }
void pollNano() {
  char buf[49];
  uint8_t n;

  // =========================
  // PART 1 (0 - 31)
  // =========================
  Wire.beginTransmission(NANO_ADDR);
  Wire.write(0); // offset
  Wire.endTransmission();

  delayMicroseconds(200);

  n = Wire.requestFrom((uint8_t)NANO_ADDR, (uint8_t)32);
  if (n != 32) {
    Serial.printf("[Nano] ❌ Part1 gagal (%d)\n", n);
    while (Wire.available()) Wire.read();
    return;
  }

  for (int i = 0; i < 32; i++) {
    buf[i] = Wire.read();
  }

  // =========================
  // PART 2 (32 - 47)
  // =========================
  Wire.beginTransmission(NANO_ADDR);
  Wire.write(32); // offset
  Wire.endTransmission();

  delayMicroseconds(200);

  n = Wire.requestFrom((uint8_t)NANO_ADDR, (uint8_t)16);
  if (n != 16) {
    Serial.printf("[Nano] ❌ Part2 gagal (%d)\n", n);
    while (Wire.available()) Wire.read();
    return;
  }

  for (int i = 0; i < 16; i++) {
    buf[32 + i] = Wire.read();
  }

  buf[48] = '\0';

  Serial.printf("[Nano] RAW: [%s]\n", buf);

  // =========================
  // PARSE
  // =========================
  char tmp[8];
  auto sub = [&](int from, int len) -> float {
    memcpy(tmp, buf + from, len);
    tmp[len] = '\0';
    return atof(tmp);
  };

  sv.b1 = sub(0,  6);
  sv.b2 = sub(6,  6);
  sv.t1 = sub(12, 5);
  sv.t2 = sub(17, 5);
  sv.t3 = sub(22, 5);
  sv.t4 = sub(27, 5);
  sv.v  = sub(32, 6);
  sv.i  = sub(38, 5);
  sv.p  = sub(43, 5);

  Serial.printf("[Nano] ✅ B=%.2f/%.2f | T=%.1f/%.1f/%.1f/%.1f | V=%.1f I=%.2f P=%.0f\n",
                sv.b1, sv.b2, sv.t1, sv.t2, sv.t3, sv.t4, sv.v, sv.i, sv.p);
}
// ════════════════════════════════════════════════════════════
// WIFI
// ════════════════════════════════════════════════════════════

void startAP() {
  apMode = authOk = false;
  uint8_t mac[6];
  WiFi.macAddress(mac);
  char apSsid[24];
  snprintf(apSsid, sizeof(apSsid), "SmartHome-%02X%02X", mac[4], mac[5]);

  WiFi.mode(WIFI_AP_STA);
  WiFi.softAPConfig(IPAddress(192, 168, 4, 1), IPAddress(192, 168, 4, 1),
                    IPAddress(255, 255, 255, 0));
  WiFi.softAP(apSsid, "");

  Serial.printf("[AP] SSID: %s\n[AP] Portal: http://192.168.4.1\n", apSsid);
  if (strlen(cfg.ssid))
    WiFi.begin(cfg.ssid, cfg.pass); // try STA too
}

bool connectWiFi() {
  Serial.print(F("[WiFi] Connecting to "));
  Serial.println(cfg.ssid);
  WiFi.mode(WIFI_STA);
  WiFi.begin(cfg.ssid, cfg.pass);
  for (int i = 0; i < 40 && WiFi.status() != WL_CONNECTED; i++) {
    delay(500);
    Serial.print('.');
    ledState = !ledState;
    digitalWrite(LED_PIN, ledState ? LOW : HIGH);
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("[WiFi] ✅ IP=%s RSSI=%ddBm\n",
                  WiFi.localIP().toString().c_str(), WiFi.RSSI());
    return true;
  }
  Serial.println(F("[WiFi] ❌ Failed"));
  return false;
}

// ════════════════════════════════════════════════════════════
// WEB PORTAL
// ════════════════════════════════════════════════════════════

String portalHtml() {
  bool w = WiFi.status() == WL_CONNECTED;
  bool m = mqtt.connected();
  String h =
      "<!DOCTYPE html><html lang='id'><head>"
      "<meta charset='UTF-8'><meta name='viewport' "
      "content='width=device-width,initial-scale=1'>"
      "<title>SmartHome Setup</title>"
      "<style>"
      "*{box-sizing:border-box;margin:0;padding:0}"
      "body{font-family:'Segoe UI',sans-serif;background:#0f1117;color:#e2e8f0}"
      ".hd{background:linear-gradient(135deg,#1a2744,#0f1831);padding:16px;"
      "text-align:center;border-bottom:1px solid #2d3a52}"
      ".hd h1{font-size:1.2rem;color:#60a5fa;font-weight:700}"
      ".sb{display:flex;gap:8px;padding:8px "
      "14px;background:#151b30;flex-wrap:wrap}"
      ".bx{padding:3px "
      "10px;border-radius:20px;font-size:.77rem;font-weight:600}"
      ".ok{background:rgba(16,185,129,.15);color:#34d399;border:1px solid "
      "rgba(16,185,129,.3)}"
      ".er{background:rgba(239,68,68,.15);color:#f87171;border:1px solid "
      "rgba(239,68,68,.3)}"
      ".wd{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid "
      "rgba(245,158,11,.3)}"
      ".w{max-width:460px;margin:18px auto;padding:0 12px 40px}"
      ".cd{background:#1a2035;border:1px solid "
      "#2a3550;border-radius:12px;padding:18px;margin-bottom:14px}"
      ".cd "
      "h2{font-size:.82rem;font-weight:700;color:#94a3b8;text-transform:"
      "uppercase;letter-spacing:1px;margin-bottom:14px}"
      ".fg{margin-bottom:12px}"
      "label{display:block;font-size:.76rem;color:#94a3b8;font-weight:600;"
      "margin-bottom:4px;text-transform:uppercase}"
      "input{width:100%;background:#0f1831;border:1px solid "
      "#2a3550;border-radius:8px;color:#e2e8f0;padding:8px "
      "11px;font-size:.87rem}"
      "input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px "
      "rgba(59,130,246,.15)}"
      ".hint{font-size:.7rem;color:#4a5568;margin-top:2px}"
      ".btn{width:100%;padding:11px;border-radius:10px;border:none;font-size:."
      "9rem;font-weight:700;cursor:pointer;margin-top:4px;transition:all .2s}"
      ".p{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-"
      "shadow:0 4px 15px rgba(37,99,235,.3)}"
      ".d{background:rgba(239,68,68,.1);color:#f87171;border:1px solid "
      "rgba(239,68,68,.25);margin-top:8px}"
      ".nets{max-height:170px;overflow-y:auto;margin-bottom:10px}"
      ".net{display:flex;justify-content:space-between;padding:8px "
      "11px;border-radius:7px;cursor:pointer;margin-bottom:3px;border:1px "
      "solid transparent;transition:all .15s}"
      ".net:hover{background:rgba(59,130,246,.1);border-color:rgba(59,130,246,."
      "2)}"
      ".sel{background:rgba(59,130,246,.15)!important;border-color:rgba(59,130,"
      "246,.4)!important}"
      ".info{background:rgba(59,130,246,.08);border:1px solid "
      "rgba(59,130,246,.2);border-radius:9px;padding:10px "
      "12px;margin-bottom:12px;font-size:.78rem;color:#93c5fd;line-height:1.5}"
      ".toast{display:none;position:fixed;top:14px;right:14px;border-radius:"
      "10px;padding:11px 16px;font-weight:600;font-size:.85rem;z-index:9999}"
      "</style></head><body>"
      "<div class='hd'><h1>⚡ SmartHome Gateway</h1></div>"
      "<div class='sb'>"
      "<span class='bx ";
  h += w ? "ok" : "er";
  h += "'>WiFi: ";
  h += w ? WiFi.SSID() + " (" + WiFi.localIP().toString() + ")" : "Off";
  h += "</span><span class='bx ";
  h += m ? "ok" : "er";
  h += "'>MQTT: ";
  h += m ? "OK" : "Off";
  h += "</span><span class='bx ";
  h += authOk ? "ok" : "wd";
  h += "'>Auth: ";
  h += authOk ? "OK" : "Pending";
  h +=
      "</span></div><div class='w'>"
      "<div class='info'>ℹ️ Isi SSID, password, Device Code &amp; URL "
      "server.<br>"
      "MQTT credentials diambil <b>otomatis</b> via API.</div>"
      "<div class='cd'><h2>📶 WiFi</h2>"
      "<div class='nets' id='ns'><p "
      "style='color:#475569;padding:10px;text-align:center'>Memuat...</p></div>"
      "<div class='fg'><label>SSID</label>"
      "<input id='sid' value='";
  h += cfg.ssid;
  h += "' placeholder='Nama WiFi'></div>"
       "<div class='fg'><label>Password</label>"
       "<input type='password' id='pw' value='";
  h += cfg.pass;
  h += "' placeholder='Password'></div></div>"
       "<div class='cd'><h2>🔑 Device</h2>"
       "<div class='fg'><label>Device Code</label>"
       "<input id='dv' value='";
  h += cfg.dev;
  h += "' placeholder='DEV_XXXXXXXX'>"
       "<p class='hint'>Dari Dashboard Laravel → Settings</p></div>"
       "<div class='fg'><label>URL Server</label>"
       "<input id='ap' value='";
  h += cfg.api;
  h +=
      "' placeholder='https://your-server.com'>"
      "<p class='hint'>Tanpa trailing slash</p></div></div>"
      "<button class='btn p' onclick='sv()'>💾 Simpan &amp; Restart</button>"
      "<button class='btn d' onclick='rs()'>🔄 Factory Reset</button>"
      "</div><div class='toast' id='t'></div>"
      "<script>"
      "function toast(m,ok){"
      "const t=document.getElementById('t');"
      "t.textContent=m;t.style.display='block';"
      "t.style.background=ok?'#0a2a1a':'#2a0a0a';"
      "t.style.border='1px solid '+(ok?'#10b981':'#ef4444');"
      "t.style.color=ok?'#34d399':'#f87171';"
      "setTimeout(()=>t.style.display='none',4000);}"
      "fetch('/scan').then(r=>r.json()).then(ns=>{"
      "const el=document.getElementById('ns');"
      "if(!ns.length){el.innerHTML='<p "
      "style=\"color:#475569;padding:10px;text-align:center\">Tidak ada "
      "jaringan</p>';return;}"
      "el.innerHTML=ns.map(n=>`<div class=\"net\" "
      "onclick=\"pick('${n.ssid.replace(/'/g,\"\\\\'\")}',this)\">"
      "<div><b>${n.ssid||'hidden'}</b><br><small>${n.enc?'🔒':'🔓'} "
      "${n.rssi}dBm</small></div>"
      "<span>${n.rssi>-60?'▮▮▮':n.rssi>-75?'▮▮▯':'▮▯▯'}</span></"
      "div>`).join('');}).catch(()=>{"
      "document.getElementById('ns').innerHTML='<p "
      "style=\"color:#64748b;padding:8px;text-align:center\">Scan "
      "gagal</p>';});"
      "function pick(s,el){"
      "document.getElementById('sid').value=s;"
      "document.querySelectorAll('.net').forEach(e=>e.classList.remove('sel'));"
      "el.classList.add('sel');"
      "document.getElementById('pw').focus();}"
      "function sv(){"
      "const s=document.getElementById('sid').value.trim(),"
      "d=document.getElementById('dv').value.trim(),"
      "a=document.getElementById('ap').value.trim();"
      "if(!s)return toast('❌ SSID kosong',false);"
      "if(!d)return toast('❌ Device Code kosong',false);"
      "if(!a)return toast('❌ URL Server kosong',false);"
      "fetch('/save',{method:'POST',headers:{'Content-Type':'application/"
      "json'},"
      "body:JSON.stringify({ssid:s,pass:document.getElementById('pw').value,"
      "dev:d,api:a})})"
      ".then(r=>r.json()).then(r=>toast(r.ok?'✅ Tersimpan! Restart...':'❌ "
      "'+r.msg,r.ok))"
      ".catch(()=>toast('❌ Gagal',false));}"
      "function rs(){"
      "if(confirm('Factory Reset?'))fetch('/reset',{method:'POST'})"
      ".then(()=>toast('🔄 Reset...',true));}"
      "</script></body></html>";
  return h;
}

void webRoot() { server.send(200, "text/html", portalHtml()); }

void webScan() {
  int n = WiFi.scanNetworks();
  String j = "[";
  for (int i = 0; i < n; i++) {
    if (i)
      j += ",";
    String s = WiFi.SSID(i);
    s.replace("\"", "\\\"");
    j += "{\"ssid\":\"" + s + "\",\"rssi\":" + WiFi.RSSI(i) + ",\"enc\":" +
         (WiFi.encryptionType(i) != ENC_TYPE_NONE ? "true" : "false") + "}";
  }
  WiFi.scanDelete();
  server.send(200, "application/json", j + "]");
}

void webSave() {
  if (!server.hasArg("plain")) {
    server.send(400, "application/json", "{\"ok\":false,\"msg\":\"No body\"}");
    return;
  }
  StaticJsonDocument<512> d;
  if (deserializeJson(d, server.arg("plain"))) {
    server.send(400, "application/json", "{\"ok\":false,\"msg\":\"JSON\"}");
    return;
  }
  strlcpy(cfg.ssid, d["ssid"] | cfg.ssid, sizeof(cfg.ssid));
  strlcpy(cfg.pass, d["pass"] | cfg.pass, sizeof(cfg.pass));
  strlcpy(cfg.dev, d["dev"] | cfg.dev, sizeof(cfg.dev));
  strlcpy(cfg.api, d["api"] | cfg.api, sizeof(cfg.api));
  cfgSave();
  server.send(200, "application/json", "{\"ok\":true}");
  delay(1200);
  ESP.restart();
}

void webStatus() {
  StaticJsonDocument<256> d;
  d["wifi"] = WiFi.status() == WL_CONNECTED;
  d["mqtt"] = mqtt.connected();
  d["auth"] = authOk;
  d["dev"] = cfg.dev;
  d["ip"] = WiFi.localIP().toString();
  d["uptime"] = millis() / 1000;
  d["pzem_v"] = sv.v;
  d["pzem_p"] = sv.p;
  d["b1"] = sv.b1;
  String out;
  serializeJson(d, out);
  server.send(200, "application/json", out);
}

void webReset() {
  server.send(200, "application/json", "{\"ok\":true}");
  delay(300);
  factoryReset();
}

void setupWebServer() {
  server.on("/", HTTP_GET, webRoot);
  server.on("/scan", HTTP_GET, webScan);
  server.on("/save", HTTP_POST, webSave);
  server.on("/status", HTTP_GET, webStatus);
  server.on("/reset", HTTP_POST, webReset);
  server.onNotFound([] {
    server.sendHeader("Location", "/");
    server.send(302, "", "");
  });
  server.begin();
  Serial.println(F("[Web] Up on port 80"));
}

// ════════════════════════════════════════════════════════════
// FACTORY RESET BUTTON
// ════════════════════════════════════════════════════════════

void checkBtn() {
  if (digitalRead(BTN_PIN) == LOW) {
    if (!tBtn)
      tBtn = millis();
    else if (millis() - tBtn > 5000)
      factoryReset();
  } else
    tBtn = 0;
}

// ════════════════════════════════════════════════════════════
// SETUP & LOOP
// ════════════════════════════════════════════════════════════

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println(F("\n╔════════════════════════════════╗"));
  Serial.println(F("║  SmartHome Gateway  v2.2       ║"));
  Serial.println(F("╚════════════════════════════════╝"));

  pinMode(RELAY1_PIN, OUTPUT);
  pinMode(RELAY2_PIN, OUTPUT);
  pinMode(LED_PIN, OUTPUT);
  pinMode(BTN_PIN, INPUT);
  digitalWrite(LED_PIN, HIGH);

  cfgLoad();
  applyRelay(0, cfg.relay[0]); // restore last state
  applyRelay(1, cfg.relay[1]);

  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(100000); // 100kHz — stabil, sama dengan Nano
  Wire.setClockStretchLimit(200000);
  Serial.println(F("[I2C] 100kHz master"));

  // Scan I2C bus untuk verifikasi Nano terdeteksi
  Serial.print(F("[I2C] Scan: "));
  byte found = 0;
  for (byte addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
      Serial.printf("0x%02X ", addr);
      found++;
    }
  }
  if (!found)
    Serial.print(F("tidak ada device!"));
  Serial.println();

  setupWebServer();

  if (!strlen(cfg.ssid)) {
    startAP();
  } else if (connectWiFi()) {
    // 1. Auth → MQTT credentials
    if (apiAuth()) {
      authOk = true;
      // 2. Fetch widgets → build relay map
      apiFetchWidgets();
      // 3. Connect MQTT
      if (mqttConnect())
        publishSensors();
    } else {
      Serial.println(F("[API] Auth gagal — retry tiap 30s"));
    }
    pollNano();
  } else {
    startAP();
  }

  Serial.println(F("✅ Ready. FLASH 5s = factory reset."));
}

void loop() {
  unsigned long now = millis();

  server.handleClient();
  checkBtn();
  if (mqtt.connected())
    mqtt.loop();

  // MQTT reconnect
  if (!apMode && authOk && !mqtt.connected() && now - tMqttRetry > 15000) {
    tMqttRetry = now;
    if (WiFi.status() == WL_CONNECTED) {
      tlsMqtt.stop();
      delay(10);
      mqttConnect();
    } else
      WiFi.reconnect();
  }

  // API auth retry (jika awal gagal)
  if (!apMode && !authOk && WiFi.status() == WL_CONNECTED &&
      now - tApiRetry > 30000) {
    tApiRetry = now;
    if (apiAuth()) {
      authOk = true;
      apiFetchWidgets();
      mqttConnect();
    }
  }

  // Poll Nano + publish tiap 5s
  if (now - tPoll > 5000) {
    tPoll = now;
    pollNano();
    publishSensors();
  }

  // LED heartbeat: cepat=AP, lambat=normal
  if (now - tBlink > (apMode ? 250 : 1000)) {
    tBlink = now;
    ledState = !ledState;
    digitalWrite(LED_PIN, ledState ? LOW : HIGH);
  }

  yield();
}
