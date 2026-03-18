// ============================================================
// 📦 Tewe — Local HTTP Server Example
// ============================================================
//
// Untuk development / server lokal TANPA SSL.
// Cukup ganti URL ke http:// (bukan https://).
// Tewe otomatis detect dan gunakan HTTP biasa.
//
// ============================================================

#include <Tewe.h>

Tewe tewe;

void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s\n", key, state ? "ON" : "OFF");
}

void setup() {
  Serial.begin(115200);
  delay(500);

  tewe.setRelayActiveLow(true);
  tewe.mapPin("toggle1", 2);

  tewe.onToggle(onToggleChanged);

  // Perhatikan: http:// (bukan https://)
  // Tewe otomatis switch ke plain HTTP + MQTT non-TLS
  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "http://192.168.1.100:8000/api/devices", // ← Server lokal
             "DEV_JQDK0QYUUJ");
}

void loop() { tewe.run(); }
