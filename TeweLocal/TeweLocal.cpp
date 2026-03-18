// ============================================================
// 📦 TeweLocal.cpp — ESP8266 Offline-First Implementation
// ============================================================

#include "TeweLocal.h"

TeweLocal *TeweLocal::_instance = nullptr;

// ============================================================
// Constructor
// ============================================================
TeweLocal::TeweLocal()
    : _widgetCount(0), _pinMapCount(0), _queueHead(0), _queueTail(0),
      _widgetCb(nullptr), _server(80), _ws("/ws"), _mqtt(_mqttSecure),
      _hasInternet(false), _mqttConfigured(false), _lastSync(0),
      _lastHeartbeat(0), _lastPing(0) {
  _instance = this;
  memset(&_cfg, 0, sizeof(_cfg));
  _cfg.mqttPort = 8883;
  _cfg.relayActiveLow = true;
}

// ============================================================
// 🔧  Setup Methods
// ============================================================
void TeweLocal::setWiFi(const char *ssid, const char *pass) {
  strncpy(_cfg.wifiSSID, ssid, 63);
  strncpy(_cfg.wifiPass, pass, 63);
}

void TeweLocal::setAP(const char *ssid, const char *pass) {
  strncpy(_cfg.apSSID, ssid, 31);
  strncpy(_cfg.apPass, pass, 31);
}

void TeweLocal::setAPI(const char *apiBase, const char *deviceCode) {
  strncpy(_cfg.apiBase, apiBase, 127);
  strncpy(_cfg.deviceCode, deviceCode, 31);
}

void TeweLocal::setRelayActiveLow(bool v) { _cfg.relayActiveLow = v; }
void TeweLocal::onWidget(TLWidgetCallback cb) { _widgetCb = cb; }

void TeweLocal::mapPin(const char *key, int pin) {
  if (_pinMapCount >= TL_MAX_WIDGETS)
    return;
  _pinMap[_pinMapCount].key = key;
  _pinMap[_pinMapCount].pin = pin;
  _pinMapCount++;
}

// ============================================================
// 🚀  begin()
// ============================================================
void TeweLocal::begin() {
  Serial.println(F("\n╔════════════════════════════════════════════╗"));
  Serial.println(F("║   TeweLocal — Offline-First Smart Home     ║"));
  Serial.println(F("║   ESP8266 | WebSocket | MQTT Auto-Sync     ║"));
  Serial.println(F("╚════════════════════════════════════════════╝\n"));

  // 1. Init LittleFS
  if (!LittleFS.begin()) {
    Serial.println(F("⚠ LittleFS mount gagal! Format..."));
    LittleFS.format();
    LittleFS.begin();
  }

  // 2. Setup pins
  for (int i = 0; i < _pinMapCount; i++) {
    if (_pinMap[i].pin >= 0) {
      pinMode(_pinMap[i].pin, OUTPUT);
      digitalWrite(_pinMap[i].pin, _cfg.relayActiveLow ? HIGH : LOW);
      Serial.printf("  GPIO%-2d → %s (OFF)\n", _pinMap[i].pin, _pinMap[i].key);
    }
  }

  // 3. WiFi (AP + STA)
  _setupWiFi();

  // 4. Try cloud auth → fetch widgets (if internet)
  _hasInternet = _checkInternet();
  if (_hasInternet) {
    Serial.println(F("🌐 Internet available"));
    if (_authenticate()) {
      _fetchWidgets();
      _mqttConfigured = true;
    }
  } else {
    Serial.println(F("📴 No internet — running offline"));
    // Create default widgets from pin map
    for (int i = 0; i < _pinMapCount && _widgetCount < TL_MAX_WIDGETS; i++) {
      TLWidget &w = _widgets[_widgetCount];
      w.key = _pinMap[i].key;
      w.name = _pinMap[i].key;
      w.type = TL_TOGGLE;
      w.pin = _pinMap[i].pin;
      w.boolVal = false;
      w.intVal = 0;
      w.floatVal = 0;
      w.minVal = 0;
      w.maxVal = 100;
      w.icon = "lightbulb";
      w.color = "primary";
      _widgetCount++;
    }
  }

  // Apply initial pin states
  for (int i = 0; i < _widgetCount; i++) {
    if (_widgets[i].type == TL_TOGGLE)
      _applyPin(_widgets[i]);
  }

  // 5. Setup web server + WebSocket
  _setupServer();

  // 6. Setup MQTT (if configured)
  if (_mqttConfigured)
    _setupMQTT();

  Serial.println(F("\n✅ TeweLocal siap!"));
  Serial.println("  AP : " + String(_cfg.apSSID) + " → http://192.168.4.1");
  if (WiFi.status() == WL_CONNECTED)
    Serial.println("  STA: http://" + WiFi.localIP().toString());
  Serial.printf("  Widgets: %d\n\n", _widgetCount);
}

// ============================================================
// 🔄  run() — Call in loop()
// ============================================================
void TeweLocal::run() {
  _ws.cleanupClients();

  // MQTT loop
  if (_mqttConfigured) {
    _mqtt.loop();
  }

  unsigned long now = millis();

  // Check internet every 30s
  if (now - _lastPing >= 30000) {
    _lastPing = now;
    bool wasOnline = _hasInternet;
    _hasInternet = _checkInternet();

    if (_hasInternet && !wasOnline) {
      Serial.println(F("🌐 Internet kembali!"));
      // Try auth + MQTT if not done
      if (!_mqttConfigured) {
        if (_authenticate()) {
          _fetchWidgets();
          _setupMQTT();
          _mqttConfigured = true;
        }
      }
      _flushQueue();
      _syncToCloud();

      // Tell all browsers
      _broadcastWS("__network__", "online");
    } else if (!_hasInternet && wasOnline) {
      Serial.println(F("📴 Internet putus — mode offline"));
      _broadcastWS("__network__", "offline");
    }
  }

  // MQTT reconnect every 10s
  if (_mqttConfigured && !_mqtt.connected() && now - _lastSync >= 10000) {
    _lastSync = now;
    _mqttReconnect();
  }

  // Heartbeat every 60s
  if (_hasInternet && now - _lastHeartbeat >= 60000) {
    _lastHeartbeat = now;
    // Publish heartbeat via MQTT
    if (_mqtt.connected()) {
      String topic = "users/" + String(_cfg.userId) + "/devices/" +
                     String(_cfg.deviceCode) + "/heartbeat";
      JsonDocument doc;
      doc["status"] = "online";
      doc["uptime"] = millis() / 1000;
      doc["free_heap"] = ESP.getFreeHeap();
      doc["rssi"] = WiFi.RSSI();
      doc["ip"] = WiFi.localIP().toString();
      String payload;
      serializeJson(doc, payload);
      _mqtt.publish(topic.c_str(), payload.c_str());
    }
  }

  yield();
}

// ============================================================
// 🎮  Widget Control
// ============================================================
bool TeweLocal::setWidget(const char *key, const char *value) {
  int idx = _findWidget(key);
  if (idx < 0)
    return false;

  TLWidget &w = _widgets[idx];
  switch (w.type) {
  case TL_TOGGLE:
    w.boolVal = (String(value) == "1" || String(value) == "true" ||
                 String(value) == "on");
    _applyPin(w);
    break;
  case TL_SLIDER:
    w.intVal = constrain(String(value).toInt(), w.minVal, w.maxVal);
    break;
  case TL_GAUGE:
    w.floatVal = String(value).toFloat();
    break;
  case TL_TEXT:
    w.strVal = value;
    break;
  default:
    return false;
  }

  // Broadcast to all WS clients
  String v = (w.type == TL_TOGGLE) ? (w.boolVal ? "1" : "0") : String(value);
  _broadcastWS(key, v.c_str());

  // MQTT publish if online
  if (_mqtt.connected()) {
    _publishMQTT(key, v.c_str());
  } else {
    _enqueue(key, v.c_str());
  }

  // User callback
  if (_widgetCb)
    _widgetCb(key, v.c_str());

  return true;
}

bool TeweLocal::toggleWidget(const char *key) {
  int idx = _findWidget(key);
  if (idx < 0 || _widgets[idx].type != TL_TOGGLE)
    return false;
  return setWidget(key, _widgets[idx].boolVal ? "0" : "1");
}

String TeweLocal::getWidgetValue(const char *key) {
  int idx = _findWidget(key);
  if (idx < 0)
    return "";
  TLWidget &w = _widgets[idx];
  switch (w.type) {
  case TL_TOGGLE:
    return w.boolVal ? "1" : "0";
  case TL_SLIDER:
    return String(w.intVal);
  case TL_GAUGE:
    return String(w.floatVal, 2);
  case TL_TEXT:
    return w.strVal;
  default:
    return "";
  }
}

// ============================================================
// 📊  Status
// ============================================================
bool TeweLocal::isWiFiConnected() { return WiFi.status() == WL_CONNECTED; }
bool TeweLocal::isInternetAvailable() { return _hasInternet; }
bool TeweLocal::isMqttConnected() { return _mqtt.connected(); }
int TeweLocal::getWidgetCount() { return _widgetCount; }
int TeweLocal::getQueueSize() {
  if (_queueTail >= _queueHead)
    return _queueTail - _queueHead;
  return TL_MAX_QUEUE - _queueHead + _queueTail;
}

int TeweLocal::_findWidget(const char *key) {
  for (int i = 0; i < _widgetCount; i++)
    if (_widgets[i].key == key)
      return i;
  return -1;
}

// ============================================================
// 📡  WiFi Setup (AP + STA)
// ============================================================
void TeweLocal::_setupWiFi() {
  // Default AP name
  if (strlen(_cfg.apSSID) == 0) {
    snprintf(_cfg.apSSID, 31, "Tewe-%s", _cfg.deviceCode);
    strcpy(_cfg.apPass, "12345678");
  }

  // Start AP always
  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP(_cfg.apSSID, _cfg.apPass);
  Serial.println("📶 AP started: " + String(_cfg.apSSID));
  Serial.println("   AP IP: " + WiFi.softAPIP().toString());

  // Try STA connection
  if (strlen(_cfg.wifiSSID) > 0) {
    Serial.printf("🔗 Connecting WiFi: %s", _cfg.wifiSSID);
    WiFi.begin(_cfg.wifiSSID, _cfg.wifiPass);

    int tries = 0;
    while (WiFi.status() != WL_CONNECTED && tries < 30) {
      delay(500);
      Serial.print(".");
      tries++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("   STA IP: " + WiFi.localIP().toString());
      Serial.println("   RSSI : " + String(WiFi.RSSI()) + " dBm");
    } else {
      Serial.println(F("   WiFi gagal — AP-only mode"));
    }
  }
}

// ============================================================
// 🌐  Web Server + WebSocket
// ============================================================
void TeweLocal::_setupServer() {
  // ── WebSocket handler ────────────────────────────────────
  _ws.onEvent([](AsyncWebSocket *ws, AsyncWebSocketClient *client,
                 AwsEventType type, void *arg, uint8_t *data, size_t len) {
    if (!_instance)
      return;

    if (type == WS_EVT_CONNECT) {
      Serial.printf("WS client #%u connected\n", client->id());
      // Send all widget states on connect
      String json = _instance->_statusToJson();
      client->text(json);
    } else if (type == WS_EVT_DISCONNECT) {
      Serial.printf("WS client #%u disconnected\n", client->id());
    } else if (type == WS_EVT_DATA) {
      // Parse incoming command
      String msg = "";
      for (size_t i = 0; i < len; i++)
        msg += (char)data[i];

      JsonDocument doc;
      if (deserializeJson(doc, msg) == DeserializationError::Ok) {
        String action = doc["action"].as<String>();
        String key = doc["key"].as<String>();

        if (action == "set") {
          _instance->setWidget(key.c_str(), doc["value"].as<String>().c_str());
        } else if (action == "toggle") {
          _instance->toggleWidget(key.c_str());
        }
      }
    }
  });
  _server.addHandler(&_ws);

  // ── REST API ─────────────────────────────────────────────

  // GET /api/status — full device status
  _server.on("/api/status", HTTP_GET, [](AsyncWebServerRequest *req) {
    if (!_instance)
      return;
    req->send(200, "application/json", _instance->_statusToJson());
  });

  // GET /api/widgets — all widgets
  _server.on("/api/widgets", HTTP_GET, [](AsyncWebServerRequest *req) {
    if (!_instance)
      return;
    req->send(200, "application/json", _instance->_widgetsToJson());
  });

  // POST /api/widgets/{key}/toggle
  _server.on("/api/toggle", HTTP_POST, [](AsyncWebServerRequest *req) {
    if (!_instance || !req->hasParam("key", true)) {
      req->send(400, "application/json", "{\"error\":\"missing key\"}");
      return;
    }
    String key = req->getParam("key", true)->value();
    bool ok = _instance->toggleWidget(key.c_str());
    String val = _instance->getWidgetValue(key.c_str());
    req->send(200, "application/json",
              "{\"success\":" + String(ok ? "true" : "false") + ",\"key\":\"" +
                  key + "\",\"value\":\"" + val + "\"}");
  });

  // POST /api/set — set widget value
  _server.on("/api/set", HTTP_POST, [](AsyncWebServerRequest *req) {
    if (!_instance || !req->hasParam("key", true) ||
        !req->hasParam("value", true)) {
      req->send(400, "application/json",
                "{\"error\":\"missing key or value\"}");
      return;
    }
    String key = req->getParam("key", true)->value();
    String val = req->getParam("value", true)->value();
    bool ok = _instance->setWidget(key.c_str(), val.c_str());
    req->send(200, "application/json",
              "{\"success\":" + String(ok ? "true" : "false") + ",\"key\":\"" +
                  key + "\",\"value\":\"" + val + "\"}");
  });

  // GET /api/network
  _server.on("/api/network", HTTP_GET, [](AsyncWebServerRequest *req) {
    if (!_instance)
      return;
    JsonDocument doc;
    doc["wifi"] = WiFi.status() == WL_CONNECTED;
    doc["internet"] = _instance->_hasInternet;
    doc["mqtt"] = _instance->_mqtt.connected();
    doc["ip"] = WiFi.localIP().toString();
    doc["ap_ip"] = WiFi.softAPIP().toString();
    doc["rssi"] = WiFi.RSSI();
    doc["queue"] = _instance->getQueueSize();
    String json;
    serializeJson(doc, json);
    req->send(200, "application/json", json);
  });

  // ── Serve static files from LittleFS ─────────────────────
  _server.serveStatic("/", LittleFS, "/").setDefaultFile("index.html");

  // ── Fallback: embedded HTML when LittleFS is empty ───────
  _server.onNotFound([](AsyncWebServerRequest *req) {
    if (req->url() == "/" || req->url() == "/index.html") {
      // Serve embedded dashboard
      String html = F(
          "<!DOCTYPE html><html><head>"
          "<meta charset=UTF-8><meta name=viewport "
          "content='width=device-width,initial-scale=1'>"
          "<title>Tewe Panel</title><style>"
          "*{margin:0;padding:0;box-sizing:border-box}"
          "body{font-family:sans-serif;background:#0f172a;color:#f1f5f9;min-"
          "height:100vh;padding:16px}"
          "h1{text-align:center;margin:12px "
          "0;font-size:1.3em;background:linear-gradient(135deg,#3b82f6,#06b6d4)"
          ";-webkit-background-clip:text;-webkit-text-fill-color:transparent}"
          ".status{text-align:center;font-size:.8em;color:#94a3b8;margin-"
          "bottom:16px}"
          ".grid{display:grid;grid-template-columns:repeat(auto-fill,minmax("
          "150px,1fr));gap:12px;max-width:600px;margin:0 auto}"
          ".card{background:#1e293b;border:1px solid "
          "#334155;border-radius:14px;padding:18px;text-align:center;cursor:"
          "pointer;transition:all .3s}"
          ".card.on{border-color:#3b82f6;box-shadow:0 0 20px "
          "rgba(59,130,246,.2)}"
          ".icon{font-size:2em;margin-bottom:8px}"
          ".name{font-size:.85em;color:#94a3b8;margin-bottom:8px}"
          ".sw{width:52px;height:28px;border-radius:14px;background:#475569;"
          "position:relative;display:inline-block;transition:background .3s}"
          ".sw.on{background:#3b82f6}"
          ".sw::after{content:'';width:22px;height:22px;border-radius:50%;"
          "background:#fff;position:absolute;top:3px;left:3px;transition:"
          "transform .3s}"
          ".sw.on::after{transform:translateX(24px)}"
          ".info{text-align:center;font-size:.7em;color:#475569;margin-top:"
          "16px}"
          "</style></head><body>"
          "<h1>⚡ Tewe Smart Home</h1>"
          "<div class=status id=st>Connecting...</div>"
          "<div class=grid id=g></div>"
          "<div class=info id=ft></div>"
          "<script>"
          "var ws,W=[];"
          "function cn(){var p=location.protocol=='https:'?'wss:':'ws:';ws=new "
          "WebSocket(p+'//'+location.host+'/ws');"
          "ws.onmessage=function(e){var "
          "d=JSON.parse(e.data);if(d.event=='init'){W=d.widgets||[];document."
          "getElementById('st').textContent=(d.internet?'ONLINE':'OFFLINE')+' "
          "| IP: '+(d.ip||'');rnd()}else "
          "if(d.event=='state'){upd(d.key,d.value)}};"
          "ws.onclose=function(){setTimeout(cn,3000)};ws.onerror=function(){ws."
          "close()}}"
          "function rnd(){var "
          "g=document.getElementById('g');g.innerHTML='';if(!W.length){g."
          "innerHTML='<div "
          "style=\"grid-column:1/"
          "-1;text-align:center;padding:40px;color:#64748b\">No "
          "widgets</div>';return}"
          "W.forEach(function(w){var "
          "c=document.createElement('div');c.className='card'+(w.value=='1'?' "
          "on':'');c.id='c-'+w.key;"
          "c.innerHTML='<div "
          "class=icon>'+(w.type=='toggle'?'💡':'⚙️')+'</div><div "
          "class=name>'+w.name+'</div>'+(w.type=='toggle'?'<div "
          "class=sw'+(w.value=='1'?' on':'')+' "
          "id=s-'+w.key+'></div>':'<div>'+w.value+'</div>');"
          "if(w.type=='toggle')c.onclick=function(){ws.send(JSON.stringify({"
          "action:'toggle',key:w.key}))};g.appendChild(c)})}"
          "function upd(k,v){var "
          "c=document.getElementById('c-'+k);if(c){c.className='card'+(v=='1'?'"
          " on':'');var "
          "s=document.getElementById('s-'+k);if(s)s.className='sw'+(v=='1'?' "
          "on':'')}for(var i=0;i<W.length;i++)if(W[i].key==k)W[i].value=v}"
          "cn()"
          "</script></body></html>");
      req->send(200, "text/html", html);
    } else {
      req->send(404, "text/plain", "Not Found");
    }
  });

  _server.begin();
  Serial.println(F("🌐 Web server started on port 80"));
}

// ============================================================
// 📡  MQTT Setup
// ============================================================
void TeweLocal::_setupMQTT() {
  _mqttSecure.setInsecure(); // Accept self-signed certs
  // Force port 8883 for external TLS (port 1883 is localhost only)
  if (_cfg.mqttPort == 1883)
    _cfg.mqttPort = 8883;
  _mqtt.setServer(_cfg.mqttHost, _cfg.mqttPort);
  _mqtt.setCallback(_mqttCallback);
  _mqtt.setKeepAlive(60);
  _mqtt.setBufferSize(TL_MQTT_BUFFER);
  Serial.printf("MQTT broker: %s:%d (TLS)\n", _cfg.mqttHost, _cfg.mqttPort);
  _mqttReconnect();
}

void TeweLocal::_mqttReconnect() {
  if (_mqtt.connected() || !_hasInternet)
    return;

  String cid =
      "TeweLocal-" + String(_cfg.deviceCode) + "-" + String(millis() % 10000);

  if (_mqtt.connect(cid.c_str(), _cfg.mqttUser, _cfg.mqttPass)) {
    Serial.println(F("📡 MQTT connected"));
    // Subscribe to control topics
    String base = "users/" + String(_cfg.userId) + "/devices/" +
                  String(_cfg.deviceCode) + "/control/#";
    _mqtt.subscribe(base.c_str());
    Serial.println("  SUB: " + base);

    // Publish all current states
    for (int i = 0; i < _widgetCount; i++) {
      String v = getWidgetValue(_widgets[i].key.c_str());
      _publishMQTT(_widgets[i].key.c_str(), v.c_str());
      delay(20);
    }
  } else {
    Serial.println("MQTT fail rc=" + String(_mqtt.state()));
  }
}

void TeweLocal::_publishMQTT(const char *key, const char *value) {
  if (!_mqtt.connected())
    return;
  String topic = "users/" + String(_cfg.userId) + "/devices/" +
                 String(_cfg.deviceCode) + "/sensors/" + key;
  _mqtt.publish(topic.c_str(), value, true);
}

void TeweLocal::_mqttCallback(char *topic, byte *payload, unsigned int len) {
  if (!_instance)
    return;
  String msg;
  for (unsigned int i = 0; i < len; i++)
    msg += (char)payload[i];

  // Extract widget key from topic: .../control/{key}
  String t = String(topic);
  int lastSlash = t.lastIndexOf('/');
  if (lastSlash < 0)
    return;
  String key = t.substring(lastSlash + 1);

  Serial.printf("[MQTT] %s = %s\n", key.c_str(), msg.c_str());
  _instance->setWidget(key.c_str(), msg.c_str());
}

// ============================================================
// 🔐  Auth + Fetch (Cloud)
// ============================================================
bool TeweLocal::_authenticate() {
  Serial.println(F("🔐 Authenticating..."));
  BearSSL::WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(_cfg.apiBase) + "/auth";
  bool isHttps = String(_cfg.apiBase).startsWith("https");

  WiFiClient plainClient;
  bool ok = isHttps ? http.begin(client, url) : http.begin(plainClient, url);
  if (!ok)
    return false;

  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);

  JsonDocument req;
  req["device_code"] = _cfg.deviceCode;
  String body;
  serializeJson(req, body);

  int code = http.POST(body);
  if (code == 200) {
    String resp = http.getString();
    JsonDocument res;
    if (deserializeJson(res, resp) == DeserializationError::Ok &&
        res["success"].as<bool>() == true) {
      _cfg.userId = res["device"]["user_id"];
      strncpy(_cfg.mqttHost, res["mqtt"]["host"].as<String>().c_str(), 127);
      _cfg.mqttPort = res["mqtt"]["port"] | 1883;
      strncpy(_cfg.mqttUser, res["mqtt"]["username"].as<String>().c_str(), 63);
      strncpy(_cfg.mqttPass, res["mqtt"]["password"].as<String>().c_str(), 63);
      Serial.println("  Auth OK! userId=" + String(_cfg.userId));
      http.end();
      return true;
    }
  }
  Serial.println("  Auth gagal: HTTP " + String(code));
  http.end();
  return false;
}

bool TeweLocal::_fetchWidgets() {
  Serial.println(F("📋 Fetching widgets..."));
  BearSSL::WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url =
      String(_cfg.apiBase) + "/" + String(_cfg.deviceCode) + "/widgets";
  bool isHttps = String(_cfg.apiBase).startsWith("https");

  WiFiClient plainClient;
  bool ok = isHttps ? http.begin(client, url) : http.begin(plainClient, url);
  if (!ok)
    return false;

  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  int code = http.GET();
  if (code != 200) {
    Serial.println("  Fetch gagal: HTTP " + String(code));
    http.end();
    return false;
  }

  String payload = http.getString();
  Serial.println("  Raw response (" + String(payload.length()) + " bytes):");
  Serial.println(payload.substring(0, 500)); // Print first 500 chars for debug
  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, payload);
  if (err) {
    Serial.println("  JSON parse error: " + String(err.c_str()));
    http.end();
    return false;
  }
  if (!doc["success"].as<bool>()) {
    Serial.println("  API returned success=false");
    http.end();
    return false;
  }

  _widgetCount = 0;
  JsonObject widgets = doc["widgets"].as<JsonObject>();

  for (JsonPair kv : widgets) {
    if (_widgetCount >= TL_MAX_WIDGETS)
      break;

    String key = kv.key().c_str();
    String type = kv.value()["type"].as<String>();
    String name = kv.value()["name"].as<String>();
    String val = kv.value()["value"].as<String>();

    TLWidget &w = _widgets[_widgetCount];
    w.key = key;
    w.name = name;
    w.pin = -1;
    w.minVal = kv.value()["min"] | 0;
    w.maxVal = kv.value()["max"] | 100;
    w.icon = kv.value()["config"]["icon"] | "circle";
    w.color = kv.value()["config"]["color"] | "primary";

    if (type == "toggle")
      w.type = TL_TOGGLE;
    else if (type == "slider")
      w.type = TL_SLIDER;
    else if (type == "gauge")
      w.type = TL_GAUGE;
    else if (type == "text")
      w.type = TL_TEXT;
    else {
      continue;
    } // skip unknown

    // Parse initial value
    w.boolVal = (val == "1" || val == "true");
    w.intVal = val.toInt();
    w.floatVal = val.toFloat();
    w.strVal = val;

    // Match pin mapping
    for (int p = 0; p < _pinMapCount; p++) {
      if (key == _pinMap[p].key) {
        w.pin = _pinMap[p].pin;
        break;
      }
    }

    const char *tl[] = {"TOGGLE", "SLIDER", "GAUGE", "TEXT"};
    Serial.printf("  [%d] %-10s %-7s pin=%d val=%s\n", _widgetCount,
                  key.c_str(), tl[w.type], w.pin, val.c_str());
    _widgetCount++;
  }

  Serial.println("  Loaded " + String(_widgetCount) + " widgets");
  http.end();
  return true;
}

// ============================================================
// 🌐  Internet Check
// ============================================================
bool TeweLocal::_checkInternet() {
  if (WiFi.status() != WL_CONNECTED)
    return false;
  WiFiClient client;
  bool ok = client.connect("clients3.google.com", 80);
  if (ok)
    client.stop();
  return ok;
}

// ============================================================
// 📤  Offline Queue
// ============================================================
void TeweLocal::_enqueue(const char *key, const char *value) {
  _queue[_queueTail].key = key;
  _queue[_queueTail].value = value;
  _queue[_queueTail].ts = millis();
  _queueTail = (_queueTail + 1) % TL_MAX_QUEUE;
  if (_queueTail == _queueHead)
    _queueHead = (_queueHead + 1) % TL_MAX_QUEUE; // overwrite oldest
}

void TeweLocal::_flushQueue() {
  if (getQueueSize() == 0)
    return;
  Serial.printf("📤 Flushing %d queued changes...\n", getQueueSize());

  while (_queueHead != _queueTail) {
    TLQueueEntry &e = _queue[_queueHead];
    if (_mqtt.connected()) {
      _publishMQTT(e.key.c_str(), e.value.c_str());
    }
    _queueHead = (_queueHead + 1) % TL_MAX_QUEUE;
    delay(20);
  }

  _syncToCloud();
}

void TeweLocal::_syncToCloud() {
  if (!_hasInternet || strlen(_cfg.apiBase) == 0)
    return;

  Serial.println(F("☁️  Syncing to cloud..."));
  BearSSL::WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url =
      String(_cfg.apiBase) + "/" + String(_cfg.deviceCode) + "/widgets";
  bool isHttps = String(_cfg.apiBase).startsWith("https");

  WiFiClient plainClient;
  bool ok = isHttps ? http.begin(client, url) : http.begin(plainClient, url);
  if (!ok)
    return;

  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);

  // Build batch payload
  JsonDocument doc;
  JsonArray arr = doc["widgets"].to<JsonArray>();
  for (int i = 0; i < _widgetCount; i++) {
    JsonObject w = arr.add<JsonObject>();
    w["key"] = _widgets[i].key;
    w["value"] = getWidgetValue(_widgets[i].key.c_str());
  }

  String body;
  serializeJson(doc, body);
  int code = http.POST(body);
  Serial.println("  Sync response: HTTP " + String(code));
  http.end();
}

// ============================================================
// 🔌  Pin Control
// ============================================================
void TeweLocal::_applyPin(TLWidget &w) {
  if (w.type != TL_TOGGLE || w.pin < 0)
    return;
  bool level = _cfg.relayActiveLow ? !w.boolVal : w.boolVal;
  digitalWrite(w.pin, level ? HIGH : LOW);
}

// ============================================================
// 📡  WebSocket Broadcast
// ============================================================
void TeweLocal::_broadcastWS(const char *key, const char *value) {
  JsonDocument doc;
  doc["event"] = "state";
  doc["key"] = key;
  doc["value"] = value;
  String json;
  serializeJson(doc, json);
  _ws.textAll(json);
}

void TeweLocal::_broadcastWSAll() {
  String json = _statusToJson();
  _ws.textAll(json);
}

// ============================================================
// 📦  JSON Builders
// ============================================================
String TeweLocal::_widgetsToJson() {
  JsonDocument doc;
  JsonArray arr = doc.to<JsonArray>();
  for (int i = 0; i < _widgetCount; i++) {
    JsonObject w = arr.add<JsonObject>();
    w["key"] = _widgets[i].key;
    w["name"] = _widgets[i].name;
    w["type"] = (_widgets[i].type == TL_TOGGLE)   ? "toggle"
                : (_widgets[i].type == TL_SLIDER) ? "slider"
                : (_widgets[i].type == TL_GAUGE)  ? "gauge"
                                                  : "text";
    w["value"] = getWidgetValue(_widgets[i].key.c_str());
    w["pin"] = _widgets[i].pin;
    w["min"] = _widgets[i].minVal;
    w["max"] = _widgets[i].maxVal;
    w["icon"] = _widgets[i].icon;
    w["color"] = _widgets[i].color;
  }
  String json;
  serializeJson(doc, json);
  return json;
}

String TeweLocal::_statusToJson() {
  JsonDocument doc;
  doc["event"] = "init";
  doc["device"] = _cfg.deviceCode;
  doc["uptime"] = millis() / 1000;
  doc["heap"] = ESP.getFreeHeap();
  doc["wifi"] = WiFi.status() == WL_CONNECTED;
  doc["internet"] = _hasInternet;
  doc["mqtt"] = _mqtt.connected();
  doc["queue"] = getQueueSize();
  doc["rssi"] = WiFi.RSSI();
  doc["ip"] = WiFi.localIP().toString();
  doc["ap_ip"] = WiFi.softAPIP().toString();

  JsonArray arr = doc["widgets"].to<JsonArray>();
  for (int i = 0; i < _widgetCount; i++) {
    JsonObject w = arr.add<JsonObject>();
    w["key"] = _widgets[i].key;
    w["name"] = _widgets[i].name;
    w["type"] = (_widgets[i].type == TL_TOGGLE)   ? "toggle"
                : (_widgets[i].type == TL_SLIDER) ? "slider"
                : (_widgets[i].type == TL_GAUGE)  ? "gauge"
                                                  : "text";
    w["value"] = getWidgetValue(_widgets[i].key.c_str());
    w["pin"] = _widgets[i].pin;
    w["min"] = _widgets[i].minVal;
    w["max"] = _widgets[i].maxVal;
    w["icon"] = _widgets[i].icon;
    w["color"] = _widgets[i].color;
  }

  String json;
  serializeJson(doc, json);
  return json;
}
