// ============================================================
// 📦 Tewe — Multi-Room Smart Home Example
// ============================================================
//
// Contoh lengkap smart home multi-ruangan.
// Setiap toggle di-map ke relay dan diberi label ruangan.
// Callback digunakan untuk logic tambahan (misalnya:
// matikan semua jika toggle "master" dimatikan).
//
// ============================================================

#include <Tewe.h>

Tewe tewe;

// ── Callback: logic otomatis ───────────────────────────────
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s\n", key, state ? "ON" : "OFF");

  // ── Master switch logic ────────────────────────────────
  // Jika toggle1 (master) dimatikan → matikan semua
  if (String(key) == "toggle1" && !state) {
    Serial.println("Master OFF → matikan semua...");
    tewe.setState("toggle2", false);
    tewe.setState("toggle3", false);
    tewe.setState("toggle4", false);
    tewe.setState("toggle5", false);
    tewe.setState("toggle6", false);
  }

  // ── Auto-on logic ─────────────────────────────────────
  // Jika ada toggle yang dinyalakan, pastikan master juga ON
  if (String(key) != "toggle1" && state) {
    if (!tewe.getState("toggle1")) {
      Serial.println("Auto-ON master switch");
      tewe.setState("toggle1", true);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  tewe.setRelayActiveLow(true);
  tewe.setHeartbeatInterval(30000); // heartbeat setiap 30 detik

  // Map ruangan ke GPIO
  tewe.mapPin("toggle1", 2);  // D4 → Master / Utama
  tewe.mapPin("toggle2", 4);  // D2 → Ruang Tamu
  tewe.mapPin("toggle3", 5);  // D1 → Kamar Tidur
  tewe.mapPin("toggle4", 12); // D6 → Dapur
  tewe.mapPin("toggle5", 13); // D7 → Kamar Mandi
  tewe.mapPin("toggle6", 14); // D5 → Teras

  tewe.onToggle(onToggleChanged);

  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "https://nh.mdpower.io/api/devices", "DEV_JQDK0QYUUJ");

  Serial.println("\n=== ROOM MAP ===");
  Serial.println("toggle1 = Master");
  Serial.println("toggle2 = Ruang Tamu");
  Serial.println("toggle3 = Kamar Tidur");
  Serial.println("toggle4 = Dapur");
  Serial.println("toggle5 = Kamar Mandi");
  Serial.println("toggle6 = Teras");
  Serial.println("================\n");
}

void loop() { tewe.run(); }
