// ============================================================
// TeweLocal.ino — ESP8266 Offline-First Smart Home
// ============================================================
// Control relay via browser (local WiFi, no internet required).
// Auto-sync to cloud when internet available.
// Based on proven esp8266_toggle_https.ino pattern.
//
// Dependencies:
//   - ESPAsyncTCP          by dvarrel
//   - ESPAsyncWebServer    by lacamera
//   - ArduinoJson          by Benoit Blanchon (v7.x)
//   - PubSubClient         by Nick O'Leary
// ============================================================

#include <ArduinoJson.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>
#include <ESPAsyncTCP.h>
#include <ESPAsyncWebServer.h>
#include <PubSubClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <EEPROM.h>

// ================= CONFIG & EEPROM =================
struct EepromConfig {
  uint32_t magic;
  char ssid[64];
  char pass[64];
  char api[128];
  char dev[32];
  char session[33];
  char auth_user[32];
  char auth_pass[64];
};
EepromConfig cfg;

const char *AP_SSID = "Tewe-Panel";
const char *AP_PASS = "12345678";

void loadConfig() {
  EEPROM.begin(512);
  EEPROM.get(0, cfg);
  if (cfg.magic != 0x54455747) { // "TEW7" formats to store local config
    Serial.println(F("⚠ No valid EEPROM config found. Formatting with defaults."));
    cfg.magic = 0x54455747;
    cfg.ssid[0] = '\0';
    cfg.pass[0] = '\0';
    strlcpy(cfg.api, "https://nh.mdpower.io/api/devices", sizeof(cfg.api));
    strlcpy(cfg.dev, "DEV_JQDK0QYUUJ", sizeof(cfg.dev));
    cfg.session[0] = '\0';
    strlcpy(cfg.auth_user, "admin", sizeof(cfg.auth_user));
    strlcpy(cfg.auth_pass, "admin", sizeof(cfg.auth_pass));
    EEPROM.put(0, cfg);
    EEPROM.commit();
  } else {
    Serial.println(F("✅ Config loaded from EEPROM"));
  }
}

void saveConfig() {
  EEPROM.put(0, cfg);
  EEPROM.commit();
}

// ================= WIDGET =================
struct LocalWidget {
  String key;
  String name;
  String type;
  String value;
  String unit;
  int pin;
};

#define MAX_WIDGETS 12
LocalWidget localWidgets[MAX_WIDGETS];
int widgetCount = 0;

// Forward Declarations
void applyPin(LocalWidget &w);
void publishWidgetState(int i);
void broadcastWS(const char *key, const char *value);
void queueAPISync(const String &key, const String &value);

// ================= PIN MAP =================
struct PinMap {
  const char *key;
  int pin;
};
PinMap pinMap[] = {
    {"toggle1", 5}, // D1 (GPIO5)
    {"toggle2", 4}, // D2 (GPIO4)
};
const int PIN_MAP_SIZE = sizeof(pinMap) / sizeof(pinMap[0]);

// ================= MQTT =================
String mqttHost;
int mqttPort;
String mqttUser;
String mqttPass;
int userId;

// ================= CLIENTS (global, reuse!) =================
BearSSL::WiFiClientSecure httpsClient;
BearSSL::WiFiClientSecure mqttSecure;
PubSubClient mqtt(mqttSecure);

// ================= WEB SERVER =================
AsyncWebServer server(80);
AsyncWebSocket ws("/ws");

// ================= STATE =================
bool hasInternet = false;
bool mqttReady = false;
bool mqttCredsValid = false;
unsigned long lastPing = 0;
unsigned long lastMqttRetry = 0;

// ================= HTTP API SYNC QUEUE =================
// Fallback: POST ke Laravel API saat MQTT tidak tersedia
struct SyncEntry {
  String key;
  String value;
};
#define SYNC_QUEUE_SIZE 10
SyncEntry syncQueue[SYNC_QUEUE_SIZE];
int syncQueueCount = 0;

void queueAPISync(const String &key, const String &value) {
  if (syncQueueCount >= SYNC_QUEUE_SIZE) {
    // Geser: buang yang paling lama
    for (int i = 0; i < SYNC_QUEUE_SIZE - 1; i++)
      syncQueue[i] = syncQueue[i + 1];
    syncQueueCount = SYNC_QUEUE_SIZE - 1;
  }
  syncQueue[syncQueueCount].key = key;
  syncQueue[syncQueueCount].value = value;
  syncQueueCount++;
}

void flushAPISyncQueue() {
  if (syncQueueCount == 0 || !hasInternet)
    return;

  for (int i = 0; i < syncQueueCount; i++) {
    HTTPClient http;
    httpsClient.setInsecure();
    String url = String(cfg.api) + "/" + String(cfg.dev) + "/widgets/" + syncQueue[i].key;
    http.begin(httpsClient, url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.setTimeout(5000);

    String body = "value=" + syncQueue[i].value + "&skip_mqtt=1";
    int code = http.POST(body);
    Serial.printf("📤 API sync %s=%s → HTTP %d\n", syncQueue[i].key.c_str(),
                  syncQueue[i].value.c_str(), code);
    http.end();
    delay(50);
  }
  syncQueueCount = 0;
}

// Helper: check if string is valid (not null, not empty, not "null")
bool isValidStr(const String &s) {
  return s.length() > 0 && s != "null";
}

// ============================================================
// WIFI
// ============================================================
void connectWiFi() {
  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP(AP_SSID, AP_PASS);
  Serial.println("📶 AP: " + String(AP_SSID) + " → http://192.168.4.1");

  if (strlen(cfg.ssid) > 0) {
    Serial.printf("🔗 WiFi: %s", cfg.ssid);
    WiFi.begin(cfg.ssid, cfg.pass);
    int t = 0;
    while (WiFi.status() != WL_CONNECTED && t < 30) {
      delay(500);
      Serial.print(".");
      t++;
    }
    Serial.println();
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("   IP: " + WiFi.localIP().toString());
      Serial.println("   RSSI: " + String(WiFi.RSSI()) + " dBm");
    } else {
      Serial.println("   WiFi gagal — AP-only mode");
    }
  }
}

// ============================================================
// AUTH
// ============================================================
bool authenticate() {
  Serial.println(F("🔐 Authenticating..."));
  HTTPClient http;
  httpsClient.setInsecure();

  String url = String(cfg.api) + "/auth";
  http.begin(httpsClient, url);
  http.addHeader("Content-Type", "application/json");

  JsonDocument req;
  req["device_code"] = cfg.dev;
  String body;
  serializeJson(req, body);

  int code = http.POST(body);
  if (code != 200) {
    Serial.println("  Auth gagal: HTTP " + String(code));
    http.end();
    return false;
  }

  String resp = http.getString();

  JsonDocument doc;
  // Zero-copy parsing: casting to mutable char* saves RAM
  DeserializationError error = deserializeJson(doc, const_cast<char*>(resp.c_str()));
  if (error) {
    Serial.print("  Auth deserialize gagal: ");
    Serial.println(error.c_str());
    http.end();
    return false;
  }

  userId = doc["device"]["user_id"];
  mqttHost = doc["mqtt"]["host"].as<String>();
  mqttPort = doc["mqtt"]["port"].as<int>();
  mqttUser = doc["mqtt"]["username"].as<String>();
  mqttPass = doc["mqtt"]["password"].as<String>();

  Serial.printf("  userId=%d mqtt=%s:%d\n", userId, mqttHost.c_str(), mqttPort);
  http.end();
  httpsClient.stop(); // FORCE CLOSE to prevent socket reuse bugs

  // Validate MQTT credentials
  mqttCredsValid = isValidStr(mqttHost) && isValidStr(mqttUser) && isValidStr(mqttPass);
  if (!mqttCredsValid) {
    Serial.println(F("  ⚠ MQTT credentials null/kosong dari server!"));
    Serial.println(F("  → Cek MQTT_HOST, MQTT_USERNAME, MQTT_PASSWORD di .env server"));
    Serial.println(F("  → Firmware tetap jalan dalam mode lokal (tanpa MQTT)"));
  }

  // Auth tetap OK — device masih bisa kontrol lokal via WebSocket
  Serial.println(F("  Auth OK!"));
  return true;
}

// ============================================================
// FETCH WIDGETS
// ============================================================
bool fetchWidgets() {
  Serial.println(F("📋 Fetching widgets..."));
  HTTPClient http;
  httpsClient.setInsecure();

  String url = String(cfg.api) + "/" + String(cfg.dev) + "/widgets?lite=1";
  Serial.println("  URL: " + url);
  http.begin(httpsClient, url);

  int code = http.GET();
  if (code != 200) {
    Serial.println("  Fetch gagal: HTTP " + String(code));
    http.end();
    httpsClient.stop();
    return false;
  }

  JsonDocument filter;
  filter["widgets"]["*"]["type"] = true;
  filter["widgets"]["*"]["name"] = true;
  filter["widgets"]["*"]["value"] = true;
  filter["widgets"]["*"]["config"]["unit"] = true;

  JsonDocument doc;
  // STREAM PARSING: Read directly from socket. Saves massive RAM by bypassing http.getString()
  DeserializationError error = deserializeJson(doc, http.getStream(), DeserializationOption::Filter(filter));
  if (error) {
    Serial.print("  Fetch deserialize gagal: ");
    Serial.println(error.c_str());
    http.end();
    httpsClient.stop();
    return false;
  }

  String base = "users/" + String(userId) + "/devices/" + String(cfg.dev);
  JsonObject widgets = doc["widgets"].as<JsonObject>();
  
  http.end();
  httpsClient.stop();

  widgetCount = 0;
  for (JsonPair kv : widgets) {
    String key = kv.key().c_str();
    String type = kv.value()["type"].as<String>();

    if (widgetCount >= MAX_WIDGETS)
      break;

    LocalWidget &w = localWidgets[widgetCount];
    w.key = key;
    w.type = type;
    w.name = kv.value()["name"].as<String>();
    w.value = kv.value()["value"].as<String>();
    w.unit = kv.value()["config"]["unit"] | "";
    w.pin = -1;

    if (w.type == "toggle") {
      for (int p = 0; p < PIN_MAP_SIZE; p++) {
        if (key == pinMap[p].key) {
          w.pin = pinMap[p].pin;
          pinMode(w.pin, OUTPUT);
          break;
        }
      }
    }

    Serial.printf("  [%d] %s (type=%s) pin=%d val=%s\n", widgetCount, key.c_str(), w.type.c_str(), w.pin, w.value.c_str());
    widgetCount++;
  }

  Serial.println("  Loaded " + String(widgetCount) + " widgets");
  http.end();
  return true;
}

// ============================================================
// PIN CONTROL
// ============================================================
void applyPin(LocalWidget &w) {
  if (w.pin < 0 || w.type != "toggle")
    return;
  digitalWrite(w.pin, (w.value == "1" || w.value == "true") ? LOW : HIGH); // Active LOW relay
}

void publishWidgetState(int i) {
  if (!mqtt.connected())
    return;
  String topic = "users/" + String(userId) + "/devices/" + String(cfg.dev) + "/sensors/" + localWidgets[i].key;
  mqtt.publish(topic.c_str(), localWidgets[i].value.c_str(), true);
}

// ============================================================
// WEBSOCKET (declared before MQTT so mqttCallback can use broadcastWS)
// ============================================================
void broadcastWS(const char *key, const char *value) {
  JsonDocument d;
  d["event"] = "state";
  d["key"] = key;
  d["value"] = value;
  String json;
  serializeJson(d, json);
  ws.textAll(json);
}

String buildInitJson() {
  JsonDocument doc;
  doc["event"] = "init";
  doc["device"] = cfg.dev;
  doc["uptime"] = millis() / 1000;
  doc["heap"] = ESP.getFreeHeap();
  doc["wifi"] = WiFi.status() == WL_CONNECTED;
  doc["internet"] = hasInternet;
  doc["mqtt"] = mqtt.connected();
  doc["ip"] = WiFi.localIP().toString();
  doc["ap_ip"] = WiFi.softAPIP().toString();
  doc["rssi"] = WiFi.RSSI();

  JsonObject conf = doc["config"].to<JsonObject>();
  conf["ssid"] = cfg.ssid;
  conf["api"] = cfg.api;
  conf["dev"] = cfg.dev;
  conf["auth_user"] = cfg.auth_user;

  JsonArray arr = doc["widgets"].to<JsonArray>();
  for (int i = 0; i < widgetCount; i++) {
    JsonObject w = arr.add<JsonObject>();
    w["key"] = localWidgets[i].key;
    w["name"] = localWidgets[i].name;
    w["type"] = localWidgets[i].type;
    w["value"] = localWidgets[i].value;
    w["unit"] = localWidgets[i].unit;
    w["pin"] = localWidgets[i].pin;
  }

  String json;
  serializeJson(doc, json);
  return json;
}

// ============================================================
// MQTT
// ============================================================
void mqttCallback(char *topic, byte *payload, unsigned int len) {
  String msg;
  for (unsigned int i = 0; i < len; i++)
    msg += (char)payload[i];

  String topicStr = String(topic);
  String baseCtrl = "users/" + String(userId) + "/devices/" + String(cfg.dev) + "/control/";

  for (int i = 0; i < widgetCount; i++) {
    if (localWidgets[i].type == "toggle") {
      if (topicStr == baseCtrl + localWidgets[i].key) {
        localWidgets[i].value = msg;
        applyPin(localWidgets[i]);
        publishWidgetState(i);
        broadcastWS(localWidgets[i].key.c_str(), msg.c_str());
        Serial.println("MQTT " + localWidgets[i].key + " → " + msg);
      }
    }
  }
}

bool isMqttSetup = false;

void setupMQTT() {
  if (isMqttSetup) return;
  
  Serial.println(F("⚙️ Setting up MQTT client..."));
  mqttSecure.setInsecure();
  // Limit memory usage for TLS
  mqttSecure.setBufferSizes(1024, 512); 
  
  mqtt.setServer(mqttHost.c_str(), mqttPort);
  mqtt.setCallback(mqttCallback);
  mqtt.setBufferSize(512);
  mqtt.setKeepAlive(30);
  
  isMqttSetup = true;
}

void connectMQTT() {
  if (!isMqttSetup) setupMQTT();

  Serial.printf("MQTT → %s:%d user=%s heap=%d\n", mqttHost.c_str(), mqttPort,
                mqttUser.c_str(), ESP.getFreeHeap());

  String cid = "TeweLocal-" + String(cfg.dev) + "-" + String(millis() % 10000);
  
  // Ensure connection is fully closed before retrying
  if (mqtt.connected()) mqtt.disconnect();
  mqttSecure.stop();
  delay(10);
  
  if (mqtt.connect(cid.c_str(), mqttUser.c_str(), mqttPass.c_str())) {
    Serial.println(F("📡 MQTT connected!"));
    String baseCtrl = "users/" + String(userId) + "/devices/" + String(cfg.dev) + "/control/";
    for (int i = 0; i < widgetCount; i++) {
      if (localWidgets[i].type == "toggle") {
        mqtt.subscribe((baseCtrl + localWidgets[i].key).c_str(), 1);
      }
      publishWidgetState(i);
      delay(20);
    }
  } else {
    Serial.printf("MQTT fail rc=%d heap=%d\n", mqtt.state(), ESP.getFreeHeap());
  }
}

// ============================================================
// WEBSOCKET EVENT HANDLER
// ============================================================
void onWsEvent(AsyncWebSocket *s, AsyncWebSocketClient *client,
               AwsEventType type, void *arg, uint8_t *data, size_t len) {
  if (type == WS_EVT_CONNECT) {
    Serial.printf("WS #%u connected\n", client->id());
    client->text(buildInitJson());
  } else if (type == WS_EVT_DATA) {
    String msg;
    for (size_t i = 0; i < len; i++)
      msg += (char)data[i];

    JsonDocument d;
    if (!deserializeJson(d, msg)) {
      String action = d["action"].as<String>();
      String key = d["key"].as<String>();

      if (action == "toggle") {
        for (int i = 0; i < widgetCount; i++) {
          if (localWidgets[i].key == key && localWidgets[i].type == "toggle") {
            localWidgets[i].value = (localWidgets[i].value == "1") ? "0" : "1";
            applyPin(localWidgets[i]);
            broadcastWS(key.c_str(), localWidgets[i].value.c_str());
            if (mqtt.connected()) {
              publishWidgetState(i);
            } else if (hasInternet) {
              queueAPISync(key, localWidgets[i].value);
            }
            Serial.println("WS " + key + " → " + localWidgets[i].value);
            break;
          }
        }
      } else if (action == "set") {
        String val = d["value"].as<String>();
        for (int i = 0; i < widgetCount; i++) {
          if (localWidgets[i].key == key) {
            localWidgets[i].value = val;
            applyPin(localWidgets[i]);
            broadcastWS(key.c_str(), localWidgets[i].value.c_str());
            if (mqtt.connected()) {
              publishWidgetState(i);
            } else if (hasInternet) {
              queueAPISync(key, localWidgets[i].value);
            }
            break;
          }
        }
      }
    }
  }
}

// ============================================================
// EMBEDDED HTML DASHBOARD
// ============================================================
#include "index_html.h"
#include "login_html.h"

bool isAuthenticated(AsyncWebServerRequest *request) {
  if (strlen(cfg.session) == 0) return false;
  if (request->hasHeader("Cookie")) {
    String cookie = request->header("Cookie");
    int idx = cookie.indexOf("tewe_sess=");
    if (idx != -1) {
      String sess = cookie.substring(idx + 10);
      int endIdx = sess.indexOf(';');
      if (endIdx != -1) sess = sess.substring(0, endIdx);
      sess.trim();
      return (sess == String(cfg.session));
    }
  }
  return false;
}

// ============================================================
// WEB SERVER SETUP
// ============================================================
void setupWebServer() {
  ws.onEvent(onWsEvent);
  server.addHandler(&ws);
  ws.enable(true);
  // Limit WS clients to save RAM for BearSSL MQTT TLS (~22KB needed)
  ws.cleanupClients(2);

  // REST API
  server.on("/api/status", HTTP_GET, [](AsyncWebServerRequest *r) {
    if (!isAuthenticated(r)) { r->send(401); return; }
    r->send(200, "application/json", buildInitJson());
  });

  server.on("/api/toggle", HTTP_POST, [](AsyncWebServerRequest *r) {
    if (!isAuthenticated(r)) { r->send(401); return; }
    if (!r->hasParam("key", true)) {
      r->send(400);
      return;
    }
    String key = r->getParam("key", true)->value();
    for (int i = 0; i < widgetCount; i++) {
      if (localWidgets[i].key == key && localWidgets[i].type == "toggle") {
        localWidgets[i].value = (localWidgets[i].value == "1") ? "0" : "1";
        applyPin(localWidgets[i]);
        broadcastWS(key.c_str(), localWidgets[i].value.c_str());
        if (mqtt.connected())
          publishWidgetState(i);
        r->send(200, "application/json", "{\"ok\":true,\"value\":\"" + localWidgets[i].value + "\"}");
        return;
      }
    }
    r->send(404);
  });

  server.on("/api/scan", HTTP_GET, [](AsyncWebServerRequest *r) {
    if (!isAuthenticated(r)) { r->send(401); return; }
    int n = WiFi.scanNetworks();
    JsonDocument doc;
    JsonArray arr = doc.to<JsonArray>();
    for (int i = 0; i < n; i++) {
      JsonObject obj = arr.add<JsonObject>();
      obj["s"] = WiFi.SSID(i);
      obj["r"] = WiFi.RSSI(i);
    }
    String out;
    serializeJson(doc, out);
    r->send(200, "application/json", out);
  });

  server.on("/api/config", HTTP_POST, [](AsyncWebServerRequest *r) {
    if (!isAuthenticated(r)) { r->send(401); return; }
    if (r->hasParam("ssid", true)) strlcpy(cfg.ssid, r->getParam("ssid", true)->value().c_str(), sizeof(cfg.ssid));
    if (r->hasParam("pass", true) && r->getParam("pass", true)->value().length() > 0) strlcpy(cfg.pass, r->getParam("pass", true)->value().c_str(), sizeof(cfg.pass));
    if (r->hasParam("api", true)) strlcpy(cfg.api, r->getParam("api", true)->value().c_str(), sizeof(cfg.api));
    if (r->hasParam("dev", true)) strlcpy(cfg.dev, r->getParam("dev", true)->value().c_str(), sizeof(cfg.dev));
    if (r->hasParam("a_usr", true) && r->getParam("a_usr", true)->value().length() > 0) strlcpy(cfg.auth_user, r->getParam("a_usr", true)->value().c_str(), sizeof(cfg.auth_user));
    if (r->hasParam("a_pwd", true) && r->getParam("a_pwd", true)->value().length() > 0) strlcpy(cfg.auth_pass, r->getParam("a_pwd", true)->value().c_str(), sizeof(cfg.auth_pass));
    saveConfig();
    r->send(200, "text/plain", "OK");
    delay(500);
    ESP.restart();
  });

  server.on("/api/login", HTTP_POST, [](AsyncWebServerRequest *r) {
    if (!r->hasParam("user", true) || !r->hasParam("pass", true)) {
      r->send(400); return;
    }
    String user = r->getParam("user", true)->value();
    String pass = r->getParam("pass", true)->value();
    bool rem = r->hasParam("rem", true) && r->getParam("rem", true)->value() == "1";

    if (user == String(cfg.auth_user) && pass == String(cfg.auth_pass)) {
      // Generate new session token
      String token = "";
      for (int i=0; i<32; i++) {
        token += String(random(0,16), HEX);
      }
      strlcpy(cfg.session, token.c_str(), sizeof(cfg.session));
      saveConfig();
      
      AsyncWebServerResponse *resp = r->beginResponse(200, "text/plain", "OK");
      String cookie = "tewe_sess=" + token + "; Path=/; HttpOnly";
      if (rem) cookie += "; Max-Age=2592000"; // 30 days
      resp->addHeader("Set-Cookie", cookie);
      r->send(resp);
    } else {
      r->send(401, "text/plain", "Invalid credentials");
    }
  });

  server.on("/api/logout", HTTP_POST, [](AsyncWebServerRequest *r) {
    cfg.session[0] = '\0';
    saveConfig();
    AsyncWebServerResponse *resp = r->beginResponse(200, "text/plain", "OK");
    resp->addHeader("Set-Cookie", "tewe_sess=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT");
    r->send(resp);
  });

  // Dashboard (embedded HTML — no LittleFS needed!)
  server.on("/", HTTP_GET, [](AsyncWebServerRequest *r) {
    if (isAuthenticated(r)) r->send_P(200, "text/html", DASHBOARD_HTML);
    else r->send_P(200, "text/html", LOGIN_HTML);
  });

  server.onNotFound([](AsyncWebServerRequest *r) {
    if (r->url() == "/index.html") {
      if (isAuthenticated(r)) r->send_P(200, "text/html", DASHBOARD_HTML);
      else r->send_P(200, "text/html", LOGIN_HTML);
    } else {
      r->send(404, "text/plain", "Not Found");
    }
  });

  server.begin();
  Serial.println(F("🌐 Web server started on port 80"));
}

// ============================================================
// INTERNET CHECK
// ============================================================
bool checkInternet() {
  if (WiFi.status() != WL_CONNECTED)
    return false;
  WiFiClient c;
  bool ok = c.connect("clients3.google.com", 80);
  if (ok)
    c.stop();
  return ok;
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(1000);

  loadConfig(); // Load EEPROM configuration

  Serial.println(F("\n╔════════════════════════════════════════════╗"));
  Serial.println(F("║   TeweLocal — Offline-First Smart Home     ║"));
  Serial.println(F("║   ESP8266 | WebSocket | MQTT Auto-Sync     ║"));
  Serial.println(F("╚════════════════════════════════════════════╝\n"));

  // Setup pins
  for (int i = 0; i < PIN_MAP_SIZE; i++) {
    pinMode(pinMap[i].pin, OUTPUT);
    digitalWrite(pinMap[i].pin, HIGH); // Active LOW relay = OFF
    Serial.printf("  GPIO%-2d → %s (OFF)\n", pinMap[i].pin, pinMap[i].key);
  }

  // WiFi
  connectWiFi();

  // Auth + Widgets (if internet available)
  hasInternet = checkInternet();
  if (hasInternet) {
    Serial.println(F("🌐 Internet available"));
    if (authenticate()) {
      fetchWidgets();
      // Apply initial states
      for (int i = 0; i < widgetCount; i++)
        applyPin(localWidgets[i]);
      // Connect MQTT hanya jika credentials valid
      if (mqttCredsValid) {
        connectMQTT();
        mqttReady = true;
      } else {
        Serial.println(F("⚠ MQTT dilewati — credentials tidak tersedia"));
        Serial.println(F("  Device tetap berjalan via WebSocket lokal"));
      }
    }
  } else {
    Serial.println(F("📴 No internet — offline mode"));
    // Create default toggles from pin map
    for (int i = 0; i < PIN_MAP_SIZE && widgetCount < MAX_WIDGETS; i++) {
      localWidgets[widgetCount].key = pinMap[i].key;
      localWidgets[widgetCount].name = pinMap[i].key;
      localWidgets[widgetCount].type = "toggle";
      localWidgets[widgetCount].value = "0";
      localWidgets[widgetCount].pin = pinMap[i].pin;
      widgetCount++;
    }
  }

  // Web Server (always starts, internet or not)
  setupWebServer();

  Serial.println(F("\n✅ TeweLocal siap!"));
  Serial.println("  AP : " + String(AP_SSID) + " → http://192.168.4.1");
  if (WiFi.status() == WL_CONNECTED)
    Serial.println("  STA: http://" + WiFi.localIP().toString());
  Serial.printf("  Widgets: %d\n\n", widgetCount);
}

// ============================================================
// LOOP
// ============================================================
void loop() {
  // MQTT loop
  if (mqttReady)
    mqtt.loop();
  ws.cleanupClients();

  // Flush pending HTTP API syncs (fallback saat MQTT tidak tersedia)
  if (syncQueueCount > 0)
    flushAPISyncQueue();

  unsigned long now = millis();

  // Reconnect MQTT every 10s
  if (mqttReady && !mqtt.connected() && now - lastMqttRetry >= 10000) {
    lastMqttRetry = now;
    connectMQTT();
  }

  // Check internet every 30s
  if (now - lastPing >= 30000) {
    lastPing = now;
    bool was = hasInternet;
    hasInternet = checkInternet();

    if (hasInternet && !was) {
      Serial.println(F("🌐 Internet kembali!"));
      if (!mqttReady) {
        if (authenticate()) {
          fetchWidgets();
          for (int i = 0; i < widgetCount; i++)
            applyPin(localWidgets[i]);
          if (mqttCredsValid) {
            connectMQTT();
            mqttReady = true;
          } else {
            Serial.println(F("⚠ MQTT masih null — skip"));
          }
        }
      }
    } else if (!hasInternet && was) {
      Serial.println(F("📴 Internet putus — offline mode"));
    }
  }

  yield();
}
