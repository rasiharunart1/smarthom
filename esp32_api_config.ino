#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <LittleFS.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiManager.h>

// --- Default Configuration (Will be overwritten by WiFiManager portal) ---
char device_code[20] = "DEV_YOURCODE";
char api_url[100] = "https://diklat.mdpower.io/api/devices/auth";

// --- Variables loaded from API ---
char mqtt_host[100];
int mqtt_port;
char mqtt_user[50];
char mqtt_pass[50];
char sub_topic[100];
char pub_prefix[100];

#define TRIGGER_PIN 0
#define CONFIG_TIMEOUT 180

WiFiClientSecure espClient;
PubSubClient client(espClient);
bool shouldSaveConfig = false;

void saveConfigCallback() { shouldSaveConfig = true; }

void loadStoredConfig() {
  if (LittleFS.begin(true)) {
    if (LittleFS.exists("/device_config.json")) {
      File f = LittleFS.open("/device_config.json", "r");
      if (f) {
        JsonDocument doc;
        deserializeJson(doc, f);
        strcpy(device_code, doc["device_code"] | "");
        strcpy(api_url, doc["api_url"] | api_url);
        f.close();
      }
    }
  }
}

void saveStoredConfig() {
  File f = LittleFS.open("/device_config.json", "w");
  if (f) {
    JsonDocument doc;
    doc["device_code"] = device_code;
    doc["api_url"] = api_url;
    serializeJson(doc, f);
    f.close();
  }
}

bool fetchConfigFromAPI() {
  HTTPClient http;
  http.begin(api_url);
  http.addHeader("Content-Type", "application/json");

  JsonDocument requestDoc;
  requestDoc["device_code"] = device_code;
  String requestBody;
  serializeJson(requestDoc, requestBody);

  Serial.println("Fetching config from API...");
  int httpCode = http.POST(requestBody);

  if (httpCode == 200) {
    String payload = http.getString();
    JsonDocument doc;
    deserializeJson(doc, payload);

    if (doc["success"]) {
      strcpy(mqtt_host, doc["mqtt"]["host"]);
      mqtt_port = doc["mqtt"]["port"];
      strcpy(mqtt_user, doc["mqtt"]["username"]);
      strcpy(mqtt_pass, doc["mqtt"]["password"]);
      strcpy(sub_topic, doc["topics"]["subscribe"]);
      strcpy(pub_prefix, doc["topics"]["publish_prefix"]);

      Serial.println("Config fetched successfully!");
      return true;
    }
  }

  Serial.print("API Error: ");
  Serial.println(httpCode);
  return false;
}

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  String message = "";
  for (int i = 0; i < length; i++)
    message += (char)payload[i];

  Serial.print("Message arrived [");
  Serial.print(topic);
  Serial.print("] ");
  Serial.println(message);

  // Example: Handle specific controls
  // if (String(topic).endsWith("lamp_1")) { ... }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Connecting to MQTT: ");
    Serial.println(mqtt_host);
    String clientId = "ESP32-" + String(device_code);
    if (client.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println("Connected!");
      client.subscribe(sub_topic);
      Serial.print("Subscribed to: ");
      Serial.println(sub_topic);
    } else {
      Serial.print("Failed, rc=");
      Serial.print(client.state());
      delay(5000);
    }
  }
}

void checkButton() {
  if (digitalRead(TRIGGER_PIN) == LOW) {
    unsigned long start = millis();
    while (digitalRead(TRIGGER_PIN) == LOW) {
      if (millis() - start > 5000) {
        WiFiManager wm;
        wm.startConfigPortal("ESP32_Config_Device");
        break;
      }
      delay(10);
    }
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(TRIGGER_PIN, INPUT_PULLUP);

  loadStoredConfig();

  WiFiManagerParameter custom_device_code("code", "Device Code", device_code,
                                          20);
  WiFiManagerParameter custom_api_url("api", "API Auth URL", api_url, 100);

  WiFiManager wm;
  wm.setSaveConfigCallback(saveConfigCallback);
  wm.addParameter(&custom_device_code);
  wm.addParameter(&custom_api_url);

  if (!wm.autoConnect("ESP32_Setup")) {
    ESP.restart();
  }

  strcpy(device_code, custom_device_code.getValue());
  strcpy(api_url, custom_api_url.getValue());

  if (shouldSaveConfig) {
    saveStoredConfig();
  }

  // Fetch MQTT info from Laravel
  while (!fetchConfigFromAPI()) {
    Serial.println("Retrying API in 10s...");
    delay(10000);
  }

  espClient.setInsecure();
  client.setServer(mqtt_host, mqtt_port);
  client.setCallback(mqttCallback);
}

void loop() {
  checkButton();
  if (!client.connected())
    reconnect();
  client.loop();

  // Publish sensor data example
  static unsigned long lastMsg = 0;
  if (millis() - lastMsg > 15000) {
    lastMsg = millis();
    char topic[150];
    sprintf(topic, "%stemp", pub_prefix); // topics/publish_prefix + "temp"
    client.publish(topic, String(random(20, 35)).c_str());
    Serial.print("Published to ");
    Serial.println(topic);
  }
}
