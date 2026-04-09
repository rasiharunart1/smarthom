// ============================================================
// esp8266_gateway.ino
// ESP8266 — WiFi / MQTT Gateway
// ============================================================
// Fungsi:
//   - I2C Master: polling Arduino Nano (slave 0x08) setiap 5 detik
//   - Parse data sensor (PZEM, temperatura, baterai)
//   - Publish ke MQTT Laravel: users/{id}/devices/{code}/sensors/{key}
//   - Subscribe MQTT untuk kontrol 2 relay toggle
//   - LED heartbeat (GPIO2 = LED builtin, active LOW)
//
// Wiring ESP8266 ↔ Arduino Nano:
//   ESP8266 D1 (GPIO5/SCL) ──── Nano A5 (SCL)
//   ESP8266 D2 (GPIO4/SDA) ──── Nano A4 (SDA)
//   GND ──────────────────────── GND (WAJIB hubungkan)
//   Pullup 4.7kΩ SDA dan SCL ke 3.3V (ESP8266 side)
//
// Relay:
//   D5 (GPIO14) → Relay 1  (toggle1)
//   D6 (GPIO12) → Relay 2  (toggle2)
//   Active-LOW relay module
//
// Dependencies (Library Manager):
//   - ESPAsyncTCP           by dvarrel
//   - ESPAsyncWebServer     by lacamera
//   - PubSubClient          by Nick O'Leary
//   - ArduinoJson           by Benoit Blanchon (v7.x)
//   - WiFiClientSecureBearSSL (built-in ESP8266 core)
// ============================================================

#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <Wire.h>
#include <PubSubClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <ArduinoJson.h>
#include <EEPROM.h>

// ── I2C ─────────────────────────────────────────────────────
#define NANO_I2C_ADDR     0x08
#define I2C_SDA           4   // D2
#define I2C_SCL           5   // D1

// ── Relay Pins ───────────────────────────────────────────────
#define RELAY1_PIN        14  // D5
#define RELAY2_PIN        12  // D6
#define LED_PIN           2   // D4 – LED built-in (active LOW)

// ── I2C CMD Register ────────────────────────────────────────
#define CMD_PZEM_VI       0x01  // Voltage + Current
#define CMD_PZEM_PE       0x02  // Power + Energy
#define CMD_PZEM_FPF      0x03  // Frequency + PF
#define CMD_BATT          0x10  // Battery 1 + 2
#define CMD_TEMP12        0x20  // Temp 1 + 2
#define CMD_TEMP34        0x21  // Temp 3 + 4

// ── EEPROM Config ────────────────────────────────────────────
struct EepromConfig {
  uint32_t magic;
  char ssid[64];
  char pass[64];
  char api[128];     // https://your-server/api/devices
  char dev[32];      // Device code: DEV_XXXXXXXX
  char mqttHost[64];
  int  mqttPort;
  char mqttUser[64];
  char mqttPass[64];
  int  userId;
  char relay1[0];    // diperhitungkan via padding; pakai relay state array
  bool relay[2];     // relay1, relay2 last state
};

EepromConfig cfg;
const uint32_t EEPROM_MAGIC = 0x4E414E4F; // "NANO"

void loadConfig() {
  EEPROM.begin(512);
  EEPROM.get(0, cfg);
  if (cfg.magic != EEPROM_MAGIC) {
    cfg.magic = EEPROM_MAGIC;
    strlcpy(cfg.ssid,     "",                            sizeof(cfg.ssid));
    strlcpy(cfg.pass,     "",                            sizeof(cfg.pass));
    strlcpy(cfg.api,      "https://your-server.com/api/devices", sizeof(cfg.api));
    strlcpy(cfg.dev,      "DEV_XXXXXXXX",               sizeof(cfg.dev));
    strlcpy(cfg.mqttHost, "your-mqtt-host",             sizeof(cfg.mqttHost));
    cfg.mqttPort = 8883;
    strlcpy(cfg.mqttUser, "",                            sizeof(cfg.mqttUser));
    strlcpy(cfg.mqttPass, "",                            sizeof(cfg.mqttPass));
    cfg.userId   = 1;
    cfg.relay[0] = false;
    cfg.relay[1] = false;
    EEPROM.put(0, cfg);
    EEPROM.commit();
    Serial.println(F("⚠ EEPROM: format default. Update via Serial."));
  } else {
    Serial.println(F("✅ EEPROM config loaded"));
  }
}

void saveConfig() {
  EEPROM.put(0, cfg);
  EEPROM.commit();
}

// ── Sensor Values ────────────────────────────────────────────
struct SensorValues {
  float pzem_v   = 0, pzem_i   = 0;
  float pzem_p   = 0, pzem_e   = 0;
  float pzem_f   = 0, pzem_pf  = 0;
  float batt1    = 0, batt2    = 0;
  float temp[4]  = {0, 0, 0, 0};
  bool  nanoOk   = false;
};
SensorValues sv;

// ── MQTT ─────────────────────────────────────────────────────
BearSSL::WiFiClientSecure mqttSecure;
PubSubClient mqtt(mqttSecure);
bool mqttReady = false;
unsigned long lastMqttRetry = 0;
unsigned long lastPollNano  = 0;
unsigned long lastHeartbeat = 0;
bool ledState = false;

// ── Forward Declarations ─────────────────────────────────────
void pollNano();
void publishSensors();
void connectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int len);
bool i2cRequest(byte cmd, char* buf, uint8_t bufLen);
void parseVI(const char* s);
void parsePE(const char* s);
void parseFPF(const char* s);
void parseBatt(const char* s);
void parseTemp12(const char* s);
void parseTemp34(const char* s);
void applyRelay(int idx, bool on);
String mqttBase();

// ── I2C Request Helper ───────────────────────────────────────
bool i2cRequest(byte cmd, char* buf, uint8_t bufLen) {
  // (1) Kirim CMD ke Nano
  Wire.beginTransmission(NANO_I2C_ADDR);
  Wire.write(cmd);
  byte err = Wire.endTransmission();
  if (err != 0) {
    Serial.printf("I2C TX err cmd=0x%02X code=%d\n", cmd, err);
    return false;
  }
  delay(5); // beri waktu Nano build response

  // (2) Request data dari Nano (max 31 char + null = 32 byte)
  uint8_t n = Wire.requestFrom((uint8_t)NANO_I2C_ADDR, (uint8_t)31);
  if (n == 0) {
    Serial.printf("I2C RX: no bytes cmd=0x%02X\n", cmd);
    return false;
  }

  int idx = 0;
  while (Wire.available() && idx < (bufLen - 1)) {
    char c = Wire.read();
    if (c == '\0') break;
    buf[idx++] = c;
  }
  buf[idx] = '\0';
  // Drain leftover bytes
  while (Wire.available()) Wire.read();

  Serial.printf("I2C RX [0x%02X] → \"%s\"\n", cmd, buf);
  return true;
}

// ── Parse Helpers ────────────────────────────────────────────
// Format "V:230.1,I:1.23"
void parseVI(const char* s) {
  // sscanf langsung parse key=value CSV
  sscanf(s, "V:%f,I:%f", &sv.pzem_v, &sv.pzem_i);
}
void parsePE(const char* s)   { sscanf(s, "P:%f,E:%f",   &sv.pzem_p,  &sv.pzem_e);  }
void parseFPF(const char* s)  { sscanf(s, "F:%f,PF:%f",  &sv.pzem_f,  &sv.pzem_pf); }
void parseBatt(const char* s) { sscanf(s, "B1:%f,B2:%f", &sv.batt1,   &sv.batt2);   }
void parseTemp12(const char* s){ sscanf(s, "T1:%f,T2:%f", &sv.temp[0], &sv.temp[1]); }
void parseTemp34(const char* s){ sscanf(s, "T3:%f,T4:%f", &sv.temp[2], &sv.temp[3]); }

// ── Poll Nano via I2C ────────────────────────────────────────
void pollNano() {
  char buf[32];
  sv.nanoOk = true;

  // Baca semua 6 register secara berurutan
  if (i2cRequest(CMD_PZEM_VI,  buf, sizeof(buf))) parseVI(buf);    else sv.nanoOk = false;
  delay(10);
  if (i2cRequest(CMD_PZEM_PE,  buf, sizeof(buf))) parsePE(buf);
  delay(10);
  if (i2cRequest(CMD_PZEM_FPF, buf, sizeof(buf))) parseFPF(buf);
  delay(10);
  if (i2cRequest(CMD_BATT,     buf, sizeof(buf))) parseBatt(buf);
  delay(10);
  if (i2cRequest(CMD_TEMP12,   buf, sizeof(buf))) parseTemp12(buf);
  delay(10);
  if (i2cRequest(CMD_TEMP34,   buf, sizeof(buf))) parseTemp34(buf);

  Serial.println(F("─── Sensor Snapshot ───────────────"));
  Serial.printf("  PZEM: %.1fV %.2fA %.1fW %.2fkWh\n",
                sv.pzem_v, sv.pzem_i, sv.pzem_p, sv.pzem_e);
  Serial.printf("  PZEM: %.1fHz PF=%.2f\n", sv.pzem_f, sv.pzem_pf);
  Serial.printf("  Batt: B1=%.2fV B2=%.2fV\n", sv.batt1, sv.batt2);
  Serial.printf("  Temp: %.1f %.1f %.1f %.1f °C\n",
                sv.temp[0], sv.temp[1], sv.temp[2], sv.temp[3]);
  Serial.println(F("───────────────────────────────────"));
}

// ── MQTT Base Topic ──────────────────────────────────────────
String mqttBase() {
  return "users/" + String(cfg.userId) +
         "/devices/" + String(cfg.dev);
}

// ── Publish Sensors ke MQTT ──────────────────────────────────
void publishSensors() {
  if (!mqtt.connected()) return;
  String base = mqttBase() + "/sensors/";

  // Helper lambda-like macro
  auto pub = [&](const char* key, float val, int decimals) {
    char topic[96], payload[16];
    snprintf(topic,   sizeof(topic),   "%s%s", base.c_str(), key);
    if (decimals == 0)
      snprintf(payload, sizeof(payload), "%.0f", val);
    else if (decimals == 1)
      snprintf(payload, sizeof(payload), "%.1f", val);
    else
      snprintf(payload, sizeof(payload), "%.2f", val);
    mqtt.publish(topic, payload, true); // retain=true
    Serial.printf("  ↑ %s = %s\n", topic, payload);
  };

  Serial.println(F("📤 Publishing sensors..."));
  pub("voltage",      sv.pzem_v,     1);
  pub("current",      sv.pzem_i,     2);
  pub("power",        sv.pzem_p,     1);
  pub("energy",       sv.pzem_e,     2);
  pub("frequency",    sv.pzem_f,     1);
  pub("power_factor", sv.pzem_pf,    2);
  pub("batt1",        sv.batt1,      2);
  pub("batt2",        sv.batt2,      2);
  pub("temp1",        sv.temp[0],    1);
  pub("temp2",        sv.temp[1],    1);
  pub("temp3",        sv.temp[2],    1);
  pub("temp4",        sv.temp[3],    1);
  // Relay state
  pub("toggle1",      cfg.relay[0] ? 1.0f : 0.0f, 0);
  pub("toggle2",      cfg.relay[1] ? 1.0f : 0.0f, 0);
}

// ── Relay Control ────────────────────────────────────────────
void applyRelay(int idx, bool on) {
  int pin = (idx == 0) ? RELAY1_PIN : RELAY2_PIN;
  digitalWrite(pin, on ? LOW : HIGH); // Active LOW
  cfg.relay[idx] = on;
  Serial.printf("  Relay%d → %s\n", idx + 1, on ? "ON" : "OFF");
}

// ── MQTT Callback (kontrol relay dari Laravel) ───────────────
void mqttCallback(char* topic, byte* payload, unsigned int len) {
  String msg = "";
  for (unsigned int i = 0; i < len; i++) msg += (char)payload[i];

  String topicStr  = String(topic);
  String baseCtrl  = mqttBase() + "/control/";

  Serial.println("📥 MQTT ← " + topicStr + " = " + msg);

  if (topicStr == baseCtrl + "toggle1") {
    bool on = (msg == "1" || msg == "true" || msg == "on");
    applyRelay(0, on);
    saveConfig();
    // ACK publish ke sensors topic
    String ack = mqttBase() + "/sensors/toggle1";
    mqtt.publish(ack.c_str(), on ? "1" : "0", true);
  }
  else if (topicStr == baseCtrl + "toggle2") {
    bool on = (msg == "1" || msg == "true" || msg == "on");
    applyRelay(1, on);
    saveConfig();
    String ack = mqttBase() + "/sensors/toggle2";
    mqtt.publish(ack.c_str(), on ? "1" : "0", true);
  }
}

// ── Connect MQTT ─────────────────────────────────────────────
void connectMQTT() {
  Serial.printf("📡 MQTT → %s:%d\n", cfg.mqttHost, cfg.mqttPort);

  mqttSecure.setInsecure();
  mqttSecure.setBufferSizes(1024, 512);
  mqtt.setServer(cfg.mqttHost, cfg.mqttPort);
  mqtt.setCallback(mqttCallback);
  mqtt.setBufferSize(512);
  mqtt.setKeepAlive(30);

  String cid = "ESP-GW-" + String(cfg.dev) + "-" + String(millis() % 9999);

  if (mqtt.connect(cid.c_str(), cfg.mqttUser, cfg.mqttPass)) {
    Serial.println(F("✅ MQTT Connected!"));
    // Subscribe relay control topics
    String ctrl = mqttBase() + "/control/";
    mqtt.subscribe((ctrl + "toggle1").c_str(), 1);
    mqtt.subscribe((ctrl + "toggle2").c_str(), 1);
    Serial.println("  Sub: " + ctrl + "toggle1");
    Serial.println("  Sub: " + ctrl + "toggle2");
    mqttReady = true;
    // Publish initial state
    publishSensors();
  } else {
    Serial.printf("  ❌ MQTT fail rc=%d\n", mqtt.state());
    mqttReady = false;
  }
}

// ── Serial Config Helper ─────────────────────────────────────
// Kirim config baru via Serial Monitor:
// Format: SET ssid:MyWiFi,pass:password123,dev:DEV_XXX,host:mqtthost,port:8883,user:u,pass:p,uid:1
void handleSerialConfig() {
  if (!Serial.available()) return;
  String line = Serial.readStringUntil('\n');
  line.trim();
  if (!line.startsWith("SET ")) return;
  line = line.substring(4);

  // Parse key:value,key:value
  int s = 0;
  while (s < (int)line.length()) {
    int c = line.indexOf(',', s);
    String pair = (c < 0) ? line.substring(s) : line.substring(s, c);
    s = (c < 0) ? line.length() : c + 1;
    int eq = pair.indexOf(':');
    if (eq < 0) continue;
    String k = pair.substring(0, eq);
    String v = pair.substring(eq + 1);
    k.trim(); v.trim();

    if      (k == "ssid") strlcpy(cfg.ssid,     v.c_str(), sizeof(cfg.ssid));
    else if (k == "pass")  strlcpy(cfg.pass,     v.c_str(), sizeof(cfg.pass));
    else if (k == "api")   strlcpy(cfg.api,      v.c_str(), sizeof(cfg.api));
    else if (k == "dev")   strlcpy(cfg.dev,      v.c_str(), sizeof(cfg.dev));
    else if (k == "host")  strlcpy(cfg.mqttHost, v.c_str(), sizeof(cfg.mqttHost));
    else if (k == "port")  cfg.mqttPort = v.toInt();
    else if (k == "user")  strlcpy(cfg.mqttUser, v.c_str(), sizeof(cfg.mqttUser));
    else if (k == "mpass") strlcpy(cfg.mqttPass, v.c_str(), sizeof(cfg.mqttPass));
    else if (k == "uid")   cfg.userId   = v.toInt();
  }
  saveConfig();
  Serial.println(F("✅ Config saved. Restarting..."));
  delay(500);
  ESP.restart();
}

// ── Setup ────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println(F("\n╔═══════════════════════════════════════╗"));
  Serial.println(F("║  ESP8266 Gateway — I2C + MQTT         ║"));
  Serial.println(F("║  PZEM-004T | DS18B20 | Batt | Relay   ║"));
  Serial.println(F("╚═══════════════════════════════════════╝\n"));

  loadConfig();

  // Relay pins
  pinMode(RELAY1_PIN, OUTPUT);
  pinMode(RELAY2_PIN, OUTPUT);
  applyRelay(0, cfg.relay[0]); // restore last state
  applyRelay(1, cfg.relay[1]);

  // LED
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, HIGH); // OFF

  // I2C Master (ESP8266 is master)
  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(100000); // 100kHz untuk kompatibilitas Nano
  Serial.println(F("✅ I2C Master init (SDA=D2, SCL=D1)"));

  // WiFi
  WiFi.mode(WIFI_STA);
  Serial.print(F("📶 WiFi: "));
  Serial.print(cfg.ssid);
  WiFi.begin(cfg.ssid, cfg.pass);
  int t = 0;
  while (WiFi.status() != WL_CONNECTED && t < 40) {
    delay(500);
    Serial.print(".");
    t++;
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("  IP: " + WiFi.localIP().toString());
    Serial.println("  RSSI: " + String(WiFi.RSSI()) + " dBm");
    connectMQTT();
  } else {
    Serial.println(F("  ❌ WiFi gagal — cek SSID/pass via SET command"));
  }

  // Baca awal sensor
  pollNano();

  Serial.println(F("\n✅ Gateway siap! Polling Nano setiap 5 detik."));
  Serial.println(F("Kirim config: SET ssid:...,pass:...,dev:...,host:...,port:8883,user:...,mpass:...,uid:1\n"));
}

// ── Loop ─────────────────────────────────────────────────────
void loop() {
  unsigned long now = millis();

  // Serial config
  handleSerialConfig();

  // MQTT loop
  if (mqtt.connected()) {
    mqtt.loop();
  }

  // MQTT reconnect setiap 15 detik
  if (!mqtt.connected() && (now - lastMqttRetry >= 15000)) {
    lastMqttRetry = now;
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println(F("🔄 MQTT retrying..."));
      mqttSecure.stop();
      delay(10);
      connectMQTT();
    } else {
      Serial.println(F("⚠ WiFi putus — skip MQTT retry"));
      WiFi.reconnect();
    }
  }

  // Poll Nano + Publish MQTT setiap 5 detik
  if (now - lastPollNano >= 5000) {
    lastPollNano = now;
    pollNano();
    if (mqtt.connected()) publishSensors();
  }

  // LED heartbeat setiap 1 detik
  if (now - lastHeartbeat >= 1000) {
    lastHeartbeat = now;
    ledState = !ledState;
    digitalWrite(LED_PIN, ledState ? LOW : HIGH); // active LOW
  }

  yield();
}
