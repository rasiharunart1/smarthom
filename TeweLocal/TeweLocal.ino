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

// ================= CONFIG =================
const char *WIFI_SSID = "Harun";
const char *WIFI_PASS = "harun3211";
const char *AP_SSID = "Tewe-Panel";
const char *AP_PASS = "12345678";
const char *API_BASE = "https://nh.mdpower.io/api/devices";
const char *DEVICE_CODE = "DEV_JQDK0QYUUJ";

// ================= TOGGLE =================
struct Toggle {
  String key;
  String name;
  bool state;
  int pin;
  String topicCtrl;
  String topicState;
};

Toggle toggles[10];
int toggleCount = 0;

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
    String url = String(API_BASE) + "/" + DEVICE_CODE + "/widgets/" + syncQueue[i].key;
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

  if (strlen(WIFI_SSID) > 0) {
    Serial.printf("🔗 WiFi: %s", WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
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

  String url = String(API_BASE) + "/auth";
  http.begin(httpsClient, url);
  http.addHeader("Content-Type", "application/json");

  JsonDocument req;
  req["device_code"] = DEVICE_CODE;
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
  if (deserializeJson(doc, resp)) {
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

  String url = String(API_BASE) + "/" + DEVICE_CODE + "/widgets";
  http.begin(httpsClient, url);

  int code = http.GET();
  if (code != 200) {
    Serial.println("  Fetch gagal: HTTP " + String(code));
    http.end();
    return false;
  }

  String payload = http.getString();
  Serial.println("  Response: " + String(payload.length()) + " bytes");

  JsonDocument doc;
  if (deserializeJson(doc, payload)) {
    http.end();
    return false;
  }

  String base = "users/" + String(userId) + "/devices/" + DEVICE_CODE;
  JsonObject widgets = doc["widgets"].as<JsonObject>();

  toggleCount = 0;
  for (JsonPair kv : widgets) {
    String key = kv.key().c_str();
    String type = kv.value()["type"].as<String>();

    if (type != "toggle")
      continue;
    if (toggleCount >= 10)
      break;

    Toggle &t = toggles[toggleCount];
    t.key = key;
    t.name = kv.value()["name"].as<String>();
    t.state = (kv.value()["value"].as<String>() == "1");
    t.topicCtrl = base + "/control/" + key;
    t.topicState = base + "/sensors/" + key;
    t.pin = -1;

    for (int p = 0; p < PIN_MAP_SIZE; p++) {
      if (key == pinMap[p].key) {
        t.pin = pinMap[p].pin;
        pinMode(t.pin, OUTPUT);
        break;
      }
    }

    Serial.printf("  [%d] %s pin=%d val=%d\n", toggleCount, key.c_str(), t.pin,
                  t.state);
    toggleCount++;
  }

  Serial.println("  Loaded " + String(toggleCount) + " toggles");
  http.end();
  return true;
}

// ============================================================
// PIN CONTROL
// ============================================================
void applyPin(Toggle &t) {
  if (t.pin < 0)
    return;
  digitalWrite(t.pin, t.state ? LOW : HIGH); // Active LOW relay
}

void publishToggle(int i) {
  if (!mqtt.connected())
    return;
  mqtt.publish(toggles[i].topicState.c_str(), toggles[i].state ? "1" : "0",
               true);
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
  doc["device"] = DEVICE_CODE;
  doc["uptime"] = millis() / 1000;
  doc["heap"] = ESP.getFreeHeap();
  doc["wifi"] = WiFi.status() == WL_CONNECTED;
  doc["internet"] = hasInternet;
  doc["mqtt"] = mqtt.connected();
  doc["ip"] = WiFi.localIP().toString();
  doc["ap_ip"] = WiFi.softAPIP().toString();
  doc["rssi"] = WiFi.RSSI();

  JsonArray arr = doc["widgets"].to<JsonArray>();
  for (int i = 0; i < toggleCount; i++) {
    JsonObject w = arr.add<JsonObject>();
    w["key"] = toggles[i].key;
    w["name"] = toggles[i].name;
    w["type"] = "toggle";
    w["value"] = toggles[i].state ? "1" : "0";
    w["pin"] = toggles[i].pin;
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

  for (int i = 0; i < toggleCount; i++) {
    if (String(topic) == toggles[i].topicCtrl) {
      toggles[i].state = (msg == "1");
      applyPin(toggles[i]);
      publishToggle(i);
      broadcastWS(toggles[i].key.c_str(), toggles[i].state ? "1" : "0");
      Serial.println("MQTT " + toggles[i].key + " → " + msg);
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

  String cid = "TeweLocal-" + String(DEVICE_CODE) + "-" + String(millis() % 10000);
  
  // Ensure connection is fully closed before retrying
  if (mqtt.connected()) mqtt.disconnect();
  mqttSecure.stop();
  delay(10);
  
  if (mqtt.connect(cid.c_str(), mqttUser.c_str(), mqttPass.c_str())) {
    Serial.println(F("📡 MQTT connected!"));
    for (int i = 0; i < toggleCount; i++) {
      mqtt.subscribe(toggles[i].topicCtrl.c_str(), 1);
      publishToggle(i);
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
        for (int i = 0; i < toggleCount; i++) {
          if (toggles[i].key == key) {
            toggles[i].state = !toggles[i].state;
            applyPin(toggles[i]);
            broadcastWS(key.c_str(), toggles[i].state ? "1" : "0");
            if (mqtt.connected()) {
              publishToggle(i);
            } else if (hasInternet) {
              // Fallback: kirim ke Laravel via HTTP API
              queueAPISync(key, toggles[i].state ? "1" : "0");
            }
            Serial.println("WS " + key + " → " + String(toggles[i].state));
            break;
          }
        }
      } else if (action == "set") {
        String val = d["value"].as<String>();
        for (int i = 0; i < toggleCount; i++) {
          if (toggles[i].key == key) {
            toggles[i].state = (val == "1");
            applyPin(toggles[i]);
            broadcastWS(key.c_str(), toggles[i].state ? "1" : "0");
            if (mqtt.connected()) {
              publishToggle(i);
            } else if (hasInternet) {
              queueAPISync(key, toggles[i].state ? "1" : "0");
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
const char DASHBOARD_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html><html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#0f172a">
<title>Tewe Smart Home</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;flex-direction:column;-webkit-tap-highlight-color:transparent}
header{position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:rgba(15,23,42,.92);backdrop-filter:blur(12px);border-bottom:1px solid #334155}
.logo{font-size:1.2em;font-weight:800;background:linear-gradient(135deg,#3b82f6,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.badge{font-size:.7em;padding:3px 8px;border-radius:20px;font-weight:600;text-transform:uppercase}
.b-off{background:#ef444430;color:#ef4444}.b-on{background:#22c55e30;color:#22c55e}
.grid{flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px;padding:16px;max-width:800px;margin:0 auto;width:100%}
.card{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:20px;display:flex;flex-direction:column;align-items:center;gap:10px;cursor:pointer;user-select:none;transition:all .3s;position:relative;overflow:hidden}
.card::before{content:'';position:absolute;inset:0;border-radius:14px;opacity:0;transition:opacity .4s}
.card:active{transform:scale(.96)}
.card.on{border-color:#3b82f6}
.card.on::before{opacity:1;background:radial-gradient(ellipse at 50% 0%,rgba(59,130,246,.35),transparent 70%)}
.icon{font-size:2em;width:56px;height:56px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,.05);transition:all .3s}
.card.on .icon{background:#3b82f6;box-shadow:0 0 20px rgba(59,130,246,.35)}
.name{font-size:.85em;color:#94a3b8;text-align:center;font-weight:500}
.sw{width:52px;height:28px;border-radius:14px;background:#475569;position:relative;transition:background .3s;flex-shrink:0}
.sw.on{background:#3b82f6;box-shadow:0 0 12px rgba(59,130,246,.35)}
.sw::after{content:'';width:22px;height:22px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;transition:transform .3s cubic-bezier(.34,1.56,.64,1);box-shadow:0 2px 6px rgba(0,0,0,.3)}
.sw.on::after{transform:translateX(24px)}
footer{display:flex;justify-content:space-between;padding:10px 16px;font-size:.7em;color:#64748b;border-top:1px solid #1e293b}
.empty{grid-column:1/-1;text-align:center;padding:60px 0;color:#475569;font-size:1.1em}
@media(max-width:400px){.grid{grid-template-columns:repeat(2,1fr);gap:10px;padding:10px}.card{padding:16px}.icon{width:44px;height:44px;font-size:1.6em}}
</style></head><body>
<header>
<span class="logo">⚡ Tewe</span>
<div><span class="badge b-off" id="sync">OFFLINE</span></div>
</header>
<div class="grid" id="g"><div class="empty">Connecting...</div></div>
<footer><span id="ft1">---</span><span id="ft2">---</span><span id="ft3">---</span></footer>
<script>
var ws,W=[];
function cn(){var p=location.protocol==='https:'?'wss:':'ws:';ws=new WebSocket(p+'//'+location.host+'/ws');
ws.onopen=function(){};
ws.onmessage=function(e){var d=JSON.parse(e.data);
if(d.event==='init'){W=d.widgets||[];
document.getElementById('sync').className='badge '+(d.internet?'b-on':'b-off');
document.getElementById('sync').textContent=d.mqtt?'SYNCED':d.internet?'ONLINE':'LOCAL';
document.getElementById('ft1').textContent='IP: '+(d.ip||'AP only');
document.getElementById('ft2').textContent='Heap: '+(d.heap/1024).toFixed(1)+'KB';
var u=d.uptime||0,m=Math.floor(u/60);document.getElementById('ft3').textContent='Up: '+m+'m';rn()}
else if(d.event==='state'){up(d.key,d.value)}};
ws.onclose=function(){setTimeout(cn,3000)};ws.onerror=function(){ws.close()}}
function rn(){var g=document.getElementById('g');g.innerHTML='';
if(!W.length){g.innerHTML='<div class="empty">No widgets</div>';return}
W.forEach(function(w){var c=document.createElement('div');c.className='card'+(w.value==='1'?' on':'');c.id='c-'+w.key;
c.innerHTML='<div class="icon">💡</div><div class="name">'+w.name+'</div><div class="sw'+(w.value==='1'?' on':'')+'" id="s-'+w.key+'"></div>';
c.onclick=function(){ws.send(JSON.stringify({action:'toggle',key:w.key}))};g.appendChild(c)})}
function up(k,v){var c=document.getElementById('c-'+k);if(!c)return;c.className='card'+(v==='1'?' on':'');
var s=document.getElementById('s-'+k);if(s)s.className='sw'+(v==='1'?' on':'');
for(var i=0;i<W.length;i++)if(W[i].key===k)W[i].value=v}
cn();
</script></body></html>
)rawliteral";

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
    r->send(200, "application/json", buildInitJson());
  });

  server.on("/api/toggle", HTTP_POST, [](AsyncWebServerRequest *r) {
    if (!r->hasParam("key", true)) {
      r->send(400);
      return;
    }
    String key = r->getParam("key", true)->value();
    for (int i = 0; i < toggleCount; i++) {
      if (toggles[i].key == key) {
        toggles[i].state = !toggles[i].state;
        applyPin(toggles[i]);
        broadcastWS(key.c_str(), toggles[i].state ? "1" : "0");
        if (mqtt.connected())
          publishToggle(i);
        r->send(200, "application/json",
                "{\"ok\":true,\"value\":\"" +
                    String(toggles[i].state ? "1" : "0") + "\"}");
        return;
      }
    }
    r->send(404);
  });

  // Dashboard (embedded HTML — no LittleFS needed!)
  server.on("/", HTTP_GET, [](AsyncWebServerRequest *r) {
    r->send_P(200, "text/html", DASHBOARD_HTML);
  });

  server.onNotFound([](AsyncWebServerRequest *r) {
    if (r->url() == "/index.html") {
      r->send_P(200, "text/html", DASHBOARD_HTML);
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
      for (int i = 0; i < toggleCount; i++)
        applyPin(toggles[i]);
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
    for (int i = 0; i < PIN_MAP_SIZE && toggleCount < 10; i++) {
      toggles[toggleCount].key = pinMap[i].key;
      toggles[toggleCount].name = pinMap[i].key;
      toggles[toggleCount].state = false;
      toggles[toggleCount].pin = pinMap[i].pin;
      toggleCount++;
    }
  }

  // Web Server (always starts, internet or not)
  setupWebServer();

  Serial.println(F("\n✅ TeweLocal siap!"));
  Serial.println("  AP : " + String(AP_SSID) + " → http://192.168.4.1");
  if (WiFi.status() == WL_CONNECTED)
    Serial.println("  STA: http://" + WiFi.localIP().toString());
  Serial.printf("  Toggles: %d\n\n", toggleCount);
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
          for (int i = 0; i < toggleCount; i++)
            applyPin(toggles[i]);
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
