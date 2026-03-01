#include <ArduinoJson.h> // Library: ArduinoJson by Benoit Blanchon
#include <LittleFS.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiManager.h>

// --- Default Values (Akan di-overwrite jika ada input di Portal) ---
char mqtt_server[40] = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud";
char mqtt_port[6] = "8883";
char mqtt_user[40] = "harun";
char mqtt_pass[40] = "@&13harunA";

#define TRIGGER_PIN 0
#define CONFIG_TIMEOUT 180

WiFiClientSecure espClient;
PubSubClient client(espClient);

// Flag untuk simpan konfigurasi baru
bool shouldSaveConfig = false;

// Callback notifying us of the need to save config
void saveConfigCallback() {
  Serial.println("Should save config");
  shouldSaveConfig = true;
}

void setup() {
  Serial.begin(115200);
  pinMode(TRIGGER_PIN, INPUT_PULLUP);

  // 1. Mount LittleFS
  if (LittleFS.begin(true)) {
    Serial.println("mounted file system");
    if (LittleFS.exists("/config.json")) {
      // file exists, reading and loading
      File configFile = LittleFS.open("/config.json", "r");
      if (configFile) {
        JsonDocument doc;
        DeserializationError error = deserializeJson(doc, configFile);
        if (!error) {
          strcpy(mqtt_server, doc["mqtt_server"]);
          strcpy(mqtt_port, doc["mqtt_port"]);
          strcpy(mqtt_user, doc["mqtt_user"]);
          strcpy(mqtt_pass, doc["mqtt_pass"]);
        }
      }
    }
  }

  // 2. WiFiManager Custom Parameters
  WiFiManagerParameter custom_mqtt_server("server", "MQTT Server", mqtt_server,
                                          40);
  WiFiManagerParameter custom_mqtt_port("port", "MQTT Port", mqtt_port, 6);
  WiFiManagerParameter custom_mqtt_user("user", "MQTT User", mqtt_user, 40);
  WiFiManagerParameter custom_mqtt_pass("pass", "MQTT Pass", mqtt_pass, 40);

  WiFiManager wm;
  wm.setSaveConfigCallback(saveConfigCallback);

  wm.addParameter(&custom_mqtt_server);
  wm.addParameter(&custom_mqtt_port);
  wm.addParameter(&custom_mqtt_user);
  wm.addParameter(&custom_mqtt_pass);

  if (!wm.autoConnect("ESP32_SmartHome_Portal")) {
    Serial.println("Gagal konek, restart...");
    delay(3000);
    ESP.restart();
  }

  // Baca parameter baru jika ada
  strcpy(mqtt_server, custom_mqtt_server.getValue());
  strcpy(mqtt_port, custom_mqtt_port.getValue());
  strcpy(mqtt_user, custom_mqtt_user.getValue());
  strcpy(mqtt_pass, custom_mqtt_pass.getValue());

  // 3. Simpan ke LittleFS jika diubah
  if (shouldSaveConfig) {
    File configFile = LittleFS.open("/config.json", "w");
    if (configFile) {
      JsonDocument doc;
      doc["mqtt_server"] = mqtt_server;
      doc["mqtt_port"] = mqtt_port;
      doc["mqtt_user"] = mqtt_user;
      doc["mqtt_pass"] = mqtt_pass;
      serializeJson(doc, configFile);
      configFile.close();
    }
  }

  Serial.println("Terhubung ke WiFi!");
  espClient.setInsecure();
  client.setServer(mqtt_server, atoi(mqtt_port));
}

void checkButton() {
  if (digitalRead(TRIGGER_PIN) == LOW) {
    unsigned long pressTime = millis();
    while (digitalRead(TRIGGER_PIN) == LOW) {
      if (millis() - pressTime > 5000) {
        Serial.println("Membuka Config Portal...");
        WiFiManager wm;
        wm.setConfigPortalTimeout(CONFIG_TIMEOUT);
        if (!wm.startConfigPortal("ESP32_OnDemand_Setup")) {
          Serial.println("Portal Timeout");
        }
        break;
      }
      delay(10);
    }
  }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("MQTT Connecting to: ");
    Serial.println(mqtt_server);
    String clientId = "ESP32Client-" + String(random(0xffff), HEX);
    if (client.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println("MQTT Success");
    } else {
      Serial.print("Failed, rc=");
      Serial.print(client.state());
      delay(5000);
    }
  }
}

void loop() {
  checkButton();
  if (!client.connected())
    reconnect();
  client.loop();

  static unsigned long lastMsg = 0;
  if (millis() - lastMsg > 10000) {
    lastMsg = millis();
    client.publish("users/1/devices/ESP32_PRO/sensors/heartbeat",
                   "{\"online\": true}");
  }
}
