// ============================================================
// 📦 Tewe — OLED Display Example
// ============================================================
//
// Menampilkan status WiFi, MQTT, IP, dan toggle di OLED SSD1306.
// Library tambahan: Adafruit_SSD1306, Adafruit_GFX
//
// ============================================================

#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <Tewe.h>
#include <Wire.h>


// --- OLED ---
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
#define OLED_SDA 14 // D5 NodeMCU
#define OLED_SCL 12 // D6 NodeMCU

Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

Tewe tewe;

// ── Update OLED ────────────────────────────────────────────
void updateOLED() {
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(WHITE);

  // Header
  display.setCursor(0, 0);
  display.println("TEWE GATEWAY");

  // WiFi
  display.setCursor(0, 10);
  display.print("WiFi: ");
  display.println(tewe.isWifiConnected() ? "OK" : "OFF");

  // MQTT
  display.setCursor(0, 20);
  display.print("MQTT: ");
  display.println(tewe.isMqttConnected() ? "OK" : "OFF");

  // IP
  display.setCursor(0, 30);
  display.print("IP: ");
  display.println(WiFi.localIP());

  // Toggle states (max 3 di layar)
  int y = 42;
  int count = tewe.getToggleCount();
  for (int i = 0; i < count && i < 3; i++) {
    String key = "toggle" + String(i + 1);
    display.setCursor(0, y);
    display.print(key);
    display.print(": ");
    display.println(tewe.getState(key.c_str()) ? "ON" : "OFF");
    y += 8;
  }

  display.display();
}

// ── Callback ───────────────────────────────────────────────
void onToggleChanged(const char *key, bool state) {
  Serial.printf("[%s] → %s\n", key, state ? "ON" : "OFF");
  updateOLED(); // refresh OLED setiap ada perubahan
}

void setup() {
  Serial.begin(115200);
  delay(500);

  // Init OLED
  Wire.begin(OLED_SDA, OLED_SCL);
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println("OLED FAIL");
  }
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(WHITE);
  display.setCursor(0, 20);
  display.println("BOOTING...");
  display.display();

  // Config Tewe
  tewe.setRelayActiveLow(true);
  tewe.mapPin("toggle1", 2);
  tewe.mapPin("toggle2", 4);
  tewe.mapPin("toggle3", 5);
  tewe.mapPin("toggle4", 13);
  tewe.mapPin("toggle5", 15);
  tewe.mapPin("toggle6", 16);
  tewe.onToggle(onToggleChanged);

  tewe.begin("NamaWiFi_Anda", "PasswordWiFi_Anda",
             "https://nh.mdpower.io/api/devices", "DEV_JQDK0QYUUJ");

  updateOLED();
}

void loop() {
  tewe.run();

  // Refresh OLED setiap 2 detik
  static unsigned long oledTimer = 0;
  if (millis() - oledTimer > 2000) {
    oledTimer = millis();
    updateOLED();
  }
}
