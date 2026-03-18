// ============================================================
// 📦 Tewe.cpp — Implementation (Multi-Widget Support)
// ============================================================

#include "Tewe.h"

// ── Singleton instance for static MQTT callback ────────────
Tewe *Tewe::_instance = nullptr;

// ============================================================
// Constructor
// ============================================================
Tewe::Tewe()
    : _mqttPort(8883), _userId(0), _wifiSSID(nullptr), _wifiPass(nullptr),
      _apiBase(nullptr), _deviceCode(nullptr), _relayActiveLow(true),
      _heartbeatInterval(60000), _pinMapCount(0), _widgetCount(0),
      _toggleCb(nullptr), _sliderCb(nullptr), _textCb(nullptr),
      _lastHeartbeat(0), _lastConnCheck(0), _mqtt(_mqttSecure) {
  _instance = this;
}

// ============================================================
// 🔧  PUBLIC: Configuration (call before begin)
// ============================================================

void Tewe::mapPin(const char *widgetKey, int gpioPin) {
  if (_pinMapCount >= TEWE_MAX_PIN_MAP)
    return;
  _pinMap[_pinMapCount].key = widgetKey;
  _pinMap[_pinMapCount].pin = gpioPin;
  _pinMapCount++;
}

void Tewe::setRelayActiveLow(bool activeLow) { _relayActiveLow = activeLow; }
void Tewe::setHeartbeatInterval(unsigned long ms) { _heartbeatInterval = ms; }
void Tewe::onToggle(TeweToggleCallback cb) { _toggleCb = cb; }
void Tewe::onSlider(TeweSliderCallback cb) { _sliderCb = cb; }
void Tewe::onText(TeweTextCallback cb) { _textCb = cb; }

// ============================================================
//  Helper: parse widget type string
// ============================================================
TeweWidgetType Tewe::_parseType(const String &type) {
  if (type == "toggle")
    return TEWE_TOGGLE;
  if (type == "slider")
    return TEWE_SLIDER;
  if (type == "gauge")
    return TEWE_GAUGE;
  if (type == "text")
    return TEWE_TEXT;
  return TEWE_UNKNOWN;
}

// ============================================================
// 🚀  PUBLIC: begin() — All-in-one initializer
// ============================================================
void Tewe::begin(const char *wifiSSID, const char *wifiPass,
                 const char *apiBaseUrl, const char *deviceCode) {
  _wifiSSID = wifiSSID;
  _wifiPass = wifiPass;
  _apiBase = apiBaseUrl;
  _deviceCode = deviceCode;

  Serial.println(F("\n╔═══════════════════════════════════════════╗"));
  Serial.println(F("║     Tewe IoT Library v2.0.0              ║"));
  Serial.println(F("║     Toggle|Slider|Gauge|Text | MQTTS      ║"));
  Serial.println(F("╚═══════════════════════════════════════════╝\n"));

  // 1. Setup pins from mapPin() calls
  for (int p = 0; p < _pinMapCount; p++) {
    if (_pinMap[p].pin >= 0) {
      pinMode(_pinMap[p].pin, OUTPUT);
      digitalWrite(_pinMap[p].pin, _relayActiveLow ? HIGH : LOW);
      Serial.printf("  GPIO%-2d → %s (OFF)\n", _pinMap[p].pin, _pinMap[p].key);
    }
  }

  // 2. WiFi
  _connectWiFi();

  // 3. Auth → get MQTT credentials (hidden)
  if (!_authenticate()) {
    Serial.println(F("Auth gagal! Restart..."));
    delay(5000);
    ESP.restart();
  }

  // 4. Fetch widgets → build _widgets[]
  if (!_fetchWidgets()) {
    Serial.println(F("Fetch widget gagal! Restart..."));
    delay(5000);
    ESP.restart();
  }

  // 5. Apply initial state to toggle pins
  for (int i = 0; i < _widgetCount; i++) {
    if (_widgets[i].type == TEWE_TOGGLE)
      _applyPin(_widgets[i]);
  }

  // 6. Connect MQTT + subscribe + publish
  if (!_connectMQTT()) {
    Serial.println(F("MQTT gagal! Restart..."));
    delay(5000);
    ESP.restart();
  }

  Serial.println(F("\nDevice siap!"));
  Serial.println(
      F("Serial: on [N] | off [N] | t [N] | set KEY VAL | s | pub\n"));
}

// ============================================================
// 🔄  PUBLIC: run() — Call in loop()
// ============================================================
void Tewe::run() {
  _mqtt.loop();
  _handleSerial();

  if (millis() - _lastConnCheck >= 5000) {
    _lastConnCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println(F("WiFi putus! Reconnecting..."));
      _connectWiFi();
      _reconnectMQTT();
    } else if (!_mqtt.connected()) {
      _reconnectMQTT();
    }
  }

  if (millis() - _lastHeartbeat >= _heartbeatInterval) {
    _sendHeartbeat();
    _lastHeartbeat = millis();
  }

  yield();
}

// ============================================================
// 🎮  PUBLIC: Toggle Control
// ============================================================

bool Tewe::setState(const char *key, bool state) {
  int idx = getWidgetIndex(key);
  if (idx < 0 || _widgets[idx].type != TEWE_TOGGLE)
    return false;
  _widgets[idx].boolVal = state;
  _applyPin(_widgets[idx]);
  _publishWidget(_widgets[idx]);
  if (_toggleCb)
    _toggleCb(key, state);
  return true;
}

bool Tewe::toggle(const char *key) {
  int idx = getWidgetIndex(key);
  if (idx < 0 || _widgets[idx].type != TEWE_TOGGLE)
    return false;
  _widgets[idx].boolVal = !_widgets[idx].boolVal;
  _applyPin(_widgets[idx]);
  _publishWidget(_widgets[idx]);
  if (_toggleCb)
    _toggleCb(key, _widgets[idx].boolVal);
  return true;
}

bool Tewe::getState(const char *key) {
  int idx = getWidgetIndex(key);
  if (idx < 0)
    return false;
  return _widgets[idx].boolVal;
}

// ============================================================
// 🎚️  PUBLIC: Slider Control
// ============================================================

bool Tewe::setSlider(const char *key, int value) {
  int idx = getWidgetIndex(key);
  if (idx < 0 || _widgets[idx].type != TEWE_SLIDER)
    return false;
  // Clamp to min/max
  if (value < _widgets[idx].sliderMin)
    value = _widgets[idx].sliderMin;
  if (value > _widgets[idx].sliderMax)
    value = _widgets[idx].sliderMax;
  _widgets[idx].intVal = value;
  _publishWidget(_widgets[idx]);
  if (_sliderCb)
    _sliderCb(key, value);
  return true;
}

int Tewe::getSlider(const char *key) {
  int idx = getWidgetIndex(key);
  if (idx < 0)
    return 0;
  return _widgets[idx].intVal;
}

// ============================================================
// 📊  PUBLIC: Gauge (publish only)
// ============================================================

bool Tewe::publishGauge(const char *key, float value) {
  int idx = getWidgetIndex(key);
  if (idx < 0 || _widgets[idx].type != TEWE_GAUGE)
    return false;
  _widgets[idx].floatVal = value;
  _publishWidget(_widgets[idx]);
  return true;
}

// ============================================================
// 📝  PUBLIC: Text
// ============================================================

bool Tewe::setText(const char *key, const char *value) {
  int idx = getWidgetIndex(key);
  if (idx < 0 || _widgets[idx].type != TEWE_TEXT)
    return false;
  _widgets[idx].strVal = value;
  _publishWidget(_widgets[idx]);
  if (_textCb)
    _textCb(key, value);
  return true;
}

String Tewe::getText(const char *key) {
  int idx = getWidgetIndex(key);
  if (idx < 0)
    return "";
  return _widgets[idx].strVal;
}

// ============================================================
// 📤  PUBLIC: Generic Publish
// ============================================================

bool Tewe::publish(const char *key) {
  int idx = getWidgetIndex(key);
  if (idx < 0)
    return false;
  _publishWidget(_widgets[idx]);
  return true;
}

void Tewe::publishAll() {
  Serial.println(F("Publishing all widget values..."));
  for (int i = 0; i < _widgetCount; i++) {
    _publishWidget(_widgets[i]);
    delay(30);
  }
}

bool Tewe::publishRaw(const char *key, const char *value) {
  int idx = getWidgetIndex(key);
  if (idx < 0 || !_mqtt.connected())
    return false;
  return _mqtt.publish(_widgets[idx].topicSensor.c_str(), value, true);
}

// ============================================================
// 📊  PUBLIC: Status
// ============================================================

bool Tewe::isWifiConnected() { return WiFi.status() == WL_CONNECTED; }
bool Tewe::isMqttConnected() { return _mqtt.connected(); }
int Tewe::getWidgetCount() { return _widgetCount; }

int Tewe::getToggleCount() {
  int c = 0;
  for (int i = 0; i < _widgetCount; i++)
    if (_widgets[i].type == TEWE_TOGGLE)
      c++;
  return c;
}

int Tewe::getWidgetIndex(const char *key) {
  for (int i = 0; i < _widgetCount; i++) {
    if (_widgets[i].key == key)
      return i;
  }
  return -1;
}

TeweWidgetType Tewe::getWidgetType(const char *key) {
  int idx = getWidgetIndex(key);
  if (idx < 0)
    return TEWE_UNKNOWN;
  return _widgets[idx].type;
}

// ============================================================
// 📡  PRIVATE: WiFi
// ============================================================
void Tewe::_connectWiFi() {
  Serial.printf("Connecting WiFi: %s", _wifiSSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(_wifiSSID, _wifiPass);

  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 40) {
    delay(500);
    Serial.print(".");
    tries++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected! IP: " + WiFi.localIP().toString());
    Serial.println("RSSI: " + String(WiFi.RSSI()) + " dBm");
  } else {
    Serial.println(F("WiFi GAGAL! Restart..."));
    delay(3000);
    ESP.restart();
  }
}

// ============================================================
// 🔐  PRIVATE: Authenticate
// ============================================================
bool Tewe::_apiIsHttps() { return String(_apiBase).startsWith("https"); }

bool Tewe::_authenticate() {
  bool isHttps = _apiIsHttps();
  Serial.println("Authenticating via " + String(isHttps ? "HTTPS" : "HTTP") +
                 "...");

  HTTPClient http;
  String url = String(_apiBase) + "/auth";

  bool ok = isHttps
                ? (_httpsClient.setInsecure(), http.begin(_httpsClient, url))
                : http.begin(_httpClient, url);

  if (!ok) {
    Serial.println(F("HTTP begin() gagal!"));
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  JsonDocument req;
  req["device_code"] = _deviceCode;
  String body;
  serializeJson(req, body);

  int code = http.POST(body);

  if (code == 200) {
    String resp = http.getString();
    JsonDocument res;
    if (deserializeJson(res, resp) == DeserializationError::Ok &&
        res["success"] == true) {
      _userId = res["device"]["user_id"];
      _mqttHost = res["mqtt"]["host"].as<String>();
      _mqttPort = res["mqtt"]["port"] | 8883;
      _mqttUser = res["mqtt"]["username"].as<String>();
      _mqttPass = res["mqtt"]["password"].as<String>();

      Serial.println("Auth OK! user=" + String(_userId));
      http.end();
      return true;
    }
    Serial.println("JSON error / success=false");
  } else if (code < 0) {
    Serial.println("Connection error: " + http.errorToString(code));
  } else {
    Serial.println("HTTP " + String(code) + ": " + http.getString());
  }

  http.end();
  return false;
}

// ============================================================
// 🗂️  PRIVATE: Fetch Widgets (all types)
// ============================================================
bool Tewe::_fetchWidgets() {
  bool isHttps = _apiIsHttps();
  Serial.println("Fetching widgets...");

  HTTPClient http;
  String url = String(_apiBase) + "/" + String(_deviceCode) + "/widgets";

  bool ok = isHttps
                ? (_httpsClient.setInsecure(), http.begin(_httpsClient, url))
                : http.begin(_httpClient, url);

  if (!ok) {
    Serial.println(F("HTTP begin() gagal!"));
    return false;
  }

  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  int code = http.GET();

  if (code == 200) {
    String payload = http.getString();
    JsonDocument doc;

    if (deserializeJson(doc, payload) == DeserializationError::Ok &&
        doc["success"] == true) {
      _widgetCount = 0;
      JsonObject widgetsObj = doc["widgets"].as<JsonObject>();
      String base =
          "users/" + String(_userId) + "/devices/" + String(_deviceCode);

      for (JsonPair kv : widgetsObj) {
        if (_widgetCount >= TEWE_MAX_WIDGETS)
          break;

        String key = kv.key().c_str();
        String type = kv.value()["type"].as<String>();
        String name = kv.value()["name"].as<String>();
        String val = kv.value()["value"].as<String>();

        TeweWidgetType wType = _parseType(type);
        if (wType == TEWE_UNKNOWN)
          continue; // skip unsupported types

        TeweWidget &w = _widgets[_widgetCount];
        w.key = key;
        w.name = name;
        w.type = wType;
        w.pin = -1;
        w.topicControl = base + "/control/" + key;
        w.topicSensor = base + "/sensors/" + key;
        w.valid = true;
        w.boolVal = false;
        w.intVal = 0;
        w.floatVal = 0.0;
        w.strVal = "";
        w.sliderMin = 0;
        w.sliderMax = 100;

        // Parse initial value based on type
        switch (wType) {
        case TEWE_TOGGLE:
          w.boolVal = (val == "1" || val == "true" || val == "on");
          // Match pin from mapPin() calls
          for (int p = 0; p < _pinMapCount; p++) {
            if (key == _pinMap[p].key) {
              w.pin = _pinMap[p].pin;
              break;
            }
          }
          break;

        case TEWE_SLIDER:
          w.intVal = val.toInt();
          w.sliderMin = kv.value()["min"] | 0;
          w.sliderMax = kv.value()["max"] | 100;
          break;

        case TEWE_GAUGE:
          w.floatVal = val.toFloat();
          break;

        case TEWE_TEXT:
          w.strVal = val;
          break;

        default:
          break;
        }

        // Type label for log
        const char *typeLabel[] = {"TOGGLE", "SLIDER", "GAUGE", "TEXT", "?"};
        Serial.printf("  [%d] %-8s | %-7s | %-10s | val=%s", _widgetCount,
                      key.c_str(), typeLabel[wType], name.c_str(), val.c_str());
        if (wType == TEWE_TOGGLE && w.pin >= 0)
          Serial.printf(" | pin=%d", w.pin);
        if (wType == TEWE_SLIDER)
          Serial.printf(" | range=%d-%d", w.sliderMin, w.sliderMax);
        Serial.println();

        _widgetCount++;
      }

      _topicHeartbeat = base + "/heartbeat";
      Serial.println("Loaded " + String(_widgetCount) + " widgets");
      http.end();
      return true;
    }
    Serial.println(F("JSON parse gagal / success=false"));
  } else if (code < 0) {
    Serial.println("Connection error: " + http.errorToString(code));
  } else {
    Serial.println("HTTP " + String(code) + ": " + http.getString());
  }

  http.end();
  return false;
}

// ============================================================
// 🔌  PRIVATE: Apply Pin (toggle only)
// ============================================================
void Tewe::_applyPin(TeweWidget &w) {
  if (w.type != TEWE_TOGGLE || w.pin < 0)
    return;
  bool level = _relayActiveLow ? !w.boolVal : w.boolVal;
  digitalWrite(w.pin, level ? HIGH : LOW);
}

// ============================================================
// 📤  PRIVATE: Publish Widget Value
// ============================================================
void Tewe::_publishWidget(TeweWidget &w) {
  if (!_mqtt.connected())
    return;

  String payload;
  switch (w.type) {
  case TEWE_TOGGLE:
    payload = w.boolVal ? "1" : "0";
    break;
  case TEWE_SLIDER:
    payload = String(w.intVal);
    break;
  case TEWE_GAUGE:
    payload = String(w.floatVal, 2);
    break;
  case TEWE_TEXT:
    payload = w.strVal;
    break;
  default:
    return;
  }

  _mqtt.publish(w.topicSensor.c_str(), payload.c_str(), true);
}

// ============================================================
// 🔔  PRIVATE: MQTT Callback (static trampoline)
// ============================================================
void Tewe::_mqttCallbackStatic(char *topic, byte *payload, unsigned int len) {
  if (!_instance)
    return;

  String message = "";
  for (unsigned int i = 0; i < len; i++)
    message += (char)payload[i];

  String topicStr = String(topic);

  for (int i = 0; i < _instance->_widgetCount; i++) {
    TeweWidget &w = _instance->_widgets[i];

    if (topicStr != w.topicControl)
      continue;

    switch (w.type) {
    case TEWE_TOGGLE: {
      bool newState = (message == "1" || message == "true" || message == "on");
      Serial.printf("[%s] TOGGLE %s -> %s\n", w.key.c_str(),
                    w.boolVal ? "ON" : "OFF", newState ? "ON" : "OFF");
      w.boolVal = newState;
      _instance->_applyPin(w);
      _instance->_publishWidget(w);
      if (_instance->_toggleCb)
        _instance->_toggleCb(w.key.c_str(), newState);
      break;
    }

    case TEWE_SLIDER: {
      int newVal = message.toInt();
      if (newVal < w.sliderMin)
        newVal = w.sliderMin;
      if (newVal > w.sliderMax)
        newVal = w.sliderMax;
      Serial.printf("[%s] SLIDER %d -> %d\n", w.key.c_str(), w.intVal, newVal);
      w.intVal = newVal;
      _instance->_publishWidget(w);
      if (_instance->_sliderCb)
        _instance->_sliderCb(w.key.c_str(), newVal);
      break;
    }

    case TEWE_TEXT: {
      Serial.printf("[%s] TEXT \"%s\" -> \"%s\"\n", w.key.c_str(),
                    w.strVal.c_str(), message.c_str());
      w.strVal = message;
      _instance->_publishWidget(w);
      if (_instance->_textCb)
        _instance->_textCb(w.key.c_str(), message.c_str());
      break;
    }

    case TEWE_GAUGE:
      // Gauge is publish-only, ignore incoming control
      break;

    default:
      break;
    }
    return;
  }

  Serial.println("MQTT (unknown topic): " + topicStr + " = " + message);
}

// ============================================================
// 📡  PRIVATE: Connect MQTT
// ============================================================
bool Tewe::_connectMQTT() {
  _mqttSecure.setInsecure();
  _mqtt.setServer(_mqttHost.c_str(), _mqttPort);
  _mqtt.setCallback(_mqttCallbackStatic);
  _mqtt.setKeepAlive(60);
  _mqtt.setBufferSize(TEWE_MQTT_BUFFER);

  String cid = "Tewe-" + String(_deviceCode) + "-" + String(millis());
  Serial.print("MQTT " + _mqttHost + ":" + String(_mqttPort) + " ...");

  if (_mqtt.connect(cid.c_str(), _mqttUser.c_str(), _mqttPass.c_str())) {
    Serial.println(" OK");

    // Subscribe control topics (all types except gauge)
    for (int i = 0; i < _widgetCount; i++) {
      if (_widgets[i].type != TEWE_GAUGE) {
        _mqtt.subscribe(_widgets[i].topicControl.c_str());
        Serial.println("  SUB " + _widgets[i].topicControl);
      }
    }

    publishAll();
    return true;
  }

  Serial.println(" FAIL rc=" + String(_mqtt.state()));
  return false;
}

// ============================================================
// 🔁  PRIVATE: Reconnect MQTT
// ============================================================
void Tewe::_reconnectMQTT() {
  if (_mqtt.connected())
    return;
  Serial.println(F("MQTT putus! Reconnecting..."));
  for (int t = 0; t < 3; t++) {
    if (_connectMQTT())
      return;
    Serial.println("  Retry " + String(t + 1) + "/3...");
    delay(3000);
  }
  Serial.println(F("MQTT gagal! Restart..."));
  delay(5000);
  ESP.restart();
}

// ============================================================
// 💓  PRIVATE: Heartbeat
// ============================================================
void Tewe::_sendHeartbeat() {
  if (!_mqtt.connected() || _topicHeartbeat == "")
    return;
  JsonDocument doc;
  doc["status"] = "online";
  doc["uptime"] = millis() / 1000;
  doc["free_heap"] = ESP.getFreeHeap();
  doc["rssi"] = WiFi.RSSI();
  String payload;
  serializeJson(doc, payload);
  _mqtt.publish(_topicHeartbeat.c_str(), payload.c_str());
}

// ============================================================
// ⌨️  PRIVATE: Serial Input
// ============================================================
void Tewe::_handleSerial() {
  if (!Serial.available())
    return;
  String input = Serial.readStringUntil('\n');
  input.trim();
  if (input.length() == 0)
    return;

  Serial.println("> " + input);

  // Parse: "cmd [arg1] [arg2]"
  String cmd, arg1, arg2;
  int sp1 = input.indexOf(' ');
  if (sp1 > 0) {
    cmd = input.substring(0, sp1);
    String rest = input.substring(sp1 + 1);
    rest.trim();
    int sp2 = rest.indexOf(' ');
    if (sp2 > 0) {
      arg1 = rest.substring(0, sp2);
      arg2 = rest.substring(sp2 + 1);
      arg2.trim();
    } else {
      arg1 = rest;
    }
  } else {
    cmd = input;
  }
  cmd.toLowerCase();

  // ── SET command: set <key> <value> ───────────────────────
  if (cmd == "set" && arg1.length() > 0 && arg2.length() > 0) {
    int idx = getWidgetIndex(arg1.c_str());
    if (idx < 0) {
      Serial.println("Widget '" + arg1 + "' tidak ditemukan");
      return;
    }
    TeweWidget &w = _widgets[idx];
    switch (w.type) {
    case TEWE_TOGGLE:
      setState(arg1.c_str(), arg2 == "1" || arg2 == "on" || arg2 == "true");
      break;
    case TEWE_SLIDER:
      setSlider(arg1.c_str(), arg2.toInt());
      break;
    case TEWE_GAUGE:
      publishGauge(arg1.c_str(), arg2.toFloat());
      break;
    case TEWE_TEXT:
      setText(arg1.c_str(), arg2.c_str());
      break;
    default:
      break;
    }
    return;
  }

  // ── Toggle shortcuts: on/off/t ───────────────────────────
  if (cmd == "on" || cmd == "off" || cmd == "t") {
    // Find toggle by index or key
    int idx = -1;
    if (arg1.length() > 0) {
      if (arg1.toInt() > 0) {
        // By number: find the Nth toggle
        int tNum = arg1.toInt();
        int tCount = 0;
        for (int i = 0; i < _widgetCount; i++) {
          if (_widgets[i].type == TEWE_TOGGLE) {
            tCount++;
            if (tCount == tNum) {
              idx = i;
              break;
            }
          }
        }
      } else {
        idx = getWidgetIndex(arg1.c_str());
      }
    }

    if (idx >= 0 && _widgets[idx].type == TEWE_TOGGLE) {
      if (cmd == "t") {
        toggle(_widgets[idx].key.c_str());
      } else {
        setState(_widgets[idx].key.c_str(), cmd == "on");
      }
    } else if (arg1.length() == 0) {
      // All toggles
      bool newState = (cmd == "on");
      for (int i = 0; i < _widgetCount; i++) {
        if (_widgets[i].type == TEWE_TOGGLE) {
          if (cmd == "t")
            toggle(_widgets[i].key.c_str());
          else
            setState(_widgets[i].key.c_str(), newState);
          delay(30);
        }
      }
    } else {
      Serial.println("Toggle '" + arg1 + "' tidak ditemukan");
    }
    return;
  }

  // ── Status ───────────────────────────────────────────────
  if (cmd == "s") {
    const char *typeLabels[] = {"TOGGLE", "SLIDER", "GAUGE", "TEXT", "?"};
    Serial.println(F("\n== STATUS SEMUA WIDGET =="));
    Serial.printf("%-3s %-10s %-7s %-10s %-12s %-4s\n", "#", "Key", "Type",
                  "Name", "Value", "Pin");
    Serial.println(
        F("----------------------------------------------------------"));
    for (int i = 0; i < _widgetCount; i++) {
      TeweWidget &w = _widgets[i];
      String val;
      switch (w.type) {
      case TEWE_TOGGLE:
        val = w.boolVal ? "ON" : "OFF";
        break;
      case TEWE_SLIDER:
        val = String(w.intVal);
        break;
      case TEWE_GAUGE:
        val = String(w.floatVal, 2);
        break;
      case TEWE_TEXT:
        val = "\"" + w.strVal + "\"";
        break;
      default:
        val = "?";
        break;
      }
      Serial.printf("%-3d %-10s %-7s %-10s %-12s", i + 1, w.key.c_str(),
                    typeLabels[w.type], w.name.c_str(), val.c_str());
      if (w.type == TEWE_TOGGLE && w.pin >= 0)
        Serial.printf(" pin=%d", w.pin);
      Serial.println();
    }
    Serial.println("MQTT: " +
                   String(_mqtt.connected() ? "Connected" : "Disconnected"));
    Serial.println("Uptime: " + String(millis() / 1000) +
                   "s | Heap: " + String(ESP.getFreeHeap()) + " bytes\n");
    return;
  }

  // ── Publish all ──────────────────────────────────────────
  if (cmd == "pub") {
    publishAll();
    return;
  }

  Serial.println(F("Perintah:"));
  Serial.println(F("  on [N]          → toggle ON (N=nomor, kosong=semua)"));
  Serial.println(F("  off [N]         → toggle OFF"));
  Serial.println(F("  t [N]           → flip toggle"));
  Serial.println(F("  set KEY VALUE   → set widget value"));
  Serial.println(F("  s               → status semua widget"));
  Serial.println(F("  pub             → re-publish semua"));
}
