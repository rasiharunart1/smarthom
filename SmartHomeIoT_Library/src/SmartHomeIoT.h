#ifndef SmartHomeIoT_h
#define SmartHomeIoT_h

#include <Arduino.h>
#include <ArduinoJson.h>
#include <PubSubClient.h>

#if defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
  #include <WiFiClientSecureBearSSL.h>
#elif defined(ESP32)
  #include <WiFi.h>
  #include <HTTPClient.h>
  #include <WiFiClientSecure.h>
#else
  #error "This library only supports ESP8266 and ESP32"
#endif

// Tipe untuk callback (Mirip BLYNK_WRITE)
typedef void (*WriteCallback)(String value);

struct SmartHomeCallback {
  char key[32];
  WriteCallback callback;
};

// Simple Timer (Mirip BlynkTimer)
class SmartHomeTimer {
public:
  SmartHomeTimer() { lastRun = 0; interval = 1000; cb = nullptr; }
  void setInterval(unsigned long ms, void (*callback)()) {
    interval = ms;
    cb = callback;
  }
  void run() {
    if (cb && (millis() - lastRun >= interval)) {
      lastRun = millis();
      cb();
    }
  }
private:
  unsigned long lastRun;
  unsigned long interval;
  void (*cb)();
};

class SmartHomeIoTClass {
public:
  SmartHomeIoTClass();
  
  // Initialize with manual WiFi and Device Code (Blynk Style)
  void begin(const char* deviceCode, const char* ssid, const char* pass, const char* serverUrl);
  
  // Wajib dipanggil di loop()
  void loop();

  // Mengirim data ke Dashboard (Mirip Blynk.virtualWrite)
  void virtualWrite(const char* key, float value, int dec = 2);
  void virtualWrite(const char* key, int value);
  void virtualWrite(const char* key, const char* value);
  
  // Mendaftarkan aksi / control (Mirip BLYNK_WRITE)
  void onWrite(const char* key, WriteCallback callback);

  // [SECURITY FIX C-3] Set CA certificate for production TLS verification
  // Call BEFORE begin() to disable insecure mode.
  void setCACert(const char* caCert);

private:
  char _ssid[64];
  char _pass[64];
  char _deviceCode[32];
  char _serverUrl[96];

  // MQTT Runtime
  char _mqttHost[64];
  int  _mqttPort;
  char _mqttUser[64];
  char _mqttPass[64];
  char _mqttBase[96];
  char _apiToken[512]; // [SECURITY FIX Q-8] Sanctum tokens can exceed 256 chars
  bool _authOk;

  unsigned long _lastMqttRetry;
  unsigned long _lastApiRetry;

  SmartHomeCallback _callbacks[15]; // Maksimal 15 control mapping
  int _callbackCount;

  #if defined(ESP8266)
    BearSSL::WiFiClientSecure _tlsHttp;
    BearSSL::WiFiClientSecure _tlsMqtt;
  #elif defined(ESP32)
    WiFiClientSecure _tlsHttp;
    WiFiClientSecure _tlsMqtt;
  #endif

  PubSubClient* _mqtt;

  bool connectWiFi();
  bool apiAuth();
  bool mqttConnect();
  
  friend void _mqttCallbackInternal(char* topic, byte* payload, unsigned int length);
  void handleMqttMessage(char* topic, String message);
};

// Expose global instance (Mirip Blynk)
extern SmartHomeIoTClass SmartHome;

#endif
