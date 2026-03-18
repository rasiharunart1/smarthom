// ============================================================
// 📦 Tewe — Physical Button Example
// ============================================================
//
// Kontrol toggle dari tombol fisik (push button).
// Tekan tombol → flip toggle → publish ke dashboard.
// Dashboard juga bisa kontrol → relay berubah.
//
// Wiring: Button antara GPIO0 (D3, FLASH) dan GND
//         (sudah ada pull-up internal)
//
// ============================================================

#include <Tewe.h>

Tewe tewe;

// ── Button Config ──────────────────────────────────────────
#define BUTTON_PIN 0 // D3 / FLASH button (active LOW)
#define DEBOUNCE_MS 250

bool lastButtonState = HIGH;
unsigned long lastDebounce = 0;

// ── Callback ───────────────────────────────────────────────
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s\n", key, state ? "ON" : "OFF");
}

void setup() {
  Serial.begin(115200);
  delay(500);

  // Button input (internal pull-up)
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  // Config Tewe
  tewe.setRelayActiveLow(true);
  tewe.mapPin("toggle1", 2); // D4 → relay
  tewe.onToggle(onToggleChanged);

  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "https://nh.mdpower.io/api/devices", "DEV_JQDK0QYUUJ");
}

void loop() {
  tewe.run();

  // ── Baca tombol (debounced) ──────────────────────────────
  bool reading = digitalRead(BUTTON_PIN);

  if (reading == LOW && lastButtonState == HIGH &&
      (millis() - lastDebounce > DEBOUNCE_MS)) {
    lastDebounce = millis();

    // Flip toggle1
    tewe.toggle("toggle1");
    Serial.println("Button pressed → toggle1 flipped!");
  }

  lastButtonState = reading;
}
