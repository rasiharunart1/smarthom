// ============================================================
// 📦 Tewe.h — Blynk-like library for MDPower IoT Platform
// ============================================================
//
// Supported widgets: Toggle, Slider, Gauge, Text
//
// Library yang dibutuhkan (Install via Library Manager):
//   - PubSubClient      by Nick O'Leary
//   - ArduinoJson       by Benoit Blanchon (v7.x)
//   Board: esp8266 by ESP8266 Community
//   URL  : http://arduino.esp8266.com/stable/package_esp8266com_index.json
//
// ============================================================

#ifndef TEWE_H
#define TEWE_H

#include <Arduino.h>
#include <ArduinoJson.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <WiFiClientSecureBearSSL.h>

// ── Limits ──────────────────────────────────────────────────
#define TEWE_MAX_WIDGETS 20
#define TEWE_MAX_PIN_MAP 15
#define TEWE_MQTT_BUFFER 512

// ── Widget types ───────────────────────────────────────────
enum TeweWidgetType {
  TEWE_TOGGLE,
  TEWE_SLIDER,
  TEWE_GAUGE,
  TEWE_TEXT,
  TEWE_UNKNOWN
};

// ── Generic widget data ────────────────────────────────────
struct TeweWidget {
  String key;
  String name;
  TeweWidgetType type;
  int pin;             // -1 = no physical pin (toggle only)
  String topicControl; // subscribe (from dashboard)
  String topicSensor;  // publish   (to dashboard)
  bool valid;

  // Value storage (union-like, based on type)
  bool boolVal;   // toggle state
  int intVal;     // slider value
  float floatVal; // gauge value
  String strVal;  // text value

  // Slider config
  int sliderMin;
  int sliderMax;
};

// ── Callback types ─────────────────────────────────────────
typedef void (*TeweToggleCallback)(const char *key, bool state);
typedef void (*TeweSliderCallback)(const char *key, int value);
typedef void (*TeweTextCallback)(const char *key, const char *value);

// ============================================================
// 🏗️  Tewe Class
// ============================================================
class Tewe {
public:
  Tewe();

  // ── Setup (call before begin) ────────────────────────────
  /// Map a widget key to a GPIO pin (for toggles).
  void mapPin(const char *widgetKey, int gpioPin);

  /// Set relay logic. true = ACTIVE LOW (default), false = ACTIVE HIGH.
  void setRelayActiveLow(bool activeLow);

  /// Set heartbeat interval in ms (default: 60000).
  void setHeartbeatInterval(unsigned long ms);

  /// Register callbacks for each widget type.
  void onToggle(TeweToggleCallback cb);
  void onSlider(TeweSliderCallback cb);
  void onText(TeweTextCallback cb);

  /// Connect WiFi → Auth API → Fetch Widgets → MQTT connect.
  void begin(const char *wifiSSID, const char *wifiPass, const char *apiBaseUrl,
             const char *deviceCode);

  // ── Loop ─────────────────────────────────────────────────
  /// Call in loop(). Handles MQTT, reconnect, heartbeat, serial.
  void run();

  // ── Toggle Control ───────────────────────────────────────
  /// Set a toggle state and publish.
  bool setState(const char *key, bool state);

  /// Flip a toggle and publish.
  bool toggle(const char *key);

  // ── Slider Control ───────────────────────────────────────
  /// Set slider value and publish.
  bool setSlider(const char *key, int value);

  /// Get current slider value.
  int getSlider(const char *key);

  // ── Gauge (publish only) ─────────────────────────────────
  /// Publish a float value to a gauge widget.
  bool publishGauge(const char *key, float value);

  // ── Text ─────────────────────────────────────────────────
  /// Set text value and publish.
  bool setText(const char *key, const char *value);

  /// Get current text value.
  String getText(const char *key);

  // ── Generic Publish ──────────────────────────────────────
  /// Publish any widget's current value.
  bool publish(const char *key);

  /// Re-publish all widget values.
  void publishAll();

  /// Publish a raw string to any widget's sensor topic.
  bool publishRaw(const char *key, const char *value);

  // ── Status ───────────────────────────────────────────────
  bool isWifiConnected();
  bool isMqttConnected();
  int getWidgetCount();
  int getToggleCount();
  bool getState(const char *key);
  int getWidgetIndex(const char *key);
  TeweWidgetType getWidgetType(const char *key);

private:
  // ── MQTT credentials (HIDDEN from user) ──────────────────
  String _mqttHost;
  int _mqttPort;
  String _mqttUser;
  String _mqttPass;
  int _userId;

  // ── Config ───────────────────────────────────────────────
  const char *_wifiSSID;
  const char *_wifiPass;
  const char *_apiBase;
  const char *_deviceCode;
  bool _relayActiveLow;
  unsigned long _heartbeatInterval;

  // ── Pin map (set before begin) ───────────────────────────
  struct PinEntry {
    const char *key;
    int pin;
  };
  PinEntry _pinMap[TEWE_MAX_PIN_MAP];
  int _pinMapCount;

  // ── Widgets ──────────────────────────────────────────────
  TeweWidget _widgets[TEWE_MAX_WIDGETS];
  int _widgetCount;

  // ── Topics ───────────────────────────────────────────────
  String _topicHeartbeat;

  // ── Callbacks ────────────────────────────────────────────
  TeweToggleCallback _toggleCb;
  TeweSliderCallback _sliderCb;
  TeweTextCallback _textCb;

  // ── Timing ───────────────────────────────────────────────
  unsigned long _lastHeartbeat;
  unsigned long _lastConnCheck;

  // ── Network clients ──────────────────────────────────────
  WiFiClient _httpClient;
  BearSSL::WiFiClientSecure _httpsClient;
  BearSSL::WiFiClientSecure _mqttSecure;
  PubSubClient _mqtt;

  // ── Internal methods ─────────────────────────────────────
  bool _apiIsHttps();
  void _connectWiFi();
  bool _authenticate();
  bool _fetchWidgets();
  bool _connectMQTT();
  void _reconnectMQTT();
  void _applyPin(TeweWidget &w);
  void _publishWidget(TeweWidget &w);
  void _sendHeartbeat();
  void _handleSerial();
  TeweWidgetType _parseType(const String &type);

  // ── Static callback trampoline ───────────────────────────
  static void _mqttCallbackStatic(char *topic, byte *payload, unsigned int len);
  static Tewe *_instance;
};

#endif // TEWE_H
