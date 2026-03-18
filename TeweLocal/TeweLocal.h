// ============================================================
// 📦 TeweLocal.h — ESP8266 Offline-First Smart Home Controller
// ============================================================
//
// Controls relays via local Web UI (browser), no internet needed.
// Auto-syncs to Laravel cloud when internet returns.
//
// Dependencies (Install via Library Manager):
//   - ESPAsyncTCP          by dvarrel
//   - ESPAsyncWebServer    by lacamera
//   - ArduinoJson          by Benoit Blanchon (v7.x)
//   - PubSubClient         by Nick O'Leary
//   Board: esp8266 by ESP8266 Community
//
// ============================================================

#ifndef TEWE_LOCAL_H
#define TEWE_LOCAL_H

#include <Arduino.h>
#include <ArduinoJson.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>
#include <ESPAsyncTCP.h>
#include <ESPAsyncWebServer.h>
#include <LittleFS.h>
#include <PubSubClient.h>
#include <WiFiClientSecureBearSSL.h>

// ── Limits ──────────────────────────────────────────────────
#define TL_MAX_WIDGETS 16
#define TL_MAX_QUEUE 30
#define TL_MQTT_BUFFER 512

// ── Widget types ────────────────────────────────────────────
enum TLWidgetType { TL_TOGGLE, TL_SLIDER, TL_GAUGE, TL_TEXT, TL_UNKNOWN };

// ── Widget data ─────────────────────────────────────────────
struct TLWidget {
  String key;
  String name;
  TLWidgetType type;
  int pin; // -1 = no physical pin
  bool boolVal;
  int intVal;
  float floatVal;
  String strVal;
  int minVal, maxVal;
  String icon;
  String color;
};

// ── Offline sync queue entry ────────────────────────────────
struct TLQueueEntry {
  String key;
  String value;
  unsigned long ts;
};

// ── Config (stored in LittleFS) ─────────────────────────────
struct TLConfig {
  char wifiSSID[64];
  char wifiPass[64];
  char apSSID[32];
  char apPass[32];
  char apiBase[128];
  char deviceCode[32];
  char mqttHost[128];
  int mqttPort;
  char mqttUser[64];
  char mqttPass[64];
  int userId;
  bool relayActiveLow;
};

// ── Callback ────────────────────────────────────────────────
typedef void (*TLWidgetCallback)(const char *key, const char *value);

// ============================================================
// 🏗️  TeweLocal Class
// ============================================================
class TeweLocal {
public:
  TeweLocal();

  // ── Setup ─────────────────────────────────────────────────
  void setWiFi(const char *ssid, const char *pass);
  void setAP(const char *ssid, const char *pass);
  void setAPI(const char *apiBase, const char *deviceCode);
  void setRelayActiveLow(bool activeLow);
  void mapPin(const char *key, int pin);
  void onWidget(TLWidgetCallback cb);

  /// Initialize everything: WiFi, LittleFS, WebServer, MQTT
  void begin();

  /// Call in loop()
  void run();

  // ── Widget Control ────────────────────────────────────────
  bool setWidget(const char *key, const char *value);
  bool toggleWidget(const char *key);
  String getWidgetValue(const char *key);

  // ── Status ────────────────────────────────────────────────
  bool isWiFiConnected();
  bool isInternetAvailable();
  bool isMqttConnected();
  int getWidgetCount();
  int getQueueSize();

private:
  // ── Config ────────────────────────────────────────────────
  TLConfig _cfg;

  // ── Widgets ───────────────────────────────────────────────
  TLWidget _widgets[TL_MAX_WIDGETS];
  int _widgetCount;

  // ── Pin map (before begin) ────────────────────────────────
  struct PinEntry {
    const char *key;
    int pin;
  };
  PinEntry _pinMap[TL_MAX_WIDGETS];
  int _pinMapCount;

  // ── Offline Queue ─────────────────────────────────────────
  TLQueueEntry _queue[TL_MAX_QUEUE];
  int _queueHead, _queueTail;

  // ── Callback ──────────────────────────────────────────────
  TLWidgetCallback _widgetCb;

  // ── Network ───────────────────────────────────────────────
  AsyncWebServer _server;
  AsyncWebSocket _ws;
  BearSSL::WiFiClientSecure _mqttSecure;
  PubSubClient _mqtt;
  bool _hasInternet;
  bool _mqttConfigured;

  // ── Timing ────────────────────────────────────────────────
  unsigned long _lastSync;
  unsigned long _lastHeartbeat;
  unsigned long _lastPing;

  // ── Internal ──────────────────────────────────────────────
  void _setupWiFi();
  void _setupServer();
  void _setupMQTT();

  bool _authenticate();
  bool _fetchWidgets();
  bool _checkInternet();
  void _flushQueue();
  void _syncToCloud();

  int _findWidget(const char *key);
  void _applyPin(TLWidget &w);
  void _broadcastWS(const char *key, const char *value);
  void _broadcastWSAll();
  void _enqueue(const char *key, const char *value);
  String _widgetsToJson();
  String _statusToJson();

  // MQTT
  void _mqttReconnect();
  void _publishMQTT(const char *key, const char *value);
  static void _mqttCallback(char *topic, byte *payload, unsigned int len);
  static TeweLocal *_instance;
};

#endif // TEWE_LOCAL_H
