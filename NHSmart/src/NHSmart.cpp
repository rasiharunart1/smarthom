/***********************************************************************
 *  NHSmart — Implementation
 *  
 *  Handles: WiFi → HTTPS Auth → MQTT TLS → Data Flow
 ***********************************************************************/

#include "NHSmart.h"

// ═════════════════════════════════════════════════════════════════════
//  Global Instance
// ═════════════════════════════════════════════════════════════════════

NHSmartClass NHSmart;

// ═════════════════════════════════════════════════════════════════════
//  MQTT Callback Bridge
// ═════════════════════════════════════════════════════════════════════

void _nhMqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg;
  msg.reserve(length);
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }
  NHSmart.handleMqttMessage(topic, msg);
}

// ═════════════════════════════════════════════════════════════════════
//  NHTimer Implementation
// ═════════════════════════════════════════════════════════════════════

NHTimer::NHTimer() : _count(0) {
  for (int i = 0; i < NHSMART_MAX_TIMERS; i++) {
    _slots[i].cb = nullptr;
    _slots[i].interval = 0;
    _slots[i].lastRun = 0;
    _slots[i].enabled = false;
    _slots[i].oneShot = false;
  }
}

int NHTimer::setInterval(unsigned long ms, void (*callback)()) {
  if (_count >= NHSMART_MAX_TIMERS) return -1;
  int id = _count;
  _slots[id].cb = callback;
  _slots[id].interval = ms;
  _slots[id].lastRun = millis();
  _slots[id].enabled = true;
  _slots[id].oneShot = false;
  _count++;
  return id;
}

int NHTimer::setTimeout(unsigned long ms, void (*callback)()) {
  if (_count >= NHSMART_MAX_TIMERS) return -1;
  int id = _count;
  _slots[id].cb = callback;
  _slots[id].interval = ms;
  _slots[id].lastRun = millis();
  _slots[id].enabled = true;
  _slots[id].oneShot = true;
  _count++;
  return id;
}

void NHTimer::disable(int id) {
  if (id >= 0 && id < NHSMART_MAX_TIMERS) {
    _slots[id].enabled = false;
  }
}

void NHTimer::enable(int id) {
  if (id >= 0 && id < NHSMART_MAX_TIMERS) {
    _slots[id].enabled = true;
    _slots[id].lastRun = millis(); // Reset timing
  }
}

void NHTimer::run() {
  unsigned long now = millis();
  for (int i = 0; i < _count; i++) {
    if (_slots[i].enabled && _slots[i].cb && (now - _slots[i].lastRun >= _slots[i].interval)) {
      _slots[i].lastRun = now;
      _slots[i].cb();
      if (_slots[i].oneShot) {
        _slots[i].enabled = false;
      }
    }
  }
}

// ═════════════════════════════════════════════════════════════════════
//  NHSmartClass — Constructor
// ═════════════════════════════════════════════════════════════════════

NHSmartClass::NHSmartClass() {
  _callbackCount = 0;
  _authOk = false;
  _caCertSet = false;
  _mqttPort = 8883;
  _lastMqttRetry = 0;
  _lastApiRetry = 0;
  _bootTime = 0;
  _mqttRetryCount = 0;
  _apiRetryCount = 0;
  memset(_apiToken, 0, sizeof(_apiToken));
  _mqtt = new PubSubClient(_tlsMqtt);
  _mqtt->setBufferSize(NHSMART_MQTT_BUFFER);
}

// ═════════════════════════════════════════════════════════════════════
//  begin() — Initialize Everything
// ═════════════════════════════════════════════════════════════════════

void NHSmartClass::begin(const char* deviceCode, const char* ssid, const char* pass, const char* serverUrl) {
  _bootTime = millis();
  strlcpy(_deviceCode, deviceCode, sizeof(_deviceCode));
  strlcpy(_ssid, ssid, sizeof(_ssid));
  strlcpy(_pass, pass, sizeof(_pass));
  strlcpy(_serverUrl, serverUrl, sizeof(_serverUrl));

  NH_PRINTLN("");
  NH_PRINTLN("╔══════════════════════════════════════╗");
  NH_PRINTLN("║      NHSmart IoT Library v1.0.0      ║");
  NH_PRINTLN("║      Platform: tewe.io                ║");
  NH_PRINTLN("╚══════════════════════════════════════╝");
  NH_LOG("Device: %s", _deviceCode);
  NH_LOG("Server: %s", _serverUrl);

  // TLS Configuration
  if (!_caCertSet) {
    NH_LOG("⚠ TLS: Insecure mode (no CA cert set)");
    NH_LOG("  For production, call NHSmart.setCACert(cert) before begin()");
    #if defined(ESP8266)
      _tlsHttp.setInsecure();
      _tlsMqtt.setInsecure();
    #elif defined(ESP32)
      _tlsHttp.setInsecure();
      _tlsMqtt.setInsecure();
    #endif
  } else {
    NH_LOG("🔒 TLS: CA certificate set — secure mode");
  }

  // Connect WiFi
  connectWiFi();

  // Auto-auth on startup
  if (WiFi.status() == WL_CONNECTED) {
    apiAuth();
    if (_authOk) {
      mqttConnect();
    }
  }
}

// ═════════════════════════════════════════════════════════════════════
//  setCACert() — Production TLS Verification
// ═════════════════════════════════════════════════════════════════════

void NHSmartClass::setCACert(const char* caCert) {
  _caCertSet = true;
  #if defined(ESP32)
    _tlsHttp.setCACert(caCert);
    _tlsMqtt.setCACert(caCert);
  #elif defined(ESP8266)
    static BearSSL::X509List certList(caCert);
    _tlsHttp.setTrustAnchors(&certList);
    _tlsMqtt.setTrustAnchors(&certList);
  #endif
  NH_LOG("🔒 CA certificate loaded");
}

// ═════════════════════════════════════════════════════════════════════
//  WiFi
// ═════════════════════════════════════════════════════════════════════

bool NHSmartClass::connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return true;

  NH_LOG("📶 Connecting to WiFi: %s", _ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(_ssid, _pass);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40) {
    delay(500);
    NH_PRINT(".");
    attempts++;
  }
  NH_PRINTLN("");

  if (WiFi.status() == WL_CONNECTED) {
    NH_LOG("✅ WiFi Connected! IP: %s  RSSI: %d dBm",
           WiFi.localIP().toString().c_str(), WiFi.RSSI());
    return true;
  }

  NH_LOG("❌ WiFi connection failed after %d attempts", attempts);
  return false;
}

// ═════════════════════════════════════════════════════════════════════
//  API Auth — HTTPS POST to get Sanctum Token + MQTT Credentials
// ═════════════════════════════════════════════════════════════════════

bool NHSmartClass::apiAuth() {
  if (WiFi.status() != WL_CONNECTED) return false;

  String url = String(_serverUrl) + "/api/devices/auth";
  String body = "{\"device_code\":\"" + String(_deviceCode) + "\"}";

  NH_LOG("🔑 Authenticating: %s", url.c_str());

  HTTPClient http;
  http.begin(_tlsHttp, url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.setTimeout(10000); // 10s timeout

  int code = http.POST(body);
  String resp = http.getString();
  http.end();

  if (code != 200) {
    NH_LOG("❌ Auth failed (HTTP %d)", code);
    if (code == 403) {
      NH_LOG("   → Device not approved. Approve it in admin panel first.");
    } else if (code == 404) {
      NH_LOG("   → Device code not found. Check DEVICE_CODE.");
    }
    return false;
  }

  // Parse JSON response
  #if defined(ESP8266)
    DynamicJsonDocument doc(2048);
  #else
    JsonDocument doc;
  #endif
  
  DeserializationError err = deserializeJson(doc, resp);
  if (err) {
    NH_LOG("❌ JSON parse error: %s", err.c_str());
    return false;
  }

  if (!doc["success"].as<bool>()) {
    const char* msg = doc["message"] | "Unknown error";
    NH_LOG("❌ Auth rejected: %s", msg);
    return false;
  }

  // Extract MQTT config
  strlcpy(_mqttHost, doc["mqtt"]["host"] | "", sizeof(_mqttHost));
  strlcpy(_mqttUser, doc["mqtt"]["username"] | "", sizeof(_mqttUser));
  strlcpy(_mqttPass, doc["mqtt"]["password"] | "", sizeof(_mqttPass));
  strlcpy(_mqttBase, doc["topics"]["base"] | "", sizeof(_mqttBase));
  _mqttPort = doc["mqtt"]["port"] | 8883;

  // Extract API token
  const char* tok = doc["api_token"] | "";
  if (strlen(tok) == 0) {
    NH_LOG("❌ No api_token in auth response");
    return false;
  }
  strlcpy(_apiToken, tok, sizeof(_apiToken));

  _authOk = true;
  _apiRetryCount = 0;
  NH_LOG("✅ Authenticated! MQTT: %s:%d", _mqttHost, _mqttPort);
  NH_LOG("   Topic base: %s", _mqttBase);

  return true;
}

// ═════════════════════════════════════════════════════════════════════
//  MQTT Connect
// ═════════════════════════════════════════════════════════════════════

bool NHSmartClass::mqttConnect() {
  if (!_authOk || WiFi.status() != WL_CONNECTED) return false;

  _mqtt->setServer(_mqttHost, _mqttPort);
  _mqtt->setCallback(_nhMqttCallback);

  // Generate unique client ID
  char cid[48];
  #if defined(ESP8266)
    snprintf(cid, sizeof(cid), "NH8266-%06X-%lu", ESP.getChipId(), millis() % 10000);
  #elif defined(ESP32)
    uint32_t mac = (uint32_t)(ESP.getEfuseMac() >> 32);
    snprintf(cid, sizeof(cid), "NH32-%06X-%lu", mac, millis() % 10000);
  #endif

  // LWT (Last Will and Testament) — auto offline status
  char lwtTopic[96];
  snprintf(lwtTopic, sizeof(lwtTopic), "%s/status", _mqttBase);

  NH_LOG("📡 MQTT connecting as %s...", cid);

  if (_mqtt->connect(cid, _mqttUser, _mqttPass, lwtTopic, 1, true, "offline")) {
    NH_LOG("✅ MQTT Connected!");
    _mqttRetryCount = 0;

    // Subscribe to control commands
    char ctrlTopic[96];
    snprintf(ctrlTopic, sizeof(ctrlTopic), "%s/control/#", _mqttBase);
    _mqtt->subscribe(ctrlTopic);
    NH_LOG("   Subscribed: %s", ctrlTopic);

    // Publish online status
    _mqtt->publish(lwtTopic, "online", true);
    NH_LOG("   Status: online (LWT configured)");

    return true;
  }

  NH_LOG("❌ MQTT connect failed, rc=%d", _mqtt->state());
  return false;
}

// ═════════════════════════════════════════════════════════════════════
//  loop() — Main Loop Handler
// ═════════════════════════════════════════════════════════════════════

void NHSmartClass::loop() {
  // 1. WiFi reconnect
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    return;
  }

  // 2. API re-auth with exponential backoff
  if (!_authOk) {
    unsigned long backoff = min(120000UL, 5000UL * (1UL << min(_apiRetryCount, (uint8_t)5)));
    if (millis() - _lastApiRetry > backoff) {
      _lastApiRetry = millis();
      _apiRetryCount++;
      NH_LOG("🔄 Re-auth attempt #%d (backoff: %lums)", _apiRetryCount, backoff);
      apiAuth();
    }
    return;
  }

  // 3. MQTT reconnect with exponential backoff
  if (!_mqtt->connected()) {
    unsigned long backoff = min(60000UL, 3000UL * (1UL << min(_mqttRetryCount, (uint8_t)4)));
    if (millis() - _lastMqttRetry > backoff) {
      _lastMqttRetry = millis();
      _mqttRetryCount++;
      NH_LOG("🔄 MQTT reconnect attempt #%d (backoff: %lums)", _mqttRetryCount, backoff);
      mqttConnect();
    }
  } else {
    _mqtt->loop();
  }
}

// ═════════════════════════════════════════════════════════════════════
//  onWrite() — Register Control Callback
// ═════════════════════════════════════════════════════════════════════

void NHSmartClass::onWrite(const char* key, NHWriteCallback callback) {
  if (_callbackCount >= NHSMART_MAX_CALLBACKS) {
    NH_LOG("⚠ Max callbacks reached (%d). Cannot register '%s'", NHSMART_MAX_CALLBACKS, key);
    return;
  }
  strlcpy(_callbacks[_callbackCount].key, key, NHSMART_KEY_LEN);
  _callbacks[_callbackCount].callback = callback;
  _callbackCount++;
  NH_LOG("📝 Registered: onWrite(\"%s\") [%d/%d]", key, _callbackCount, NHSMART_MAX_CALLBACKS);
}

// ═════════════════════════════════════════════════════════════════════
//  handleMqttMessage() — Internal MQTT Router
// ═════════════════════════════════════════════════════════════════════

void NHSmartClass::handleMqttMessage(char* topic, String message) {
  String t = String(topic);
  
  for (int i = 0; i < _callbackCount; i++) {
    String expected = String(_mqttBase) + "/control/" + _callbacks[i].key;
    if (t == expected) {
      NH_LOG("📥 Control: %s = %s", _callbacks[i].key, message.c_str());
      
      // Execute user callback
      if (_callbacks[i].callback) {
        _callbacks[i].callback(message);
      }

      // ACK — echo back to sensors topic so dashboard updates
      char ackTopic[96];
      snprintf(ackTopic, sizeof(ackTopic), "%s/sensors/%s", _mqttBase, _callbacks[i].key);
      _mqtt->publish(ackTopic, message.c_str(), true);
      
      return;
    }
  }

  NH_LOG("📥 Unhandled topic: %s", topic);
}

// ═════════════════════════════════════════════════════════════════════
//  virtualWrite() — Send Data to Dashboard
// ═════════════════════════════════════════════════════════════════════

void NHSmartClass::virtualWrite(const char* key, const char* value) {
  if (!_authOk || !_mqtt->connected()) return;
  char topic[96];
  snprintf(topic, sizeof(topic), "%s/sensors/%s", _mqttBase, key);
  _mqtt->publish(topic, value, true);
}

void NHSmartClass::virtualWrite(const char* key, int value) {
  char buf[16];
  snprintf(buf, sizeof(buf), "%d", value);
  virtualWrite(key, buf);
}

void NHSmartClass::virtualWrite(const char* key, float value, int dec) {
  char buf[16];
  switch (dec) {
    case 0:  snprintf(buf, sizeof(buf), "%.0f", value); break;
    case 1:  snprintf(buf, sizeof(buf), "%.1f", value); break;
    case 3:  snprintf(buf, sizeof(buf), "%.3f", value); break;
    default: snprintf(buf, sizeof(buf), "%.2f", value); break;
  }
  virtualWrite(key, buf);
}

void NHSmartClass::virtualWrite(const char* key, double value, int dec) {
  virtualWrite(key, (float)value, dec);
}

void NHSmartClass::virtualWrite(const char* key, const String& value) {
  virtualWrite(key, value.c_str());
}

// ═════════════════════════════════════════════════════════════════════
//  Status Methods
// ═════════════════════════════════════════════════════════════════════

bool NHSmartClass::connected() {
  return _mqtt && _mqtt->connected();
}

bool NHSmartClass::isAuthenticated() {
  return _authOk;
}

int NHSmartClass::rssi() {
  return WiFi.RSSI();
}

unsigned long NHSmartClass::uptime() {
  return (millis() - _bootTime) / 1000;
}

// ═════════════════════════════════════════════════════════════════════
//  fetchWidgets() — Discover Widgets from Server API
// ═════════════════════════════════════════════════════════════════════

int NHSmartClass::fetchWidgets(const char* filterType, NHWidget* widgets, int maxWidgets) {
  if (!_authOk || WiFi.status() != WL_CONNECTED) {
    NH_LOG("❌ fetchWidgets: not authenticated or no WiFi");
    return -1;
  }

  // GET /api/devices/{code}/widgets?lite=1
  String url = String(_serverUrl) + "/api/devices/" + String(_deviceCode) + "/widgets?lite=1";

  NH_LOG("📋 Fetching widgets: %s", url.c_str());

  HTTPClient http;
  http.begin(_tlsHttp, url);
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + String(_apiToken));
  http.setTimeout(10000);

  int code = http.GET();
  if (code != 200) {
    NH_LOG("❌ fetchWidgets failed (HTTP %d)", code);
    http.end();
    return -1;
  }

  String resp = http.getString();
  http.end();

  // Parse JSON
  #if defined(ESP8266)
    DynamicJsonDocument doc(4096);
  #else
    JsonDocument doc;
  #endif

  DeserializationError err = deserializeJson(doc, resp);
  if (err) {
    NH_LOG("❌ Widget JSON parse error: %s", err.c_str());
    return -1;
  }

  if (!doc["success"].as<bool>()) {
    NH_LOG("❌ Widget fetch rejected");
    return -1;
  }

  // Parse widgets object: { "key1": {name, type, value}, "key2": ... }
  JsonObject widgetsObj = doc["widgets"].as<JsonObject>();
  int count = 0;
  bool doFilter = (filterType != nullptr && strlen(filterType) > 0);

  for (JsonPair kv : widgetsObj) {
    if (count >= maxWidgets) break;

    const char* wKey  = kv.key().c_str();
    const char* wType = kv.value()["type"] | "unknown";
    const char* wName = kv.value()["name"] | wKey;

    // Apply type filter
    if (doFilter && strcmp(wType, filterType) != 0) continue;

    strlcpy(widgets[count].key,  wKey,  NHSMART_KEY_LEN);
    strlcpy(widgets[count].name, wName, NHSMART_LABEL_LEN);
    strlcpy(widgets[count].type, wType, sizeof(widgets[count].type));
    count++;
  }

  NH_LOG("✅ Found %d widgets (filter: %s)", count, doFilter ? filterType : "all");
  return count;
}
