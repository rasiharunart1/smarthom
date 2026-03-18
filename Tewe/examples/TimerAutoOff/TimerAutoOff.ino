// ============================================================
// 📦 Tewe — Timer Auto-Off Example
// ============================================================
//
// Toggle otomatis mati setelah durasi tertentu.
// Contoh: nyalakan lampu teras, auto-off setelah 5 menit.
//
// Cocok untuk:
//   - Lampu teras / garasi (auto-off malam)
//   - Pompa air (auto-off setelah N menit)
//   - Kipas (auto-off setelah 1 jam)
//
// ============================================================

#include <Tewe.h>

Tewe tewe;

// ── Timer Config ───────────────────────────────────────────
struct AutoOffTimer {
  const char *key;
  unsigned long durationMs; // durasi auto-off (ms)
  unsigned long startTime;  // kapan toggle dinyalakan
  bool active;              // timer sedang berjalan?
};

AutoOffTimer timers[] = {
    {"toggle1", 5UL * 60 * 1000, 0, false},  // 5 menit
    {"toggle2", 10UL * 60 * 1000, 0, false}, // 10 menit
    {"toggle3", 60UL * 60 * 1000, 0, false}, // 1 jam
};
const int TIMER_COUNT = sizeof(timers) / sizeof(timers[0]);

// ── Callback: start timer saat toggle ON ───────────────────
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s\n", key, state ? "ON" : "OFF");

  for (int i = 0; i < TIMER_COUNT; i++) {
    if (String(key) == timers[i].key) {
      if (state) {
        // Nyalakan → mulai timer
        timers[i].startTime = millis();
        timers[i].active = true;
        Serial.printf("  Timer %s: auto-off dalam %lu detik\n", key,
                      timers[i].durationMs / 1000);
      } else {
        // Matikan → cancel timer
        timers[i].active = false;
      }
      break;
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  tewe.setRelayActiveLow(true);
  tewe.mapPin("toggle1", 2); // D4 → Lampu Teras (auto-off 5 min)
  tewe.mapPin("toggle2", 4); // D2 → Pompa Air  (auto-off 10 min)
  tewe.mapPin("toggle3", 5); // D1 → Kipas      (auto-off 1 jam)

  tewe.onToggle(onToggleChanged);

  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "https://nh.mdpower.io/api/devices", "DEV_JQDK0QYUUJ");

  Serial.println("\n=== AUTO-OFF TIMERS ===");
  for (int i = 0; i < TIMER_COUNT; i++) {
    Serial.printf("%s → auto-off setelah %lu detik\n", timers[i].key,
                  timers[i].durationMs / 1000);
  }
  Serial.println("=======================\n");
}

void loop() {
  tewe.run();

  // ── Cek timer auto-off ───────────────────────────────────
  for (int i = 0; i < TIMER_COUNT; i++) {
    if (timers[i].active &&
        (millis() - timers[i].startTime >= timers[i].durationMs)) {
      timers[i].active = false;
      tewe.setState(timers[i].key, false);
      Serial.printf("⏰ Auto-OFF: %s\n", timers[i].key);
    }
  }
}
