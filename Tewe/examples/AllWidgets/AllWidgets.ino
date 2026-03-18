// ============================================================
// 📦 Tewe — All Widgets Example (Toggle, Slider, Gauge, Text)
// ============================================================
//
// Contoh lengkap semua tipe widget yang didukung Tewe:
//   - Toggle  → subscribe + publish (ON/OFF)
//   - Slider  → subscribe + publish (0-100)
//   - Gauge   → publish only (float, dari sensor)
//   - Text    → subscribe + publish (string)
//
// ============================================================

#include <Tewe.h>

Tewe tewe;

// ── CALLBACK: Toggle berubah (dari dashboard) ──────────────
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[TOGGLE] %s → %s\n", key, state ? "ON" : "OFF");

  // Contoh: jika toggle1 ON, kirim status "Lampu Nyala"
  if (String(key) == "toggle1") {
    tewe.setText("text1", state ? "Lampu Nyala" : "Lampu Mati");
  }
}

// ── CALLBACK: Slider berubah (dari dashboard) ──────────────
void onSliderChanged(const char *key, int value) {
  Serial.printf("[SLIDER] %s → %d\n", key, value);

  // Contoh: slider1 kontrol PWM LED
  if (String(key) == "slider1") {
    int pwm = map(value, 0, 100, 0, 1023);
    // analogWrite(LED_PIN, pwm);  // uncomment jika pakai PWM
    Serial.printf("  PWM = %d\n", pwm);
  }

  // Contoh: slider2 kontrol servo angle
  if (String(key) == "slider2") {
    Serial.printf("  Servo angle = %d°\n", value);
  }
}

// ── CALLBACK: Text berubah (dari dashboard) ────────────────
void onTextChanged(const char *key, const char *value) {
  Serial.printf("[TEXT] %s → \"%s\"\n", key, value);

  // Contoh: text widget sebagai command receiver
  if (String(key) == "text1") {
    if (String(value) == "reset") {
      Serial.println("  Reset command received!");
      // lakukan reset...
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  // ── Config ───────────────────────────────────────────────
  tewe.setRelayActiveLow(true);

  // Map toggle ke GPIO (hanya untuk toggle)
  tewe.mapPin("toggle1", 2); // D4 → Relay 1
  tewe.mapPin("toggle2", 4); // D2 → Relay 2

  // ── Register callbacks ───────────────────────────────────
  tewe.onToggle(onToggleChanged);
  tewe.onSlider(onSliderChanged);
  tewe.onText(onTextChanged);

  // ── Start! ───────────────────────────────────────────────
  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "https://nh.mdpower.io/api/devices", "DEV_JQDK0QYUUJ");
}

void loop() {
  tewe.run();

  // ── Contoh: kirim data sensor ke Gauge setiap 5 detik ───
  static unsigned long sensorTimer = 0;
  if (millis() - sensorTimer >= 5000) {
    sensorTimer = millis();

    // Simulasi pembacaan sensor (ganti dengan sensor asli)
    float suhu = 25.0 + random(-50, 50) / 10.0;
    float kelembapan = 60.0 + random(-100, 100) / 10.0;

    // Publish ke gauge widget
    tewe.publishGauge("gauge1", suhu);
    tewe.publishGauge("gauge2", kelembapan);

    Serial.printf("Sensor → Suhu=%.1f°C  Kelembapan=%.1f%%\n", suhu,
                  kelembapan);
  }

  // ── Contoh: update text widget setiap 10 detik ──────────
  static unsigned long textTimer = 0;
  if (millis() - textTimer >= 10000) {
    textTimer = millis();

    String uptime = String(millis() / 1000) + "s";
    tewe.setText("text2", uptime.c_str());
  }

  // ══════════════════════════════════════════════════════════
  // RINGKASAN API PER WIDGET TYPE:
  // ══════════════════════════════════════════════════════════
  //
  // TOGGLE:
  //   tewe.onToggle(callback);             ← subscribe
  //   tewe.setState("toggle1", true);      ← set + publish
  //   tewe.toggle("toggle1");              ← flip + publish
  //   bool s = tewe.getState("toggle1");   ← baca state
  //
  // SLIDER:
  //   tewe.onSlider(callback);             ← subscribe
  //   tewe.setSlider("slider1", 75);       ← set + publish
  //   int v = tewe.getSlider("slider1");   ← baca value
  //
  // GAUGE (publish only, tidak ada subscribe):
  //   tewe.publishGauge("gauge1", 25.5);   ← kirim nilai
  //
  // TEXT:
  //   tewe.onText(callback);               ← subscribe
  //   tewe.setText("text1", "Hello");       ← set + publish
  //   String t = tewe.getText("text1");     ← baca text
  //
  // GENERIC:
  //   tewe.publish("key");                 ← re-publish satu
  //   tewe.publishAll();                   ← re-publish semua
  //   tewe.publishRaw("key", "raw data");  ← publish raw string
  //   tewe.getWidgetType("key");           ← TEWE_TOGGLE/SLIDER/...
  //   tewe.getWidgetCount();               ← total widgets
  //
  // SERIAL COMMANDS:
  //   on [N]          → toggle ON
  //   off [N]         → toggle OFF
  //   t [N]           → flip toggle
  //   set KEY VALUE   → set any widget value
  //   s               → status semua widget
  //   pub             → re-publish semua
  // ══════════════════════════════════════════════════════════
}
