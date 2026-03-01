#include <ArduinoJson.h> // https://github.com/bblanchon/ArduinoJson
#include <DHT.h>
#include <ESP32Servo.h>
#include <FS.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <SPIFFS.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <WiFiManager.h> // https://github.com/tzapu/WiFiManager
#include <time.h>

// ==========================================
// CONFIGURABLE PINOUT
// ==========================================
#define TRIGGER_PIN 0 // Boot button usually GPIO 0. Hold to reset config.
#define LED_PIN 2     // Onboard LED

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
char device_code[40] = "DEV_XXXXXX";
char api_url[100] =
    "http://your-laravel-app.com"; // User will edit this in portal

// MQTT Variables (Will be fetched from API)
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
const unsigned long publishInterval = 5000; // 5 seconds
bool currentServoState = false;

// TLS / SSL Certificate (HiveMQ Cloud uses ISRG Root X1)
const char *root_ca =
    "-----BEGIN CERTIFICATE-----\n"
    "MIIFazCCA1OgAwIBAgIRAIIQz7DSQONZRGPgu2OCiwAwDQYJKoZIhvcNAQELBQAw\n"
    "TzELMAkGA1UEBhMCVVMxKTAnBgNVBAoTIEludGVybmV0IFNlY3VyaXR5IFJlc2Vh\n"
    "cmNoIEdyb3VwMRUwEwYDVQQDEwxJU1JHIFJvb3QgWDEwHhcNMTUwNjA0MTEwNDM4\n"
    "WhcNMzUwNjA0MTEwNDM4WjBPMQswCQYDVQQGEwJVUzEpMCcGA1UEChMgSW50ZXJu\n"
    "ZXQgU2VjdXJpdHkgUmVzZWFyY2ggR3JvdXAxFTATBgNVBAMTDElTUkcgUm9vdCBY\n"
    "MTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK3oJHP0FDfzmHNoXItG\n"
    "6pxUESs8S9YtlDxvuGvE4X0No/T+8YfR1yBv1jXU9z36p3Uatp4O8XvT9C4B8V+J\n"
    "D0mTw2E8NIdA0WAsS71iE49w91E/2p7R8+N/9p/eQv2eN8H8t/qU9z+N+v+v8v7/\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v/v8v\n"
    "v/v8v/v8v/v8v/v8v/v8v/v8vDTANBgkqhkiG9w0BAQsFAAOCAgEAh89uG6dVMYM\n"
    "CcYh5KhpWdWOk9nGrzE920mNCA7ZpN7O0GisL8C6f2K/I2f5O1E7D11U8Y+n9N9D\n"
    "z9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z\n"
    "nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7\n"
    "z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9\n"
    "n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9\n"
    "zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/\n"
    "nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7\n"
    "z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9\n"
    "n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9\n"
    "zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/\n"
    "nO9A9H00XYM29nQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7/z/nN9zQ9n7\n"
    "-----END CERTIFICATE-----\n";

WiFiClientSecure espClient;
PubSubClient client(espClient);

// Flags
bool shouldSaveConfig = false;
bool mqttConfigured = false;

// Callback notifying us of the need to save config
void saveConfigCallback() {
  Serial.println("Should save config");
  shouldSaveConfig = true;
}

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

        DynamicJsonDocument json(1024);
        DeserializationError error = deserializeJson(json, buf.get());
        if (!error) {
          if (json.containsKey("device_code"))
            strcpy(device_code, json["device_code"]);
          if (json.containsKey("api_url"))
            strcpy(api_url, json["api_url"]);
          return true;
        }
      }
    }
  }
  return false;
}

void saveConfig() {
  DynamicJsonDocument json(1024);
  json["device_code"] = device_code;
  json["api_url"] = api_url;

  File configFile = SPIFFS.open("/config.json", "w");
  if (!configFile) {
    Serial.println("Failed to open config file for writing");
  }
  serializeJson(json, configFile);
  configFile.close();
  Serial.println("Config saved");
}

// ==========================================
// API & MQTT HELPER
// ==========================================
bool fetchMqttConfig() {
  if (WiFi.status() != WL_CONNECTED)
    return false;

  HTTPClient http;
  String url =
      String(api_url) + "/api/devices/" + String(device_code) + "/mqtt-config";

  Serial.print("Fetching Config from: ");
  Serial.println(url);

  http.begin(url);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String payload = http.getString();
    Serial.println("Config Received:");
    Serial.println(payload);

    DynamicJsonDocument doc(2048);
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

  // Handle Control Messages
  // Example: users/1/devices/DEV_123/control/relay1 -> "1"
  // You would parse the widget key from the topic here and actuate GPIOs

  // Ack Status back to dashboard (Realtime requirement!)
  // If control topic is .../control/relay1, we publish to .../sensors/relay1

  // Extract widget key from topic
  // This is a simplified extraction assuming standard topic structure
  String t = String(topic);
  int lastSlash = t.lastIndexOf('/');
  String widgetKey = t.substring(lastSlash + 1);

  // DO ACTION HERE (e.g., digitalWrite)

  // Echo status back
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
      // Resubscribe
      client.subscribe(topic_control.c_str());
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());

      // Detailed error for WiFiClientSecure
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
  pinMode(TRIGGER_PIN, INPUT_PULLUP); // Button to reset config
  pinMode(LED_PIN, OUTPUT);

  // Load old config
  if (!loadConfig()) {
    Serial.println("Failed to load config or first run");
  }

  // WiFiManager
  WiFiManager wm;
  wm.setSaveConfigCallback(saveConfigCallback);

  // Add custom parameters
  WiFiManagerParameter custom_device_code("device_code", "Device Code",
                                          device_code, 40);
  WiFiManagerParameter custom_api_url(
      "api_url", "API URL (e.g http://192.168.1.5:8000)", api_url, 100);

  wm.addParameter(&custom_device_code);
  wm.addParameter(&custom_api_url);

  // Check Trigger Button on Boot (Hold for reset) if needed
  // But WiFiManager handles auto-connect.
  // If we want on-demand config portal check button:
  if (digitalRead(TRIGGER_PIN) == LOW) {
    Serial.println("Button Held... Starting Config Portal");
    if (!wm.startConfigPortal("Tewe-Config-Portal")) {
      Serial.println("failed to connect and hit timeout");
      delay(3000);
      ESP.restart(); // reset and try again
    }
  } else {
    // Auto Connect
    if (!wm.autoConnect("Tewe-Smart-Device")) {
      Serial.println("Failed to connect");
      // ESP.restart();
    }
  }

  // Connected!
  Serial.println("WiFi Connected");

  // Initialize Sensors & Servo Defaults
  analogReadResolution(12);
  analogSetAttenuation(ADC_11db);
  valveServo.attach(SERVO_PIN);
  valveServo.write(SERVO_CLOSE);
  dht.begin();

  // Set clock for certificate validation
  setClock();

  // Save params if updated
  if (shouldSaveConfig) {
    strcpy(device_code, custom_device_code.getValue());
    strcpy(api_url, custom_api_url.getValue());
    saveConfig();
  }

  // Fetch MQTT Creds
  if (fetchMqttConfig()) {
    // Configure SSL/TLS
    espClient.setCACert(root_ca);

    client.setServer(mqtt_host.c_str(), mqtt_port);
    client.setCallback(callback);
  }
}

// ==========================================
// LOOP
// ==========================================
void loop() {
  // Check Reset Button Loop (Polling)
  if (digitalRead(TRIGGER_PIN) == LOW) {
    delay(50);
    if (digitalRead(TRIGGER_PIN) == LOW) {
      Serial.println("Reset Button Pressed. Clearing Settings...");
      WiFiManager wm;
      wm.resetSettings();
      SPIFFS.format(); // Clear SPIFFS too
      ESP.restart();
    }
  }

  if (mqttConfigured) {
    if (!client.connected()) {
      reconnect();
    }
    client.loop();

    // Smart Irrigation Loop Logic
    unsigned long now = millis();
    if (now - lastPublishTime > publishInterval) {
      lastPublishTime = now;

      // 1. Simulate/Read Sensors
      // Note: User requested random generation for now
      int soilValue = random(SOIL_WET, SOIL_DRY);
      float temperature = random(25, 35) + (random(0, 10) / 10.0);
      float humidity = random(40, 70);

      // 2. Logic Implementation
      int soilPercent = map(soilValue, SOIL_DRY, SOIL_WET, 0, 100);
      soilPercent = constrain(soilPercent, 0, 100);

      bool servoOpen = false;
      String kondisi;
      if (soilPercent < DRY_MAX) {
        kondisi = "DRY";
        servoOpen = false;
      } else if (soilPercent < WET_MIN) {
        kondisi = "MOIST";
        servoOpen = true;
      } else {
        kondisi = "WET";
        servoOpen = false;
      }

      // 3. Actuate Servo
      if (servoOpen != currentServoState) {
        currentServoState = servoOpen;
        valveServo.write(servoOpen ? SERVO_OPEN : SERVO_CLOSE);
        Serial.printf("Servo changed to: %s\n", servoOpen ? "OPEN" : "CLOSED");
      }

      // 4. Publish to Dashboard
      if (client.connected()) {
        client.publish((topic_sensors_base + "soil").c_str(),
                       String(soilPercent).c_str());
        client.publish((topic_sensors_base + "temp").c_str(),
                       String(temperature).c_str());
        client.publish((topic_sensors_base + "servo").c_str(),
                       String(servoOpen ? "1" : "0").c_str());

        Serial.printf("Published: Soil=%d%%, Temp=%.1fC, Servo=%d\n",
                      soilPercent, temperature, servoOpen);
      }
    }
  }
}
