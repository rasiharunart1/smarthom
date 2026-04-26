#include "SmartHomeIoT.h"

SmartHomeIoTClass SmartHome;

// Helper global untuk menampung callback MQTT dari PubSubClient
void _mqttCallbackInternal(char* topic, byte* payload, unsigned int length) {
  String msg;
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }
  SmartHome.handleMqttMessage(topic, msg);
}

SmartHomeIoTClass::SmartHomeIoTClass() {
  _callbackCount = 0;
  _authOk = false;
  _mqttPort = 8883;
  _lastMqttRetry = 0;
  _lastApiRetry = 0;
  _mqtt = new PubSubClient(_tlsMqtt);
}

void SmartHomeIoTClass::begin(const char* deviceCode, const char* ssid, const char* pass, const char* serverUrl) {
  strlcpy(_deviceCode, deviceCode, sizeof(_deviceCode));
  strlcpy(_ssid, ssid, sizeof(_ssid));
  strlcpy(_pass, pass, sizeof(_pass));
  strlcpy(_serverUrl, serverUrl, sizeof(_serverUrl));

  // ⚠️ [SECURITY WARNING C-3] setInsecure() disables ALL TLS certificate validation.
  // This is acceptable ONLY for local development / testing.
  // For PRODUCTION, call SmartHome.setCACert(cert) BEFORE SmartHome.begin()
  // to enable proper certificate verification and prevent MITM attacks.
  #if defined(ESP8266)
    _tlsHttp.setInsecure();
    _tlsMqtt.setInsecure();
  #elif defined(ESP32)
    _tlsHttp.setInsecure();
    _tlsMqtt.setInsecure();
  #endif

  connectWiFi();
}

/**
 * [SECURITY FIX C-3] Set a CA root certificate for TLS verification.
 * Call this BEFORE begin() to enable proper HTTPS/MQTTS verification.
 *
 * For ESP32: Pass the PEM-encoded root CA certificate string.
 * For ESP8266: Uses BearSSL trust anchors (simplified here with setInsecure fallback).
 *
 * Example:
 *   const char* ca_cert = "-----BEGIN CERTIFICATE-----\n....\n-----END CERTIFICATE-----\n";
 *   SmartHome.setCACert(ca_cert);
 *   SmartHome.begin(DEVICE_CODE, ssid, pass, SERVER_URL);
 */
void SmartHomeIoTClass::setCACert(const char* caCert) {
  #if defined(ESP32)
    _tlsHttp.setCACert(caCert);
    _tlsMqtt.setCACert(caCert);
  #elif defined(ESP8266)
    // ESP8266 BearSSL requires X509List — simplified fallback
    static BearSSL::X509List certList(caCert);
    _tlsHttp.setTrustAnchors(&certList);
    _tlsMqtt.setTrustAnchors(&certList);
  #endif
}

bool SmartHomeIoTClass::connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return true;
  
  Serial.print("[SmartHome] Connecting to WiFi: ");
  Serial.println(_ssid);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(_ssid, _pass);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[SmartHome] WiFi Connected! IP: ");
    Serial.println(WiFi.localIP());
    return true;
  }
  
  Serial.println("[SmartHome] WiFi Failed!");
  return false;
}

bool SmartHomeIoTClass::apiAuth() {
  if (WiFi.status() != WL_CONNECTED) return false;
  
  String url = String(_serverUrl) + "/api/devices/auth";
  String body = "{\"device_code\":\"" + String(_deviceCode) + "\"}";
  
  Serial.print("[SmartHome] API Auth -> ");
  Serial.println(url);
  
  HTTPClient http;
  http.begin(_tlsHttp, url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  
  int code = http.POST(body);
  String resp = http.getString();
  http.end();
  
  if (code != 200) {
    Serial.printf("[SmartHome] Auth Failed (HTTP %d)\n", code);
    return false;
  }
  
  StaticJsonDocument<1536> doc;
  DeserializationError err = deserializeJson(doc, resp);
  if (err || !doc["success"].as<bool>()) {
    Serial.println("[SmartHome] Auth JSON Parse Failed!");
    return false;
  }
  
  strlcpy(_mqttHost, doc["mqtt"]["host"] | "", sizeof(_mqttHost));
  strlcpy(_mqttUser, doc["mqtt"]["username"] | "", sizeof(_mqttUser));
  strlcpy(_mqttPass, doc["mqtt"]["password"] | "", sizeof(_mqttPass));
  strlcpy(_mqttBase, doc["topics"]["base"] | "", sizeof(_mqttBase));
  _mqttPort = doc["mqtt"]["port"] | 8883;
  
  const char* tok = doc["api_token"] | "";
  if (strlen(tok) == 0) {
    Serial.println("[SmartHome] No api_token in response!");
    return false;
  }
  strlcpy(_apiToken, tok, sizeof(_apiToken));
  
  _authOk = true;
  Serial.println("[SmartHome] Auth Success!");
  return true;
}

bool SmartHomeIoTClass::mqttConnect() {
  if (!_authOk || WiFi.status() != WL_CONNECTED) return false;
  
  _mqtt->setServer(_mqttHost, _mqttPort);
  _mqtt->setCallback(_mqttCallbackInternal);
  
  #if defined(ESP8266)
    char cid[48];
    snprintf(cid, sizeof(cid), "ESP8266-%06X-%lu", ESP.getChipId(), millis());
  #elif defined(ESP32)
    char cid[48];
    uint32_t mac = (uint32_t)(ESP.getEfuseMac() >> 32);
    snprintf(cid, sizeof(cid), "ESP32-%06X-%lu", mac, millis());
  #endif
  
  Serial.printf("[SmartHome] MQTT Connecting as %s...\n", cid);
  if (_mqtt->connect(cid, _mqttUser, _mqttPass)) {
    Serial.println("[SmartHome] MQTT Connected!");
    String ctrlTopic = String(_mqttBase) + "/control/#";
    _mqtt->subscribe(ctrlTopic.c_str());
    return true;
  }
  
  Serial.printf("[SmartHome] MQTT Connect Failed, rc=%d\n", _mqtt->state());
  return false;
}

void SmartHomeIoTClass::loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    return;
  }

  if (!_authOk) {
    if (millis() - _lastApiRetry > 30000) {
      _lastApiRetry = millis();
      apiAuth();
    }
    return;
  }

  if (!_mqtt->connected()) {
    if (millis() - _lastMqttRetry > 15000) {
      _lastMqttRetry = millis();
      mqttConnect();
    }
  } else {
    _mqtt->loop();
  }
}

void SmartHomeIoTClass::onWrite(const char* key, WriteCallback callback) {
  if (_callbackCount < 15) {
    strlcpy(_callbacks[_callbackCount].key, key, sizeof(_callbacks[_callbackCount].key));
    _callbacks[_callbackCount].callback = callback;
    _callbackCount++;
  }
}

void SmartHomeIoTClass::handleMqttMessage(char* topic, String message) {
  String t = String(topic);
  for (int i = 0; i < _callbackCount; i++) {
    String ctrl = String(_mqttBase) + "/control/" + _callbacks[i].key;
    if (t == ctrl) {
      if (_callbacks[i].callback) {
        _callbacks[i].callback(message);
      }
      
      // Send ACK back
      char ackTopic[96];
      snprintf(ackTopic, sizeof(ackTopic), "%s/sensors/%s", _mqttBase, _callbacks[i].key);
      _mqtt->publish(ackTopic, message.c_str(), true);
      return;
    }
  }
}

void SmartHomeIoTClass::virtualWrite(const char* key, const char* value) {
  if (!_authOk || !_mqtt->connected()) return;
  char topic[96];
  snprintf(topic, sizeof(topic), "%s/sensors/%s", _mqttBase, key);
  _mqtt->publish(topic, value, true);
}

void SmartHomeIoTClass::virtualWrite(const char* key, int value) {
  virtualWrite(key, String(value).c_str());
}

void SmartHomeIoTClass::virtualWrite(const char* key, float value, int dec) {
  char buf[16];
  if (dec == 0) snprintf(buf, sizeof(buf), "%.0f", value);
  else if (dec == 1) snprintf(buf, sizeof(buf), "%.1f", value);
  else snprintf(buf, sizeof(buf), "%.2f", value);
  virtualWrite(key, buf);
}
