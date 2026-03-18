// ============================================================
// 📦 Tewe — Basic Toggle Example
// ============================================================
//
// Contoh penggunaan library Tewe untuk ESP8266 SmartHome.
// Cukup ~20 baris kode! Semua MQTT credential disembunyikan.
//
// Install library:
//   1. Copy folder Tewe/ ke Arduino/libraries/
//   2. Install juga: PubSubClient, ArduinoJson (v7)
//   Board: esp8266 by ESP8266 Community
//
// ============================================================

#include <Tewe.h>

Tewe tewe;

// Callback: dipanggil setiap toggle berubah (dari dashboard / serial)
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s\n", key, state ? "ON" : "OFF");
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  // --- Konfigurasi relay ---
  tewe.setRelayActiveLow(true); // true = relay module (active low)

  // --- Map widget key → GPIO pin ---
  // Sesuaikan dengan wiring hardware kamu
  tewe.mapPin("toggle1", 2);  // D4 NodeMCU
  tewe.mapPin("toggle2", 4);  // D2
  tewe.mapPin("toggle3", 5);  // D1
  tewe.mapPin("toggle4", 12); // D6
  tewe.mapPin("toggle5", 13); // D7
  tewe.mapPin("toggle6", 14); // D5
  tewe.mapPin("toggle7", 15); // D8
  tewe.mapPin("toggle8", 16); // D0
  // toggle9, toggle10 → tidak perlu mapPin (virtual/remote only)

  // --- Callback (opsional) ---
  tewe.onToggle(onToggleChanged);

  // --- Mulai! WiFi → Auth → Widgets → MQTT ---
  tewe.begin("NamaWiFi_Anda",                     // WiFi SSID
             "PasswordWiFi_Anda",                 // WiFi Password
             "https://nh.mdpower.io/api/devices", // API Base URL
             "DEV_JQDK0QYUUJ"                     // Device Code
  );
}

void loop() {
  tewe.run();

  // --- Contoh: manual control via kode ---
  // tewe.setState("toggle1", true);    // Set toggle1 ON
  // tewe.toggle("toggle1");            // Flip toggle1
  // tewe.publish("toggle1");           // Re-publish state
  // tewe.publishAll();                 // Re-publish semua

  // --- Contoh: cek status ---
  // if (tewe.isMqttConnected()) { ... }
  // bool state = tewe.getState("toggle1");
  // int count  = tewe.getToggleCount();
}
