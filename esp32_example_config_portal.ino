#include <WiFi.h>
#include <WiFiManager.h> // Library: https://github.com/tzapu/WiFiManager
#include <PubSubClient.h> // Library: https://github.com/knolleary/pubsubclient

// --- Configuration ---
const char* mqtt_server = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud";
const int mqtt_port = 8883; // TLS
const char* mqtt_user = "harun";
const char* mqtt_pass = "@&13harunA";

#define TRIGGER_PIN 0 // Ganti ke pin tombol Anda (biasanya 0 atau 4)
#define CONFIG_TIMEOUT 180 // portal aktif selama 3 menit

WiFiClientSecure espClient;
PubSubClient client(espClient);

void setup() {
  Serial.begin(115200);
  pinMode(TRIGGER_PIN, INPUT_PULLUP);

  WiFiManager wm;

  // Coba koneksi otomatis
  // Jika gagal, akan membuat Access Point "ESP32_Config_Portal"
  if (!wm.autoConnect("ESP32_Config_Portal")) {
    Serial.println("Gagal konek, restart...");
    delay(3000);
    ESP.restart();
  }

  Serial.println("Terhubung ke WiFi!");
  
  // Setup MQTT with TLS
  espClient.setInsecure(); // Ganti dengan CA Cert jika ingin lebih aman
  client.setServer(mqtt_server, mqtt_port);
}

void checkButton() {
  // Cek jika tombol ditekan lama (on-demand config portal)
  if (digitalRead(TRIGGER_PIN) == LOW) {
    unsigned long pressTime = millis();
    
    // Tunggu sampai tombol dilepas atau melewati 5 detik
    while (digitalRead(TRIGGER_PIN) == LOW) {
      if (millis() - pressTime > 5000) {
        Serial.println("Membuka Config Portal...");
        WiFiManager wm;
        wm.setConfigPortalTimeout(CONFIG_TIMEOUT);
        
        if (!wm.startConfigPortal("ESP32_Config_OnDemand")) {
          Serial.println("Gagal konek/Timeout");
        } else {
          Serial.println("Terhubung via Portal!");
        }
        break;
      }
      delay(10);
    }
  }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Mencoba koneksi MQTT...");
    String clientId = "ESP32Client-" + String(random(0xffff), HEX);
    if (client.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println("MQTT Terhubung");
    } else {
      Serial.print("Gagal, rc=");
      Serial.print(client.state());
      Serial.println(" coba lagi dalam 5 detik");
      delay(5000);
    }
  }
}

void loop() {
  checkButton();

  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  // Kirim data dummy setiap 10 detik
  static unsigned long lastMsg = 0;
  if (millis() - lastMsg > 10000) {
    lastMsg = millis();
    client.publish("users/1/devices/ESP32_PRO/sensors/temp", "{\"value\":25.5}");
  }
}
