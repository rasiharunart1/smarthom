/***************************************************
 *  NHSmart — Basic Example
 *  
 *  Contoh paling sederhana: 1 sensor + 1 relay.
 *  Cocok untuk pemula yang baru mulai.
 ***************************************************/

#include <NHSmart.h>

// ===== KONFIGURASI (Ganti dengan data Anda!) =====
#define DEVICE_CODE "DEV_XXXXXXXXXX"
#define SERVER_URL  "https://your-server.com"

char ssid[] = "YOUR_WIFI_SSID";
char pass[] = "YOUR_WIFI_PASSWORD";

// ===== HARDWARE =====
#define RELAY_PIN  18   // ESP32: GPIO18 | ESP8266: D5 (14)

NHTimer timer;

// ===== CALLBACK: Dashboard → ESP =====
void onRelay(String value) {
  int state = value.toInt();
  digitalWrite(RELAY_PIN, state);
  Serial.printf("💡 Relay → %s\n", state ? "ON" : "OFF");
}

// ===== SENSOR: ESP → Dashboard =====
void sendSensor() {
  float suhu = random(250, 350) / 10.0;   // Ganti dengan sensor asli
  NHSmart.virtualWrite("temp1", suhu);
  Serial.printf("🌡️ Suhu: %.1f°C\n", suhu);
}

// ===== SETUP =====
void setup() {
  Serial.begin(115200);
  
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);

  // Register callback: saat widget "toggle1" diklik → jalankan onRelay()
  NHSmart.onWrite("toggle1", onRelay);

  // Connect! (WiFi + Auth + MQTT semua otomatis)
  NHSmart.begin(DEVICE_CODE, ssid, pass, SERVER_URL);

  // Kirim sensor setiap 2 detik
  timer.setInterval(2000, sendSensor);
}

// ===== LOOP =====
void loop() {
  NHSmart.loop();  // Wajib!
  timer.run();
}
