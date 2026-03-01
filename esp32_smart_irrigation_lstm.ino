#include <ArduinoJson.h>
#include <DHT.h>
#include <ESP32Servo.h>
#include <FS.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <SPIFFS.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
< parameter name = "time.h>

    // ==========================================
    // WIFI CONFIGURATION - HARDCODED
    // ==========================================
    const char *ssid = "madiaproject"; // Ganti dengan SSID WiFi Anda
const char *password = "heatedpillow"; // Ganti dengan password WiFi Anda

// ==========================================
// CONFIGURABLE PINOUT
// ==========================================
#define TRIGGER_PIN 0
#define LED_PIN 2

// ===== SMART IRRIGATION PINS =====
#define SOIL_PIN 34
#define SERVO_PIN 13
#define DHT_PIN 4

// ===== SMART IRRIGATION CONSTANTS =====
#define SOIL_DRY 4095
#define SOIL_WET 1500
#define DRY_MAX 30
#define WET_MIN 80
#define DHTTYPE DHT22
#define SERVO_OPEN 90
#define SERVO_CLOSE 0

// ==========================================
// GLOBAL VARIABLES
// ==========================================
char device_code[40] = "DEV_8P8LDQ5UCH";
char api_url[100] = "https://diklat.mdpower.io/";

// MQTT Variables
String mqtt_host;
int mqtt_port;
String mqtt_user;
String mqtt_pass;
String topic_control;
String topic_status;
String topic_sensors_base;

// Smart Irrigation Globals
Servo valveServo;
DHT dht(DHT_PIN, DHTTYPE);
unsigned long lastPublishTime = 0;
const unsigned long publishInterval = 5000;
bool currentServoState = false;

// TLS / SSL Certificate (ISRG Root X1)
const char *root_ca =
    "-----BEGIN CERTIFICATE-----\n"
    "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\n"
    "-----END CERTIFICATE-----\n";

WiFiClientSecure espClient;
PubSubClient client(espClient);

bool mqttConfigured = false;

// ==========================================
// FILE SYSTEM HELPER
// ==========================================
bool loadConfig() {
  if (SPIFFS.begin(true)) {
    if (SPIFFS.exists("/config.json")) {
      File configFile = SPIFFS.open("/config.json", "r");
      if (configFile) {
        size_t size = configFile.size();
        if (size > 1024) {
          Serial.println("Config file too large");
          return false;
        }
        std::unique_ptr<char[]> buf(new char[size]);
        configFile.readBytes(buf.get(), size);

        JsonDocument json;
        DeserializationError error = deserializeJson(json, buf.get());
        if (!error) {
          if (json["device_code"].is<const char *>())
            strcpy(device_code, json["device_code"]);
          if (json["api_url"].is<const char *>())
            strcpy(api_url, json["api_url"]);
          return true;
        }
      }
    }
  }
  return false;
}

void saveConfig() {
  JsonDocument json;
  json["device_code"] = device_code;
  json["api_url"] = api_url;

  File configFile = SPIFFS.open("/config.json", "w");
  if (!configFile) {
    Serial.println("Failed to open config file for writing");
    return;
  }
  serializeJson(json, configFile);
  configFile.close();
  Serial.println("Config saved");
}

// ==========================================
// WIFI CONNECTION
// ==========================================
void connectWiFi() {
  Serial.println();
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("WiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    Serial.print("Signal Strength (RSSI): ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    Serial.println();
    Serial.println("Failed to connect to WiFi");
    Serial.println("Restarting in 5 seconds...");
    delay(5000);
    ESP.restart();
  }
}

// ==========================================
// API & MQTT HELPER
// ==========================================
bool fetchMqttConfig() {
  if (WiFi.status() != WL_CONNECTED)
    return false;

  HTTPClient http;
  String url =
      String(api_url) + "api/devices/" + String(device_code) + "/mqtt-config";

  Serial.print("Fetching Config from: ");
  Serial.println(url);

  http.begin(url);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String payload = http.getString();
    Serial.println("Config Received:");
    Serial.println(payload);

    JsonDocument doc;
    deserializeJson(doc, payload);

    mqtt_host = doc["mqtt"]["host"].as<String>();
    mqtt_port = doc["mqtt"]["port"].as<int>();
    mqtt_user = doc["mqtt"]["username"].as<String>();
    mqtt_pass = doc["mqtt"]["password"].as<String>();

    topic_control = doc["topics"]["control"].as<String>();
    topic_status = doc["topics"]["status"].as<String>();
    topic_sensors_base = doc["topics"]["sensors"].as<String>();

    mqttConfigured = true;
    http.end();
    return true;
  } else {
    Serial.printf("HTTP Failed: %d\n", httpCode);
    http.end();
    return false;
  }
}

void callback(char *topic, byte *payload, unsigned int length) {
  String message;
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  Serial.printf("Message arrived [%s] %s\n", topic, message.c_str());

  String t = String(topic);
  int lastSlash = t.lastIndexOf('/');
  String widgetKey = t.substring(lastSlash + 1);

  // ===== SERVO CONTROL FROM LSTM =====
  if (widgetKey == "toggle1") {
    int command = message.toInt();
    bool newServoState = (command == 1);

    if (newServoState != currentServoState) {
      currentServoState = newServoState;
      valveServo.write(currentServoState ? SERVO_OPEN : SERVO_CLOSE);
      Serial.printf("🤖 LSTM Command: Servo %s\n",
                    currentServoState ? "OPEN" : "CLOSED");
    }
  }

  // Publish sensor status back
  String statusTopic = topic_sensors_base + widgetKey;
  client.publish(statusTopic.c_str(), message.c_str());
}

void setClock() {
  configTime(7 * 3600, 0, "pool.ntp.org", "time.nist.gov");
  Serial.print("Waiting for NTP time sync: ");
  time_t now = time(nullptr);
  while (now < 8 * 3600 * 2) {
    delay(500);
    Serial.print(".");
    now = time(nullptr);
  }
  Serial.println("");
  struct tm timeinfo;
  gmtime_r(&now, &timeinfo);
  Serial.print("Current time: ");
  Serial.print(asctime(&timeinfo));
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Attempting MQTT connection...");
    String clientId = "ESP32Client-" + String(device_code);

    if (client.connect(clientId.c_str(), mqtt_user.c_str(),
                       mqtt_pass.c_str())) {
      Serial.println("connected");
      client.subscribe(topic_control.c_str());
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());

      char err_buf[100];
      espClient.lastError(err_buf, 100);
      Serial.print(" SSL Error: ");
      Serial.println(err_buf);

      Serial.println(" try again in 5 seconds");
      delay(5000);
    }
  }
}

// ==========================================
// SETUP
// ==========================================
void setup() {
  Serial.begin(115200);
  pinMode(TRIGGER_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);

  if (!loadConfig()) {
    Serial.println("Failed to load config or first run");
  }

  // Connect to WiFi
  connectWiFi();

  analogReadResolution(12);
  analogSetAttenuation(ADC_11db);
  valveServo.attach(SERVO_PIN);
  valveServo.write(SERVO_CLOSE);
  dht.begin();

  setClock();

  if (fetchMqttConfig()) {
    espClient.setCACert(root_ca);
    client.setServer(mqtt_host.c_str(), mqtt_port);
    client.setCallback(callback);
  }
}

// ==========================================
// LOOP
// ==========================================
void loop() {
  // Check WiFi connection
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected. Reconnecting...");
    connectWiFi();
  }

  if (mqttConfigured) {
    if (!client.connected()) {
      reconnect();
    }
    client.loop();

    unsigned long now = millis();
    if (now - lastPublishTime > publishInterval) {
      lastPublishTime = now;

      // Read real sensor values from DHT22 and soil moisture
      int soilValue = analogRead(SOIL_PIN);
      float temperature = dht.readTemperature();
      float humidity = dht.readHumidity();

      // Fallback to simulated values if sensor reads fail
      if (isnan(temperature)) {
        temperature = random(25, 35) + (random(0, 10) / 10.0);
      }
      if (isnan(humidity)) {
        humidity = random(40, 70);
      }

      // Calculate soil percentage for UI display
      int soilPercent = map(soilValue, SOIL_DRY, SOIL_WET, 0, 100);
      soilPercent = constrain(soilPercent, 0, 100);

      // Publish to MQTT
      if (client.connected()) {
        // 🔥 CRITICAL: Publish RAW VALUE for LSTM Service
        client.publish((topic_sensors_base + "moisture_raw").c_str(),
                       String(soilValue).c_str());

        // Publish percentage for UI Dashboard
        client.publish((topic_sensors_base + "gauge1").c_str(),
                       String(soilPercent).c_str());
        client.publish((topic_sensors_base + "gauge2").c_str(),
                       String(temperature).c_str());
        client.publish(
            (topic_sensors_base + "text1").c_str(),
            String(currentServoState ? "TERBUKA" : "TERTUTUP").c_str());

        Serial.printf("📤 Raw=%d, Pct=%d%%, Temp=%.1fC, Servo=%s\n", soilValue,
                      soilPercent, temperature,
                      currentServoState ? "OPEN" : "CLOSED");
      }
    }
  }
}
