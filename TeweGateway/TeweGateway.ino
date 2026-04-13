// ================================================================
// TeweGateway.ino — ESP8266 IoT Gateway
// ================================================================
// Fungsi:
//   1. Baca 32-byte I2C dari Arduino Nano (master = ESP8266)
//   2. AP Mode: Config portal (SSID, pass, MQTT, device code)
//   3. STA Mode: Kirim data ke Laravel via MQTT
//      - Topic: users/{userId}/devices/{deviceCode}/sensors/{key}
//
// Library yang dibutuhkan:
//   - PubSubClient
//   - ArduinoJson (v7+)
//   - ESP8266WiFi, ESP8266WebServer, LittleFS (built-in ESP8266 core)
// ================================================================

#include <Wire.h>
#include <ESP8266WiFi.h>
#include <ESP8266WebServer.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <LittleFS.h>
#include <BearSSL.h>

// ================================================================
// KONSTANTA
// ================================================================
#define I2C_SLAVE_ADDR   0x09
#define I2C_DATA_LEN     32

#define AP_SSID_PREFIX   "TeweGW-"
#define AP_PASS          "12345678"
#define CONFIG_FILE      "/gw_config.json"
#define CONFIG_BUTTON    0   // GPIO0 (FLASH button) → tekan 3 detik untuk reset ke config
#define STATUS_LED       2   // GPIO2 (onboard LED, active LOW)

// Interval (ms)
#define I2C_READ_INTERVAL   1500
#define MQTT_RETRY_INTERVAL 10000
#define HEARTBEAT_INTERVAL  60000
#define INTERNET_CHECK_INT  30000

// ================================================================
// STRUKTUR CONFIG
// ================================================================
struct GwConfig {
    char wifiSSID[64];
    char wifiPass[64];
    char mqttHost[128];
    int  mqttPort;
    char mqttUser[64];
    char mqttPass[64];
    char deviceCode[32];
    char apiBase[128];   // mis. https://smarthom.example.com/api/device
    int  userId;
};

// ================================================================
// SENSOR DATA (parsed dari I2C)
// ================================================================
struct SensorData {
    float batt1;
    float batt2;
    float voltAC;
    float currentAC;
    float powerAC;
    int   temp1, temp2, temp3, temp4;
    bool  valid;
};

// ================================================================
// GLOBALS
// ================================================================
GwConfig cfg;
SensorData sensorData;

ESP8266WebServer  server(80);
WiFiClientSecure  wifiClientSecure;
WiFiClient        wifiClientPlain;
PubSubClient      mqtt(wifiClientSecure);

bool configMode     = false;
bool mqttConfigured = false;

unsigned long lastI2CRead     = 0;
unsigned long lastMqttRetry   = 0;
unsigned long lastHeartbeat   = 0;
unsigned long lastInternetChk = 0;
unsigned long btnPressStart   = 0;

String apSSID;

// ================================================================
// PROTOTYPES
// ================================================================
void loadConfig();
void saveConfig();
void setDefaults();
void startConfigPortal();
void startNormalMode();
void handleRoot();
void handleSave();
void handleStatus();
void readI2C();
bool parseI2CData(const char* buf, SensorData& d);
void publishSensors();
void mqttReconnect();
void mqttCallback(char* topic, byte* payload, unsigned int len);
bool authFromServer();
void blinkLED(int times, int ms = 100);
void ledOn();
void ledOff();

// ================================================================
// SETUP
// ================================================================
void setup() {
    Serial.begin(115200);
    delay(200);

    Serial.println(F("\n==========================================="));
    Serial.println(F(" TeweGateway — ESP8266 I2C→MQTT Bridge"));
    Serial.println(F("===========================================\n"));

    // LED & Button
    pinMode(STATUS_LED, OUTPUT);
    ledOff();
    pinMode(CONFIG_BUTTON, INPUT_PULLUP);

    // I2C sebagai master
    Wire.begin(4, 5); // SDA=GPIO4, SCL=GPIO5 (default D2, D1)
    Wire.setClock(400000);

    // LittleFS
    if (!LittleFS.begin()) {
        Serial.println(F("⚠ LittleFS format..."));
        LittleFS.format();
        LittleFS.begin();
    }

    // Load config
    loadConfig();

    // Cek tombol saat boot (tahan = config mode)
    if (digitalRead(CONFIG_BUTTON) == LOW) {
        Serial.println(F("🔧 Tombol ditekan saat boot → Config Portal"));
        delay(50);
        startConfigPortal();
    } else if (strlen(cfg.wifiSSID) == 0) {
        Serial.println(F("⚙ Belum ada config WiFi → Config Portal"));
        startConfigPortal();
    } else {
        startNormalMode();
    }
}

// ================================================================
// LOOP
// ================================================================
void loop() {
    server.handleClient();
    unsigned long now = millis();

    // ── Tombol reset config (tekan 3 detik) ──────────────────
    if (digitalRead(CONFIG_BUTTON) == LOW) {
        if (btnPressStart == 0) btnPressStart = now;
        if (now - btnPressStart > 3000) {
            Serial.println(F("🔧 Reset ke Config Portal..."));
            blinkLED(5, 100);
            startConfigPortal();
        }
    } else {
        btnPressStart = 0;
    }

    if (configMode) return; // Hanya handle server saat config mode

    // ── Normal mode ───────────────────────────────────────────

    // Baca I2C
    if (now - lastI2CRead >= I2C_READ_INTERVAL) {
        lastI2CRead = now;
        readI2C();
    }

    // MQTT loop
    if (mqttConfigured) {
        if (mqtt.connected()) {
            mqtt.loop();
        } else if (now - lastMqttRetry >= MQTT_RETRY_INTERVAL) {
            lastMqttRetry = now;
            mqttReconnect();
        }
    }

    // Heartbeat
    if (mqttConfigured && mqtt.connected() && now - lastHeartbeat >= HEARTBEAT_INTERVAL) {
        lastHeartbeat = now;
        String topic = "users/" + String(cfg.userId) + "/devices/" + String(cfg.deviceCode) + "/heartbeat";
        String payload = "{\"status\":\"online\",\"uptime\":" + String(millis()/1000) +
                         ",\"heap\":" + String(ESP.getFreeHeap()) +
                         ",\"rssi\":" + String(WiFi.RSSI()) + "}";
        mqtt.publish(topic.c_str(), payload.c_str(), true);
        Serial.println("💓 Heartbeat terkirim");
    }

    // Cek WiFi reconnect
    if (WiFi.status() != WL_CONNECTED && now - lastInternetChk >= INTERNET_CHECK_INT) {
        lastInternetChk = now;
        Serial.println(F("📡 Reconnecting WiFi..."));
        WiFi.begin(cfg.wifiSSID, cfg.wifiPass);
    }

    yield();
}

// ================================================================
// LOAD / SAVE CONFIG
// ================================================================
void setDefaults() {
    memset(&cfg, 0, sizeof(cfg));
    cfg.mqttPort = 8883;
    cfg.userId   = 0;
    strncpy(cfg.mqttHost, "broker.hivemq.com", 127);
}

void loadConfig() {
    setDefaults();
    if (!LittleFS.exists(CONFIG_FILE)) {
        Serial.println(F("  Config tidak ditemukan, pakai default"));
        return;
    }
    File f = LittleFS.open(CONFIG_FILE, "r");
    if (!f) return;

    JsonDocument doc;
    if (deserializeJson(doc, f) == DeserializationError::Ok) {
        strncpy(cfg.wifiSSID,   doc["wifi_ssid"]   | "", 63);
        strncpy(cfg.wifiPass,   doc["wifi_pass"]   | "", 63);
        strncpy(cfg.mqttHost,   doc["mqtt_host"]   | "broker.hivemq.com", 127);
        cfg.mqttPort =           doc["mqtt_port"]   | 8883;
        strncpy(cfg.mqttUser,   doc["mqtt_user"]   | "", 63);
        strncpy(cfg.mqttPass,   doc["mqtt_pass"]   | "", 63);
        strncpy(cfg.deviceCode, doc["device_code"] | "", 31);
        strncpy(cfg.apiBase,    doc["api_base"]    | "", 127);
        cfg.userId =             doc["user_id"]     | 0;
        Serial.println(F("  Config loaded ✓"));
        Serial.printf("  WiFi: %s | Device: %s | MQTT: %s:%d\n",
                      cfg.wifiSSID, cfg.deviceCode, cfg.mqttHost, cfg.mqttPort);
    }
    f.close();
}

void saveConfig() {
    File f = LittleFS.open(CONFIG_FILE, "w");
    if (!f) { Serial.println(F("❌ Gagal simpan config")); return; }

    JsonDocument doc;
    doc["wifi_ssid"]   = cfg.wifiSSID;
    doc["wifi_pass"]   = cfg.wifiPass;
    doc["mqtt_host"]   = cfg.mqttHost;
    doc["mqtt_port"]   = cfg.mqttPort;
    doc["mqtt_user"]   = cfg.mqttUser;
    doc["mqtt_pass"]   = cfg.mqttPass;
    doc["device_code"] = cfg.deviceCode;
    doc["api_base"]    = cfg.apiBase;
    doc["user_id"]     = cfg.userId;
    serializeJson(doc, f);
    f.close();
    Serial.println(F("  Config tersimpan ✓"));
}

// ================================================================
// CONFIG PORTAL (AP MODE)
// ================================================================
void startConfigPortal() {
    configMode = true;
    String mac = WiFi.macAddress();
    mac.replace(":", "");
    apSSID = String(AP_SSID_PREFIX) + mac.substring(8);

    WiFi.mode(WIFI_AP);
    WiFi.softAP(apSSID.c_str(), AP_PASS);

    Serial.println(F("\n📶 CONFIG PORTAL AKTIF"));
    Serial.println("   SSID : " + apSSID);
    Serial.println("   Pass : " + String(AP_PASS));
    Serial.println("   IP   : " + WiFi.softAPIP().toString());

    // Routes
    server.on("/",       HTTP_GET,  handleRoot);
    server.on("/save",   HTTP_POST, handleSave);
    server.on("/status", HTTP_GET,  handleStatus);
    server.onNotFound([]() { server.sendHeader("Location", "/"); server.send(302); });
    server.begin();

    // Blink LED tanda config mode
    blinkLED(3, 200);
    ledOn(); // LED menyala terus saat config mode
}

void handleRoot() {
    String html = F("<!DOCTYPE html><html lang='id'><head>"
        "<meta charset='UTF-8'>"
        "<meta name='viewport' content='width=device-width,initial-scale=1'>"
        "<title>TeweGateway Setup</title>"
        "<style>"
        "*{margin:0;padding:0;box-sizing:border-box}"
        "body{font-family:'Segoe UI',sans-serif;background:#0f1117;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}"
        ".card{background:#1a1f2e;border:1px solid #2a3044;border-radius:16px;padding:28px;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,.4)}"
        ".logo{text-align:center;margin-bottom:24px}"
        ".logo h1{font-size:1.4rem;background:linear-gradient(135deg,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent}"
        ".logo p{font-size:.8rem;color:#718096;margin-top:4px}"
        "label{display:block;font-size:.82rem;color:#94a3b8;margin-bottom:4px;margin-top:14px;font-weight:500}"
        "input{width:100%;padding:10px 14px;background:#0f1117;border:1px solid #2a3044;border-radius:10px;color:#e2e8f0;font-size:.9rem;outline:none;transition:.2s}"
        "input:focus{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.15)}"
        ".section{margin-top:20px;padding-top:16px;border-top:1px solid #2a3044}"
        ".section-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#10b981;margin-bottom:4px}"
        "button{width:100%;margin-top:24px;padding:12px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:10px;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;transition:.2s}"
        "button:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(16,185,129,.3)}"
        ".info{font-size:.75rem;color:#718096;text-align:center;margin-top:16px}"
        ".chip{display:inline-block;background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:600}"
        "</style></head><body>"
        "<div class='card'>"
        "<div class='logo'>"
        "<h1>⚡ TeweGateway</h1>"
        "<p>ESP8266 IoT Bridge Setup</p>"
        "</div>"
        "<form method='POST' action='/save'>"

        "<div class='section'>"
        "<div class='section-title'>📶 WiFi</div>"
        "<label>SSID</label>"
        "<input name='ws' value='");
    html += cfg.wifiSSID;
    html += F("' placeholder='Nama WiFi'>"
        "<label>Password</label>"
        "<input name='wp' type='password' value='");
    html += cfg.wifiPass;
    html += F("' placeholder='Password WiFi'>"
        "</div>"

        "<div class='section'>"
        "<div class='section-title'>🌐 Server Laravel</div>"
        "<label>API Base URL</label>"
        "<input name='ab' value='");
    html += cfg.apiBase;
    html += F("' placeholder='https://domain.com/api/device'>"
        "<label>Device Code</label>"
        "<input name='dc' value='");
    html += cfg.deviceCode;
    html += F("' placeholder='ABC123'>"
        "</div>"

        "<div class='section'>"
        "<div class='section-title'>📡 MQTT Broker</div>"
        "<label>Host</label>"
        "<input name='mh' value='");
    html += cfg.mqttHost;
    html += F("' placeholder='broker.hivemq.com'>"
        "<label>Port</label>"
        "<input name='mp' type='number' value='");
    html += cfg.mqttPort;
    html += F("' placeholder='8883'>"
        "<label>Username</label>"
        "<input name='mu' value='");
    html += cfg.mqttUser;
    html += F("' placeholder='(opsional)'>"
        "<label>Password</label>"
        "<input name='mps' type='password' value='");
    html += cfg.mqttPass;
    html += F("' placeholder='(opsional)'>"
        "</div>"

        "<button type='submit'>💾 Simpan & Restart</button>"
        "</form>"
        "<p class='info'>Firmware: TeweGateway v1.0 &nbsp;|&nbsp; <span class='chip'>I2C→MQTT</span></p>"
        "</div>"
        "</body></html>");

    server.send(200, "text/html", html);
}

void handleSave() {
    if (server.hasArg("ws"))  strncpy(cfg.wifiSSID,   server.arg("ws").c_str(),  63);
    if (server.hasArg("wp"))  strncpy(cfg.wifiPass,   server.arg("wp").c_str(),  63);
    if (server.hasArg("mh"))  strncpy(cfg.mqttHost,   server.arg("mh").c_str(), 127);
    if (server.hasArg("mp"))  cfg.mqttPort =           server.arg("mp").toInt();
    if (server.hasArg("mu"))  strncpy(cfg.mqttUser,   server.arg("mu").c_str(),  63);
    if (server.hasArg("mps")) strncpy(cfg.mqttPass,   server.arg("mps").c_str(), 63);
    if (server.hasArg("dc"))  strncpy(cfg.deviceCode, server.arg("dc").c_str(),  31);
    if (server.hasArg("ab"))  strncpy(cfg.apiBase,    server.arg("ab").c_str(), 127);

    saveConfig();

    server.send(200, "text/html",
        F("<!DOCTYPE html><html><head><meta charset='UTF-8'>"
          "<meta name='viewport' content='width=device-width,initial-scale=1'>"
          "<style>body{font-family:sans-serif;background:#0f1117;color:#e2e8f0;"
          "display:flex;align-items:center;justify-content:center;height:100vh;margin:0}"
          ".box{text-align:center;padding:32px;background:#1a1f2e;border-radius:16px;border:1px solid #2a3044}"
          "h2{color:#10b981;margin-bottom:12px}p{color:#94a3b8;font-size:.9rem}</style></head>"
          "<body><div class='box'><h2>✅ Tersimpan!</h2>"
          "<p>ESP8266 akan restart dalam 3 detik...</p></div></body></html>"));

    delay(3000);
    ESP.restart();
}

void handleStatus() {
    JsonDocument doc;
    doc["mode"]        = configMode ? "config" : "normal";
    doc["wifi"]        = WiFi.status() == WL_CONNECTED;
    doc["ip"]          = WiFi.localIP().toString();
    doc["ap_ip"]       = WiFi.softAPIP().toString();
    doc["mqtt"]        = mqtt.connected();
    doc["device_code"] = cfg.deviceCode;
    doc["uptime"]      = millis() / 1000;
    doc["heap"]        = ESP.getFreeHeap();

    if (sensorData.valid) {
        doc["batt1"]      = sensorData.batt1;
        doc["batt2"]      = sensorData.batt2;
        doc["volt_ac"]    = sensorData.voltAC;
        doc["current_ac"] = sensorData.currentAC;
        doc["power_ac"]   = sensorData.powerAC;
        doc["temp1"]      = sensorData.temp1;
        doc["temp2"]      = sensorData.temp2;
        doc["temp3"]      = sensorData.temp3;
        doc["temp4"]      = sensorData.temp4;
    }

    String out;
    serializeJson(doc, out);
    server.send(200, "application/json", out);
}

// ================================================================
// NORMAL MODE (STA + MQTT)
// ================================================================
void startNormalMode() {
    configMode = false;

    // WiFi AP+STA (AP untuk monitoring lokal tetap aktif)
    apSSID = String(AP_SSID_PREFIX) + cfg.deviceCode;
    WiFi.mode(WIFI_AP_STA);
    WiFi.softAP(apSSID.c_str(), AP_PASS);
    Serial.println("📶 AP: " + apSSID + " → " + WiFi.softAPIP().toString());

    // Connect STA
    Serial.printf("🔗 WiFi: %s", cfg.wifiSSID);
    WiFi.begin(cfg.wifiSSID, cfg.wifiPass);
    int tries = 0;
    while (WiFi.status() != WL_CONNECTED && tries < 30) {
        delay(500);
        Serial.print(".");
        tries++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("   STA IP : " + WiFi.localIP().toString());
        Serial.println("   RSSI   : " + String(WiFi.RSSI()) + " dBm");

        // Auth dari server → dapat userId
        if (strlen(cfg.apiBase) > 0 && cfg.userId == 0) {
            authFromServer();
        }

        // MQTT setup
        wifiClientSecure.setInsecure();
        mqtt.setServer(cfg.mqttHost, cfg.mqttPort);
        mqtt.setCallback(mqttCallback);
        mqtt.setKeepAlive(60);
        mqtt.setBufferSize(512);
        mqttReconnect();
        mqttConfigured = true;

    } else {
        Serial.println(F("   WiFi gagal — AP-only mode"));
    }

    // Web server lokal (status page)
    server.on("/",       HTTP_GET, handleRoot);
    server.on("/status", HTTP_GET, handleStatus);
    server.onNotFound([]() { server.sendHeader("Location", "/status"); server.send(302); });
    server.begin();

    ledOff();
    blinkLED(2, 300);
    Serial.println(F("\n✅ TeweGateway siap!"));
    Serial.printf("   Device: %s | User: %d\n\n", cfg.deviceCode, cfg.userId);
}

// ================================================================
// I2C READ
// ================================================================
void readI2C() {
    char buf[I2C_DATA_LEN + 1];
    memset(buf, 0, sizeof(buf));

    Wire.requestFrom((uint8_t)I2C_SLAVE_ADDR, (uint8_t)I2C_DATA_LEN);

    int idx = 0;
    unsigned long t = millis();
    while (Wire.available() && idx < I2C_DATA_LEN) {
        buf[idx++] = Wire.read();
    }

    if (idx < I2C_DATA_LEN) {
        Serial.printf("⚠ I2C: hanya %d/%d byte diterima\n", idx, I2C_DATA_LEN);
        sensorData.valid = false;
        return;
    }
    buf[I2C_DATA_LEN] = '\0';

    if (parseI2CData(buf, sensorData)) {
        sensorData.valid = true;
        Serial.printf("📊 B1=%.2f B2=%.2f V=%.2f A=%.2f W=%.2f T=%d,%d,%d,%d\n",
                      sensorData.batt1, sensorData.batt2,
                      sensorData.voltAC, sensorData.currentAC, sensorData.powerAC,
                      sensorData.temp1, sensorData.temp2, sensorData.temp3, sensorData.temp4);
        publishSensors();
    }
}

// ================================================================
// PARSE 32-BYTE BUFFER
// Format: [Batt1:4][Batt2:4][voltAC:6][currentAC:5][powerAC:5][t1:2][t2:2][t3:2][t4:2]
// ================================================================
bool parseI2CData(const char* buf, SensorData& d) {
    char tmp[10];

    // Batt1 (0..3)
    memcpy(tmp, buf + 0, 4); tmp[4] = '\0';
    d.batt1 = atof(tmp);

    // Batt2 (4..7)
    memcpy(tmp, buf + 4, 4); tmp[4] = '\0';
    d.batt2 = atof(tmp);

    // voltAC (8..13)
    memcpy(tmp, buf + 8,  6); tmp[6] = '\0';
    d.voltAC = atof(tmp);

    // currentAC (14..18)
    memcpy(tmp, buf + 14, 5); tmp[5] = '\0';
    d.currentAC = atof(tmp);

    // powerAC (19..23)
    memcpy(tmp, buf + 19, 5); tmp[5] = '\0';
    d.powerAC = atof(tmp);

    // suhu t1..t4 (24..31)
    memcpy(tmp, buf + 24, 2); tmp[2] = '\0'; d.temp1 = atoi(tmp);
    memcpy(tmp, buf + 26, 2); tmp[2] = '\0'; d.temp2 = atoi(tmp);
    memcpy(tmp, buf + 28, 2); tmp[2] = '\0'; d.temp3 = atoi(tmp);
    memcpy(tmp, buf + 30, 2); tmp[2] = '\0'; d.temp4 = atoi(tmp);

    return true;
}

// ================================================================
// PUBLISH MQTT
// ================================================================
void publishSensors() {
    if (!mqtt.connected() || !sensorData.valid) return;
    if (cfg.userId == 0 || strlen(cfg.deviceCode) == 0) {
        Serial.println(F("⚠ userId/deviceCode belum diset, skip publish"));
        return;
    }

    String base = "users/" + String(cfg.userId) + "/devices/" + String(cfg.deviceCode) + "/sensors/";

    char val[16];

    auto pub = [&](const char* key, const char* v) {
        String topic = base + key;
        mqtt.publish(topic.c_str(), v, true); // retain=true
        delay(10);
    };

    dtostrf(sensorData.batt1,     4, 2, val); pub("batt1",      val);
    dtostrf(sensorData.batt2,     4, 2, val); pub("batt2",      val);
    dtostrf(sensorData.voltAC,    6, 2, val); pub("volt_ac",    val);
    dtostrf(sensorData.currentAC, 5, 2, val); pub("current_ac", val);
    dtostrf(sensorData.powerAC,   5, 2, val); pub("power_ac",   val);
    sprintf(val, "%d", sensorData.temp1);     pub("temp1",      val);
    sprintf(val, "%d", sensorData.temp2);     pub("temp2",      val);
    sprintf(val, "%d", sensorData.temp3);     pub("temp3",      val);
    sprintf(val, "%d", sensorData.temp4);     pub("temp4",      val);
}

// ================================================================
// MQTT
// ================================================================
void mqttReconnect() {
    if (mqtt.connected() || WiFi.status() != WL_CONNECTED) return;

    String cid = "TeweGW-" + String(cfg.deviceCode) + "-" + String(millis() % 10000);
    Serial.printf("📡 MQTT connect ke %s:%d ...\n", cfg.mqttHost, cfg.mqttPort);

    bool ok;
    if (strlen(cfg.mqttUser) > 0) {
        ok = mqtt.connect(cid.c_str(), cfg.mqttUser, cfg.mqttPass);
    } else {
        ok = mqtt.connect(cid.c_str());
    }

    if (ok) {
        Serial.println(F("   MQTT terhubung ✓"));
        blinkLED(2, 100);

        // Subscribe ke control topic
        String ctrlTopic = "users/" + String(cfg.userId) + "/devices/" + String(cfg.deviceCode) + "/control/#";
        mqtt.subscribe(ctrlTopic.c_str());
        Serial.println("   SUB: " + ctrlTopic);

        // Publish online status
        String sTopic = "users/" + String(cfg.userId) + "/devices/" + String(cfg.deviceCode) + "/status";
        mqtt.publish(sTopic.c_str(), "online", true);
    } else {
        Serial.println("   MQTT gagal, rc=" + String(mqtt.state()));
    }
}

void mqttCallback(char* topic, byte* payload, unsigned int len) {
    String msg;
    for (unsigned int i = 0; i < len; i++) msg += (char)payload[i];
    Serial.printf("[MQTT-IN] %s = %s\n", topic, msg.c_str());
    // Tambahkan logika kontrol output di sini jika diperlukan
}

// ================================================================
// AUTH DARI SERVER LARAVEL
// ================================================================
bool authFromServer() {
    Serial.println(F("🔐 Auth ke server Laravel..."));
    BearSSL::WiFiClientSecure sc;
    sc.setInsecure();

    WiFiClient plain;
    HTTPClient http;

    String url = String(cfg.apiBase) + "/auth";
    bool isHttps = url.startsWith("https");

    bool ok = isHttps ? http.begin(sc, url) : http.begin(plain, url);
    if (!ok) {
        Serial.println(F("   Gagal begin HTTP"));
        return false;
    }

    http.addHeader("Content-Type", "application/json");
    http.setTimeout(10000);

    JsonDocument req;
    req["device_code"] = cfg.deviceCode;
    String body;
    serializeJson(req, body);

    int code = http.POST(body);
    if (code == 200) {
        String resp = http.getString();
        JsonDocument res;
        if (deserializeJson(res, resp) == DeserializationError::Ok && res["success"].as<bool>()) {
            cfg.userId = res["device"]["user_id"];
            // Update MQTT config dari server jika ada
            if (res["mqtt"]["host"].as<String>().length() > 0)
                strncpy(cfg.mqttHost, res["mqtt"]["host"].as<String>().c_str(), 127);
            if (res["mqtt"]["port"].as<int>() > 0)
                cfg.mqttPort = res["mqtt"]["port"];
            if (res["mqtt"]["username"].as<String>().length() > 0)
                strncpy(cfg.mqttUser, res["mqtt"]["username"].as<String>().c_str(), 63);
            if (res["mqtt"]["password"].as<String>().length() > 0)
                strncpy(cfg.mqttPass, res["mqtt"]["password"].as<String>().c_str(), 63);

            saveConfig(); // Simpan userId yang baru dapat
            Serial.println("   Auth OK! userId=" + String(cfg.userId));
            http.end();
            return true;
        }
    }
    Serial.println("   Auth gagal: HTTP " + String(code));
    http.end();
    return false;
}

// ================================================================
// LED HELPERS
// ================================================================
void ledOn()  { digitalWrite(STATUS_LED, LOW);  } // Active LOW
void ledOff() { digitalWrite(STATUS_LED, HIGH); }

void blinkLED(int times, int ms) {
    for (int i = 0; i < times; i++) {
        ledOn();  delay(ms);
        ledOff(); delay(ms);
    }
}
