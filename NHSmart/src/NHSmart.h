/***********************************************************************
 *  NHSmart — Blynk-Style IoT Library for Tewe.io Platform
 *  
 *  Version : 1.0.0
 *  Author  : Tewe.io Team
 *  Support : ESP32, ESP8266
 *  License : MIT
 *
 *  ┌─────────────────────────────────────────────────────────┐
 *  │  NHSmart.begin(CODE, ssid, pass, url)   // Connect     │
 *  │  NHSmart.virtualWrite(key, value)       // Send data   │
 *  │  NHSmart.onWrite(key, callback)         // Receive cmd │
 *  │  NHSmart.loop()                         // Keep alive  │
 *  └─────────────────────────────────────────────────────────┘
 *
 ***********************************************************************/

#ifndef NHSmart_h
#define NHSmart_h

#include <Arduino.h>
#include <ArduinoJson.h>
#include <PubSubClient.h>

// ─── Platform Detection ──────────────────────────────────────────────
#if defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
  #include <WiFiClientSecureBearSSL.h>
#elif defined(ESP32)
  #include <WiFi.h>
  #include <HTTPClient.h>
  #include <WiFiClientSecure.h>
#else
  #error "NHSmart only supports ESP32 and ESP8266"
#endif

// ─── Configuration ───────────────────────────────────────────────────
#define NHSMART_MAX_CALLBACKS     64    // Max onWrite() handlers (for PCF8574 64ch)
#define NHSMART_MAX_TIMERS        8     // Max simultaneous timers
#define NHSMART_KEY_LEN           32    // Max widget key length
#define NHSMART_LABEL_LEN         24    // Max widget label length
#define NHSMART_TOKEN_LEN         512   // Sanctum token buffer
#define NHSMART_MQTT_BUFFER       512   // MQTT packet size
#define NHSMART_MAX_WIDGETS       64    // Max widgets from API

// ─── Debug Logging ───────────────────────────────────────────────────
// Define NHSMART_DEBUG before #include <NHSmart.h> to enable debug logs
// Example: #define NHSMART_DEBUG 1
#ifndef NHSMART_DEBUG
  #define NHSMART_DEBUG 1
#endif

#if NHSMART_DEBUG
  #define NH_LOG(fmt, ...)   Serial.printf("[NHSmart] " fmt "\n", ##__VA_ARGS__)
  #define NH_PRINT(x)        Serial.print(x)
  #define NH_PRINTLN(x)      Serial.println(x)
#else
  #define NH_LOG(fmt, ...)
  #define NH_PRINT(x)
  #define NH_PRINTLN(x)
#endif

// ─── Callback Type ───────────────────────────────────────────────────
typedef void (*NHWriteCallback)(String value);

struct NHCallback {
  char key[NHSMART_KEY_LEN];
  NHWriteCallback callback;
};

// ─── Widget Descriptor ───────────────────────────────────────────────
struct NHWidget {
  char key[NHSMART_KEY_LEN];    // e.g. "toggle1"
  char name[NHSMART_LABEL_LEN]; // e.g. "Lampu Tamu"
  char type[12];                // "toggle", "gauge", "slider", "text"
};

// ═════════════════════════════════════════════════════════════════════
//  NHTimer — Multi-slot Timer (like BlynkTimer)
// ═════════════════════════════════════════════════════════════════════

class NHTimer {
public:
  NHTimer();

  /**
   * Register a function to run at a fixed interval.
   * Returns timer ID (0..MAX-1) or -1 if full.
   *
   * Example:
   *   timer.setInterval(2000, sendSensor);   // every 2s
   *   timer.setInterval(10000, sendStatus);  // every 10s
   */
  int setInterval(unsigned long ms, void (*callback)());

  /**
   * Register a one-shot timer (runs once after ms).
   * Returns timer ID or -1 if full.
   */
  int setTimeout(unsigned long ms, void (*callback)());

  /**
   * Disable a timer by its ID.
   */
  void disable(int id);

  /**
   * Enable a previously disabled timer.
   */
  void enable(int id);

  /**
   * Must be called in loop() to run scheduled tasks.
   */
  void run();

private:
  struct Slot {
    void (*cb)();
    unsigned long interval;
    unsigned long lastRun;
    bool enabled;
    bool oneShot;
  };
  Slot _slots[NHSMART_MAX_TIMERS];
  int _count;
};

// ═════════════════════════════════════════════════════════════════════
//  NHSmartClass — Main IoT Client
// ═════════════════════════════════════════════════════════════════════

class NHSmartClass {
public:
  NHSmartClass();

  // ── Connection ──────────────────────────────────────────────────

  /**
   * Initialize and connect to Tewe.io platform.
   * Handles: WiFi → HTTPS Auth → MQTT TLS — all automatic.
   *
   * @param deviceCode  Device code from dashboard (e.g. "DEV_XXXXXXXXXX")
   * @param ssid        WiFi network name
   * @param pass        WiFi password
   * @param serverUrl   Server URL (e.g. "https://your-server.com")
   */
  void begin(const char* deviceCode, const char* ssid, const char* pass, const char* serverUrl);

  /**
   * Must be called in loop() — keeps WiFi, Auth, MQTT alive.
   */
  void loop();

  /**
   * Check if MQTT is currently connected.
   */
  bool connected();

  // ── Sensor Data (ESP → Dashboard) ──────────────────────────────

  /**
   * Send a float value to a dashboard widget.
   *
   * @param key   Widget Key from dashboard (e.g. "temp1", "voltage")
   * @param value Float value to send
   * @param dec   Decimal places (default 2)
   */
  void virtualWrite(const char* key, float value, int dec = 2);
  void virtualWrite(const char* key, int value);
  void virtualWrite(const char* key, const char* value);
  void virtualWrite(const char* key, double value, int dec = 2);
  void virtualWrite(const char* key, const String& value);

  // ── Control Callbacks (Dashboard → ESP) ────────────────────────

  /**
   * Register a callback for when a widget value changes on the dashboard.
   * Similar to Blynk's BLYNK_WRITE(). 
   *
   * @param key       Widget Key to listen for (e.g. "toggle1", "slider1")
   * @param callback  Function to call: void myFunc(String value) { ... }
   *
   * Example:
   *   NHSmart.onWrite("toggle1", [](String v) {
   *     digitalWrite(RELAY, v.toInt());
   *   });
   */
  void onWrite(const char* key, NHWriteCallback callback);

  // ── Security ───────────────────────────────────────────────────

  /**
   * Set root CA certificate for production TLS verification.
   * Call BEFORE begin(). Without this, TLS is encrypted but
   * does not verify the server identity (vulnerable to MITM).
   *
   * @param caCert  PEM-encoded root CA certificate string
   */
  void setCACert(const char* caCert);

  // ── Widget Discovery ────────────────────────────────────────────

  /**
   * Fetch widgets from server API and filter by type.
   * Requires authenticated session (call after begin()).
   *
   * @param filterType  Widget type to filter: "toggle", "gauge", "slider", etc.
   *                    Pass NULL or "" to get all types.
   * @param widgets     Output array to fill
   * @param maxWidgets  Max items in output array
   * @return            Number of widgets found, or -1 on error
   *
   * Example:
   *   NHWidget toggles[64];
   *   int count = NHSmart.fetchWidgets("toggle", toggles, 64);
   */
  int fetchWidgets(const char* filterType, NHWidget* widgets, int maxWidgets);

  // ── Status ─────────────────────────────────────────────────────
  
  bool isAuthenticated();
  int rssi();
  unsigned long uptime();
  const char* deviceCode() { return _deviceCode; }
  const char* serverUrl()  { return _serverUrl; }

private:
  char _ssid[64];
  char _pass[64];
  char _deviceCode[32];
  char _serverUrl[128];

  // MQTT runtime
  char _mqttHost[64];
  int  _mqttPort;
  char _mqttUser[64];
  char _mqttPass[64];
  char _mqttBase[96];
  char _apiToken[NHSMART_TOKEN_LEN];
  bool _authOk;
  bool _caCertSet;

  // Reconnect timing
  unsigned long _lastMqttRetry;
  unsigned long _lastApiRetry;
  unsigned long _bootTime;
  uint8_t _mqttRetryCount;
  uint8_t _apiRetryCount;

  // Callbacks
  NHCallback _callbacks[NHSMART_MAX_CALLBACKS];
  int _callbackCount;

  // TLS clients
  #if defined(ESP8266)
    BearSSL::WiFiClientSecure _tlsHttp;
    BearSSL::WiFiClientSecure _tlsMqtt;
  #elif defined(ESP32)
    WiFiClientSecure _tlsHttp;
    WiFiClientSecure _tlsMqtt;
  #endif

  PubSubClient* _mqtt;

  // Internal methods
  bool connectWiFi();
  bool apiAuth();
  bool mqttConnect();

  friend void _nhMqttCallback(char* topic, byte* payload, unsigned int length);
  void handleMqttMessage(char* topic, String message);
};

// ─── Global Instance ─────────────────────────────────────────────────
extern NHSmartClass NHSmart;

#endif // NHSmart_h
