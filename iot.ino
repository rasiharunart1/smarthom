// ============================================================
// 📄 SmartDevice Library - User-Friendly OOP Version
// ============================================================
//
// USAGE EXAMPLE:
//
// #include "SmartDevice.h"
//
// SmartDevice device;
//
// void setup() {
//   device.begin();
//   device.setWiFi("MyWiFi", "password123");
//   device.setAPI("http://192.168.1.100:8000/api/devices", "DEV_ABC123");
//   device.addPin("lampu", 2, OUTPUT);
//   device.addPin("sensor suhu", 34, INPUT);
//   device.connect();
// }
//
// void loop() {
//   device.update();
// }
//
// ============================================================

#ifndef SMART_DEVICE_H
#define SMART_DEVICE_H

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <PubSubClient.h>
#include <WiFiClientSecure.h>

// ===== Widget Class =====
class Widget {
public:
  String key;
  String name;
  String type;
  String value;
  int pin;
  int displayIndex;
  float minValue;
  float maxValue;
  bool autoSetup;

  Widget() : pin(-1), displayIndex(0), minValue(0), maxValue(100), autoSetup(false) {}

  bool isValid() const { return pin >= 0 && autoSetup; }
  bool isSensor() const { return type == "gauge" || type == "sensor" || type == "chart"; }
  bool isActuator() const { return type == "toggle" || type == "slider"; }

  void applyState() {
    if (!isValid() || !isActuator()) return;

    if (type == "toggle") {
      bool state = (value == "1" || value == "true" || value == "on");
      digitalWrite(pin, state ? HIGH : LOW);
    }
    else if (type == "slider") {
      int pwmValue = value.toInt();
      pwmValue = constrain(pwmValue, (int)minValue, (int)maxValue);
      pwmValue = map(pwmValue, (int)minValue, (int)maxValue, 0, 255);
      analogWrite(pin, pwmValue);
    }
  }
};

// ===== Pin Mapping Structure =====
struct PinMap {
  String name;
  int pin;
  int mode;
};

// ===== Main SmartDevice Class =====
class SmartDevice {
private:
  // WiFi
  String wifiSSID;
  String wifiPassword;
  bool wifiConfigured;

  // API
  String apiBaseUrl;
  String deviceCode;
  bool apiConfigured;

  // MQTT
  WiFiClientSecure espClient;
  PubSubClient* mqttClient;
  String mqttServer;
  int mqttPort;
  String mqttUser;
  String mqttPassword;
  int userId;
  bool mqttConfigured;

  // Widgets
  Widget widgets[20];
  int widgetCount;

  // Pin Mappings
  PinMap pinMaps[30];
  int pinMapCount;

  // Timing
  unsigned long lastSensorPublish;
  unsigned long lastHeartbeat;
  unsigned long sensorInterval;
  unsigned long heartbeatInterval;

  // Callbacks
  std::function<void(String, String)> onMessageCallback;
  std::function<void(String, String)> onSensorReadCallback;

  // State
  bool isConnected;
  bool debugMode;

  static SmartDevice* instance;

public:
  // ===== Constructor =====
  SmartDevice()
    : wifiConfigured(false),
      apiConfigured(false),
      mqttConfigured(false),
      mqttClient(nullptr),
      mqttPort(8883),
      userId(0),
      widgetCount(0),
      pinMapCount(0),
      lastSensorPublish(0),
      lastHeartbeat(0),
      sensorInterval(10000),
      heartbeatInterval(60000),
      isConnected(false),
      debugMode(true) {
    instance = this;
    espClient.setInsecure();
  }

  ~SmartDevice() {
    delete mqttClient;
  }

  // ===== BASIC SETUP FUNCTIONS =====

  // Initialize serial communication
  SmartDevice& begin(int baudRate = 115200) {
    Serial.begin(baudRate);
    delay(1000);
    printBanner();
    log("✅ Device initialized");
    return *this;
  }

  // Configure WiFi credentials
  SmartDevice& setWiFi(const char* ssid, const char* password) {
    wifiSSID = ssid;
    wifiPassword = password;
    wifiConfigured = true;
    log("✅ WiFi configured: " + String(ssid));
    return *this;
  }

  // Configure API endpoint
  SmartDevice& setAPI(const char* baseUrl, const char* devCode) {
    apiBaseUrl = baseUrl;
    deviceCode = devCode;
    apiConfigured = true;
    log("✅ API configured");
    log("   URL: " + String(baseUrl));
    log("   Device: " + String(devCode));
    return *this;
  }

  // ===== PIN MAPPING FUNCTIONS =====

  // Add single pin mapping
  SmartDevice& addPin(const char* widgetName, int pin, int mode = OUTPUT) {
    if (pinMapCount >= 30) {
      log("⚠️  Max pin mappings reached!");
      return *this;
    }

    pinMaps[pinMapCount].name = widgetName;
    pinMaps[pinMapCount].pin = pin;
    pinMaps[pinMapCount].mode = mode;
    pinMapCount++;

    log("✅ Pin mapped: '" + String(widgetName) + "' → GPIO " + String(pin));
    return *this;
  }

  // Add multiple pins at once
  SmartDevice& addPins(std::initializer_list<std::tuple<const char*, int, int>> pins) {
    for (auto& p : pins) {
      addPin(std::get<0>(p), std::get<1>(p), std::get<2>(p));
    }
    return *this;
  }

  // ===== CONFIGURATION FUNCTIONS =====

  // Set sensor publish interval (milliseconds)
  SmartDevice& setSensorInterval(unsigned long interval) {
    sensorInterval = interval;
    log("✅ Sensor interval: " + String(interval) + "ms");
    return *this;
  }

  // Set heartbeat interval (milliseconds)
  SmartDevice& setHeartbeatInterval(unsigned long interval) {
    heartbeatInterval = interval;
    log("✅ Heartbeat interval: " + String(interval) + "ms");
    return *this;
  }

  // Enable/disable debug logging
  SmartDevice& setDebug(bool enable) {
    debugMode = enable;
    log(enable ? "✅ Debug mode enabled" : "ℹ️  Debug mode disabled");
    return *this;
  }

  // ===== CALLBACK FUNCTIONS =====

  // Set callback for MQTT messages
  SmartDevice& onMessage(std::function<void(String topic, String message)> callback) {
    onMessageCallback = callback;
    log("✅ Message callback registered");
    return *this;
  }

  // Set callback for sensor readings
  SmartDevice& onSensorRead(std::function<void(String widget, String value)> callback) {
    onSensorReadCallback = callback;
    log("✅ Sensor callback registered");
    return *this;
  }

  // ===== CONNECTION FUNCTIONS =====

  // Connect to everything (WiFi, API, MQTT)
  bool connect() {
    if (!wifiConfigured || !apiConfigured) {
      log("❌ WiFi or API not configured!");
      return false;
    }

    // Connect WiFi
    if (!connectWiFi()) {
      log("❌ WiFi connection failed!");
      return false;
    }

    // Authenticate with API
    if (!authenticate()) {
      log("❌ Authentication failed!");
      return false;
    }

    // Setup MQTT
    setupMQTT();

    // Fetch widgets
    if (!fetchWidgets()) {
      log("⚠️  Widget fetch failed!");
    }

    // Auto-setup pins
    autoSetupPins();

    // Connect MQTT
    if (!connectMQTT()) {
      log("⚠️  MQTT connection failed!");
    }

    isConnected = true;
    log("🚀 Device ready!");
    return true;
  }

  // Main update loop - call this in loop()
  void update() {
    // Check WiFi
    if (WiFi.status() != WL_CONNECTED) {
      log("⚠️  WiFi disconnected!");
      connectWiFi();
    }

    // Check MQTT
    if (mqttClient && !mqttClient->connected()) {
      connectMQTT();
    }

    if (mqttClient) {
      mqttClient->loop();
    }

    // Process widgets
    processWidgets();

    // Publish sensors
    if (millis() - lastSensorPublish >= sensorInterval) {
      publishSensors();
      lastSensorPublish = millis();
    }

    // Send heartbeat
    if (millis() - lastHeartbeat >= heartbeatInterval) {
      sendHeartbeat();
      lastHeartbeat = millis();
    }
  }

  // ===== UTILITY FUNCTIONS =====

  // Manually set a widget value
  bool setValue(const char* widgetName, const char* value) {
    for (int i = 0; i < widgetCount; i++) {
      if (widgets[i].name.equalsIgnoreCase(widgetName)) {
        widgets[i].value = value;
        widgets[i].applyState();
        log("✅ Set '" + String(widgetName) + "' = " + String(value));
        return true;
      }
    }
    log("⚠️  Widget not found: " + String(widgetName));
    return false;
  }

  // Get a widget value
  String getValue(const char* widgetName) {
    for (int i = 0; i < widgetCount; i++) {
      if (widgets[i].name.equalsIgnoreCase(widgetName)) {
        return widgets[i].value;
      }
    }
    return "";
  }

  // Check if connected
  bool connected() const {
    return isConnected && WiFi.status() == WL_CONNECTED;
  }

  // Get WiFi RSSI
  int getRSSI() const {
    return WiFi.RSSI();
  }

  // Get uptime in seconds
  unsigned long getUptime() const {
    return millis() / 1000;
  }

  // Print current configuration
  void printConfig() {
    Serial.println("\n📋 DEVICE CONFIGURATION");
    Serial.println("══════════════════════════════════════════");
    Serial.println("WiFi SSID    : " + wifiSSID);
    Serial.println("API URL      : " + apiBaseUrl);
    Serial.println("Device Code  : " + deviceCode);
    Serial.println("Widgets      : " + String(widgetCount));
    Serial.println("Pin Mappings : " + String(pinMapCount));
    Serial.println("MQTT Server  : " + mqttServer);
    Serial.println("User ID      : " + String(userId));
    Serial.println("══════════════════════════════════════════\n");
  }

  // Print all widgets
  void printWidgets() {
    Serial.println("\n📋 WIDGETS");
    Serial.println("═══════════════════════════════════════════════════");
    Serial.println(" Name             | Type      | Value  | Pin | OK");
    Serial.println("------------------|-----------|--------|-----|----");

    for (int i = 0; i < widgetCount; i++) {
      Widget &w = widgets[i];
      Serial.printf(" %-16s | %-9s | %-6s | %-3d | %s\n",
        w.name.c_str(),
        w.type.c_str(),
        w.value.c_str(),
        w.pin,
        w.autoSetup ? "✓" : "✗"
      );
    }
    Serial.println("═══════════════════════════════════════════════════\n");
  }

private:
  // ===== INTERNAL FUNCTIONS =====

  void log(String message) {
    if (debugMode) {
      Serial.println(message);
    }
  }

  void printBanner() {
    Serial.println("\n╔════════════════════════════════════════════╗");
    Serial.println("║     ESP32 SmartDevice Library v2.0        ║");
    Serial.println("║     Easy-to-use IoT Framework             ║");
    Serial.println("╚════════════════════════════════════════════╝\n");
  }

  bool connectWiFi() {
    log("📡 Connecting to WiFi: " + wifiSSID);
    WiFi.begin(wifiSSID.c_str(), wifiPassword.c_str());

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
      log("✅ WiFi connected!");
      log("📍 IP: " + WiFi.localIP().toString());
      log("📶 RSSI: " + String(WiFi.RSSI()) + " dBm");
      return true;
    }
    return false;
  }

  bool authenticate() {
    HTTPClient http;
    String url = apiBaseUrl + "/auth";

    log("🔐 Authenticating...");

    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    JsonDocument doc;
    doc["device_code"] = deviceCode;

    String payload;
    serializeJson(doc, payload);

    int httpCode = http.POST(payload);

    if (httpCode == 200) {
      String response = http.getString();
      JsonDocument responseDoc;

      if (deserializeJson(responseDoc, response) == DeserializationError::Ok) {
        if (responseDoc["success"] == true) {
          userId = responseDoc["device"]["user_id"];
          mqttServer = responseDoc["mqtt"]["host"].as<String>();
          mqttPort = responseDoc["mqtt"]["port"] | 8883;
          mqttUser = responseDoc["mqtt"]["username"].as<String>();
          mqttPassword = responseDoc["mqtt"]["password"].as<String>();

          log("✅ Authenticated!");
          log("   User ID: " + String(userId));
          log("   MQTT: " + mqttServer);

          http.end();
          return true;
        }
      }
    }

    log("❌ Auth failed: HTTP " + String(httpCode));
    http.end();
    return false;
  }

  void setupMQTT() {
    mqttClient = new PubSubClient(espClient);
    mqttClient->setServer(mqttServer.c_str(), mqttPort);
    mqttClient->setBufferSize(512);
    mqttClient->setCallback([](char* topic, byte* payload, unsigned int length) {
      if (instance) {
        instance->handleMQTTMessage(topic, payload, length);
      }
    });

    mqttConfigured = true;
    log("✅ MQTT configured");
  }

  bool connectMQTT() {
    if (!mqttClient || !mqttConfigured) return false;

    log("📡 Connecting to MQTT...");

    String clientId = "ESP32-" + deviceCode + "-" + String(millis());

    if (mqttClient->connect(clientId.c_str(), mqttUser.c_str(), mqttPassword.c_str())) {
      String topic = "users/" + String(userId) + "/devices/" + deviceCode + "/control/#";
      mqttClient->subscribe(topic.c_str());
      log("✅ MQTT connected!");
      log("📥 Subscribed: " + topic);
      return true;
    }

    log("❌ MQTT failed, rc=" + String(mqttClient->state()));
    return false;
  }

  bool fetchWidgets() {
    HTTPClient http;
    String url = apiBaseUrl + "/" + deviceCode + "/widgets";

    log("📡 Fetching widgets...");

    http.begin(url);
    http.addHeader("Accept", "application/json");

    int httpCode = http.GET();

    if (httpCode == 200) {
      String payload = http.getString();
      JsonDocument doc;

      if (deserializeJson(doc, payload) == DeserializationError::Ok) {
        if (doc["success"] == true) {
          JsonObject widgetsData = doc["widgets"].as<JsonObject>();
          widgetCount = 0;

          for (JsonPair kv : widgetsData) {
            if (widgetCount >= 20) break;

            String widgetKey = kv.key().c_str();
            JsonObject widget = kv.value().as<JsonObject>();

            widgets[widgetCount].key = widgetKey;
            widgets[widgetCount].name = widget["name"].as<String>();
            widgets[widgetCount].type = widget["type"].as<String>();
            widgets[widgetCount].value = widget["value"].as<String>();
            widgets[widgetCount].displayIndex = widget["order"] | widgetCount;
            widgets[widgetCount].minValue = widget["min"] | 0;
            widgets[widgetCount].maxValue = widget["max"] | 100;
            widgets[widgetCount].pin = -1;
            widgets[widgetCount].autoSetup = false;

            widgetCount++;
          }

          log("✅ Fetched " + String(widgetCount) + " widgets");
          http.end();
          return true;
        }
      }
    }

    log("❌ Widget fetch failed: HTTP " + String(httpCode));
    http.end();
    return false;
  }

  void autoSetupPins() {
    log("\n🔧 Auto-setup pins...");

    int setupCount = 0;

    for (int i = 0; i < widgetCount; i++) {
      Widget &w = widgets[i];

      for (int j = 0; j < pinMapCount; j++) {
        if (w.name.equalsIgnoreCase(pinMaps[j].name)) {
          w.pin = pinMaps[j].pin;
          w.autoSetup = true;
          pinMode(w.pin, pinMaps[j].mode);

          log("  ✓ " + w.name + " → GPIO " + String(w.pin));
          setupCount++;
          break;
        }
      }
    }

    log("✅ Setup " + String(setupCount) + "/" + String(widgetCount) + " widgets\n");
  }

  void handleMQTTMessage(char* topic, byte* payload, unsigned int length) {
    String message = "";
    for (unsigned int i = 0; i < length; i++) {
      message += (char)payload[i];
    }

    String topicStr = String(topic);

    log("📩 MQTT: " + topicStr + " = " + message);

    // Extract widget name from topic
    int lastSlash = topicStr.lastIndexOf('/');
    if (lastSlash > 0) {
      String widgetName = topicStr.substring(lastSlash + 1);

      // Update widget value
      for (int i = 0; i < widgetCount; i++) {
        if (widgets[i].name == widgetName) {
          widgets[i].value = message;
          widgets[i].applyState();
          break;
        }
      }

      // Call user callback
      if (onMessageCallback) {
        onMessageCallback(widgetName, message);
      }
    }
  }

  void processWidgets() {
    for (int i = 0; i < widgetCount; i++) {
      widgets[i].applyState();
    }
  }

  void publishSensors() {
    if (!mqttClient || !mqttClient->connected()) return;

    for (int i = 0; i < widgetCount; i++) {
      Widget &w = widgets[i];

      if (!w.isSensor() || !w.isValid()) continue;

      int rawValue = analogRead(w.pin);
      String sensorValue = String(rawValue);

      // Custom sensor processing
      String nameLower = w.key;
      nameLower.toLowerCase();

      if (nameLower.indexOf("temp") >= 0) {
        float temp = rawValue * 0.1; // Example conversion
        sensorValue = String(temp, 1);
      }

      String topic = "users/" + String(userId) + "/devices/" +
                     deviceCode + "/sensors/" + w.key;

      mqttClient->publish(topic.c_str(), sensorValue.c_str());

      // Call user callback
      if (onSensorReadCallback) {
        onSensorReadCallback(w.name, sensorValue);
      }

      delay(50);
    }
  }

  void sendHeartbeat() {
    HTTPClient http;
    String url = apiBaseUrl + "/" + deviceCode + "/heartbeat";

    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    JsonDocument doc;
    doc["status"] = "online";
    doc["uptime"] = getUptime();
    doc["free_heap"] = ESP.getFreeHeap();
    doc["rssi"] = WiFi.RSSI();

    String payload;
    serializeJson(doc, payload);

    http.POST(payload);
    http.end();

    log("💓 Heartbeat sent");
  }
};

SmartDevice* SmartDevice::instance = nullptr;

#endif

// ============================================================
// 📄 EXAMPLE USAGE IN .ino FILE
// ============================================================

#include "SmartDevice.h"

SmartDevice device;

void setup() {
  // 1️⃣ Initialize device
  device.begin()

  // 2️⃣ Configure WiFi
        .setWiFi("madiaproject", "heatedpillow")

  // 3️⃣ Configure API
        .setAPI("http://10.199.9.103:8000/api/devices", "DEV_1IGXEOR0PQ")

  // 4️⃣ Map pins (widget name → GPIO pin → mode)
        .addPin("lampu", 2, OUTPUT)
        .addPin("lamp teras", 4, OUTPUT)
        .addPin("lamp rumah", 5, OUTPUT)
        .addPin("lampu 2", 18, OUTPUT)
        .addPin("level sumur", 34, INPUT)
        .addPin("kipas", 19, OUTPUT)
        .addPin("terang", 21, OUTPUT)

  // 5️⃣ Configure intervals (optional)
        .setSensorInterval(10000)    // 10 seconds
        .setHeartbeatInterval(60000) // 60 seconds

  // 6️⃣ Set callbacks (optional)
        .onMessage([](String widget, String value) {
          Serial.println("🔔 Widget '" + widget + "' changed to: " + value);
        })
        .onSensorRead([](String widget, String value) {
          Serial.println("📊 Sensor '" + widget + "' read: " + value);
        });

  // 7️⃣ Connect everything!
  if (!device.connect()) {
    Serial.println("❌ Connection failed! Restarting...");
    delay(5000);
    ESP.restart();
  }

  // 8️⃣ Print configuration
  device.printConfig();
  device.printWidgets();
}

void loop() {
  // Just call update() - that's it!
  device.update();

  // Optional: Manual control
  // device.setValue("lampu", "1");  // Turn on lamp
  // String value = device.getValue("lampu");
}

// ============================================================
// 🎯 ADVANCED EXAMPLE - Multiple Devices
// ============================================================

/*
SmartDevice device1;
SmartDevice device2;

void setup() {
  // Device 1
  device1.begin()
         .setWiFi("WiFi-A", "pass123")
         .setAPI("http://api1.com/devices", "DEV_001")
         .addPin("relay1", 2, OUTPUT)
         .connect();

  // Device 2
  device2.begin()
         .setWiFi("WiFi-B", "pass456")
         .setAPI("http://api2.com/devices", "DEV_002")
         .addPin("relay2", 4, OUTPUT)
         .connect();
}

void loop() {
  device1.update();
  device2.update();
}
*/
