/***************************************************
 *  SmartHome IoT — Contoh Lengkap Semua Widget
 *  ============================================
 *  Kompatibel: ESP32 & ESP8266
 *  
 *  Contoh ini mendemonstrasikan SEMUA tipe widget
 *  yang didukung oleh platform Tewe.io:
 *
 *  📊 SENSOR (SmartHome.virtualWrite)
 *     - gauge   → Menampilkan nilai numerik (suhu, tegangan, dll)
 *     - text    → Menampilkan teks (status, label, dll)
 *     - chart   → Data historis (otomatis dari source_key gauge)
 *
 *  🎮 CONTROL (SmartHome.onWrite)
 *     - toggle  → Kontrol ON/OFF (relay, lampu, fan)
 *     - slider  → Kontrol nilai variabel (PWM, brightness)
 *
 *  🔐 KEAMANAN:
 *     - Autentikasi HTTPS + Sanctum Token
 *     - MQTT via TLS (port 8883)
 *     - setCACert() tersedia untuk verifikasi sertifikat production
 *
 *  📝 CARA PAKAI:
 *     1. Buat Device di dashboard → copy DEVICE_CODE
 *     2. Buat widget di dashboard (gauge, toggle, slider, text)
 *     3. Catat "Widget Key" masing-masing widget
 *     4. Ganti konfigurasi di bawah sesuai key widget Anda
 *     5. Upload ke ESP32/ESP8266
 ***************************************************/

#include <SmartHomeIoT.h>

// ╔══════════════════════════════════════════════════╗
// ║              KONFIGURASI WAJIB                   ║
// ║   Ganti semua nilai ini sesuai akun Anda!        ║
// ╚══════════════════════════════════════════════════╝

#define DEVICE_CODE "DEV_XXXXXXXXXX"          // ← Device code dari dashboard
#define SERVER_URL  "https://your-server.com"  // ← URL server Laravel Anda

char ssid[] = "YOUR_WIFI_SSID";               // ← Nama WiFi
char pass[] = "YOUR_WIFI_PASSWORD";            // ← Password WiFi

// ╔══════════════════════════════════════════════════╗
// ║            KONFIGURASI PIN HARDWARE              ║
// ╚══════════════════════════════════════════════════╝

// --- ACTUATOR (Output) ---
#define PIN_RELAY1    18    // ESP32: GPIO18 | ESP8266: D5 (14)
#define PIN_RELAY2    19    // ESP32: GPIO19 | ESP8266: D6 (12)
#define PIN_LED_PWM   21    // ESP32: GPIO21 | ESP8266: D7 (13) — untuk slider dimmer

// --- SENSOR (Input) ---
#define PIN_LDR       34    // ESP32: GPIO34 (ADC) | ESP8266: A0
#define PIN_SOIL      35    // ESP32: GPIO35 (ADC) | ESP8266: tidak tersedia

// PWM Config (ESP32 only)
#ifdef ESP32
  #define PWM_CHANNEL   0
  #define PWM_FREQ      5000
  #define PWM_RESOLUTION 8   // 0-255
#endif

// ╔══════════════════════════════════════════════════╗
// ║              WIDGET KEY MAPPING                  ║
// ║                                                  ║
// ║  Key ini HARUS sama persis dengan "Widget Key"   ║
// ║  yang dibuat di Dashboard web.                   ║
// ╚══════════════════════════════════════════════════╝

// --- SENSOR KEYS (gauge / text / chart) ---
// Dikirim dari ESP → Dashboard via SmartHome.virtualWrite()
#define KEY_TEMPERATURE "temp1"       // Widget: gauge
#define KEY_HUMIDITY    "humidity"     // Widget: gauge
#define KEY_VOLTAGE     "voltage"     // Widget: gauge
#define KEY_CURRENT     "current"     // Widget: gauge
#define KEY_POWER       "power"       // Widget: gauge
#define KEY_ENERGY      "energy"      // Widget: gauge
#define KEY_LDR         "ldr"         // Widget: gauge (cahaya)
#define KEY_GAS         "gas"         // Widget: gauge
#define KEY_STATUS      "status_text" // Widget: text

// --- CONTROL KEYS (toggle / slider) ---
// Diterima dari Dashboard → ESP via SmartHome.onWrite()
#define KEY_TOGGLE1  "toggle1"    // Widget: toggle → Relay 1
#define KEY_TOGGLE2  "toggle2"    // Widget: toggle → Relay 2
#define KEY_SLIDER1  "slider1"    // Widget: slider → PWM / Brightness

// ╔══════════════════════════════════════════════════╗
// ║              GLOBAL VARIABLES                    ║
// ╚══════════════════════════════════════════════════╝

SmartHomeTimer sensorTimer;   // Timer kirim sensor data
SmartHomeTimer statusTimer;   // Timer kirim status text

// Simulated sensor values (ganti dengan pembacaan sensor asli)
float temperature = 28.0;
float humidity    = 65.0;
float voltage     = 220.0;
float current_a   = 1.5;
float power_w     = 330.0;
float energy_kwh  = 0.0;
int   gasValue    = 200;
int   ldrValue    = 500;
int   sliderValue = 0;

// State tracking untuk actuator
bool relay1State = false;
bool relay2State = false;

// ╔══════════════════════════════════════════════════╗
// ║           SMOOTHING HELPER                       ║
// ║  Menghaluskan perubahan nilai sensor             ║
// ╚══════════════════════════════════════════════════╝

float smooth(float prev, float current, float alpha = 0.3) {
  return (alpha * current) + ((1.0 - alpha) * prev);
}

// ╔══════════════════════════════════════════════════╗
// ║        CONTROL CALLBACKS (Mirip BLYNK_WRITE)     ║
// ║                                                  ║
// ║  Fungsi ini dipanggil OTOMATIS oleh library      ║
// ║  ketika user klik/geser widget di Dashboard.     ║
// ╚══════════════════════════════════════════════════╝

/**
 * 🔌 TOGGLE 1 — Relay / Lampu
 * 
 * Nilai yang diterima: "1" / "0" / "true" / "false" / "on" / "off"
 * Library sudah menormalisasi → cukup pakai toInt()
 */
void onToggle1(String value) {
  relay1State = (value.toInt() == 1);
  digitalWrite(PIN_RELAY1, relay1State ? HIGH : LOW);
  Serial.printf("💡 Relay 1 → %s\n", relay1State ? "ON" : "OFF");
}

/**
 * 🔌 TOGGLE 2 — Relay / Fan / Pompa
 */
void onToggle2(String value) {
  relay2State = (value.toInt() == 1);
  digitalWrite(PIN_RELAY2, relay2State ? HIGH : LOW);
  Serial.printf("🌀 Relay 2 → %s\n", relay2State ? "ON" : "OFF");
}

/**
 * 🎚️ SLIDER 1 — PWM Dimmer / Brightness
 * 
 * Nilai: "0" s/d "255" (atau sesuai range min/max di dashboard)
 * Cocok untuk: LED dimmer, kecepatan motor, dll
 */
void onSlider1(String value) {
  sliderValue = constrain(value.toInt(), 0, 255);
  
  #ifdef ESP32
    ledcWrite(PWM_CHANNEL, sliderValue);
  #else
    analogWrite(PIN_LED_PWM, sliderValue);
  #endif
  
  Serial.printf("🎚️ Slider → %d (%.0f%%)\n", sliderValue, (sliderValue / 255.0) * 100);
}

// ╔══════════════════════════════════════════════════╗
// ║       SENSOR DATA — Kirim ke Dashboard           ║
// ║                                                  ║
// ║  Dipanggil otomatis oleh timer setiap 2 detik.   ║
// ║  Ganti bagian "simulasi" dengan pembacaan asli.  ║
// ╚══════════════════════════════════════════════════╝

void sendSensorData() {
  // ── 1. BACA SENSOR (Ganti dengan sensor asli Anda) ──────────
  
  // Contoh: DHT22 / DS18B20 / BME280
  temperature = smooth(temperature, random(250, 350) / 10.0);
  humidity    = smooth(humidity,    random(400, 800) / 10.0);

  // Contoh: PZEM-004T / INA219
  voltage   = smooth(voltage,  random(215, 225));
  current_a = smooth(current_a, random(10, 50) / 10.0);
  power_w   = voltage * current_a;
  energy_kwh += power_w / 3600000.0;  // kWh accumulation per 2s interval

  // Contoh: MQ-2 / MQ-135 Gas Sensor
  gasValue = (int)smooth(gasValue, random(100, 600));

  // Contoh: LDR / BH1750 Light Sensor
  #ifdef ESP32
    ldrValue = analogRead(PIN_LDR);  // 0-4095
    ldrValue = map(ldrValue, 0, 4095, 0, 100);  // Normalize ke 0-100%
  #else
    ldrValue = analogRead(A0);       // 0-1023
    ldrValue = map(ldrValue, 0, 1023, 0, 100);
  #endif

  // ── 2. KIRIM KE DASHBOARD (SmartHome.virtualWrite) ──────────
  //    Key harus SAMA PERSIS dengan Widget Key di dashboard
  //    Data otomatis tampil di gauge/chart yang sesuai

  // Gauge widgets
  SmartHome.virtualWrite(KEY_TEMPERATURE, temperature);
  SmartHome.virtualWrite(KEY_HUMIDITY,    humidity);
  SmartHome.virtualWrite(KEY_VOLTAGE,     voltage);
  SmartHome.virtualWrite(KEY_CURRENT,     current_a);
  SmartHome.virtualWrite(KEY_POWER,       power_w);
  SmartHome.virtualWrite(KEY_ENERGY,      energy_kwh);
  SmartHome.virtualWrite(KEY_GAS,         gasValue);
  SmartHome.virtualWrite(KEY_LDR,         ldrValue);

  // ── 3. DEBUG OUTPUT ─────────────────────────────────────────
  Serial.println("╔════════════ DATA TERKIRIM ════════════╗");
  Serial.printf( "║ 🌡️  Suhu    : %.1f °C               \n", temperature);
  Serial.printf( "║ 💧 Humidity : %.1f %%                 \n", humidity);
  Serial.printf( "║ ⚡ Voltage  : %.1f V                 \n", voltage);
  Serial.printf( "║ 🔌 Current  : %.2f A                 \n", current_a);
  Serial.printf( "║ 💡 Power    : %.1f W                 \n", power_w);
  Serial.printf( "║ 🔥 Gas      : %d ppm                 \n", gasValue);
  Serial.printf( "║ ☀️  Cahaya   : %d %%                  \n", ldrValue);
  Serial.printf( "║ 🎚️  Slider   : %d                    \n", sliderValue);
  Serial.printf( "║ 💡 Relay1   : %s | Relay2: %s        \n",
                 relay1State ? "ON" : "OFF",
                 relay2State ? "ON" : "OFF");
  Serial.println("╚══════════════════════════════════════╝");
}

/**
 * 📝 KIRIM STATUS TEXT
 * 
 * Widget tipe "text" bisa menampilkan pesan/status apapun.
 * Contoh: status koneksi, mode operasi, peringatan, dll.
 */
void sendStatusText() {
  // Buat status string dinamis
  char statusMsg[64];
  
  if (gasValue > 500) {
    snprintf(statusMsg, sizeof(statusMsg), "⚠️ Gas tinggi! (%d ppm)", gasValue);
  } else if (temperature > 35.0) {
    snprintf(statusMsg, sizeof(statusMsg), "🔥 Suhu tinggi! (%.1f°C)", temperature);
  } else {
    unsigned long uptimeMin = millis() / 60000;
    snprintf(statusMsg, sizeof(statusMsg), "✅ Normal — Uptime: %lu menit", uptimeMin);
  }

  // Kirim ke widget text di dashboard
  SmartHome.virtualWrite(KEY_STATUS, statusMsg);
  
  Serial.printf("📝 Status: %s\n", statusMsg);
}

// ╔══════════════════════════════════════════════════╗
// ║                    SETUP                         ║
// ╚══════════════════════════════════════════════════╝

void setup() {
  Serial.begin(115200);
  delay(100);
  
  Serial.println("\n");
  Serial.println("╔══════════════════════════════════════════╗");
  Serial.println("║    SmartHome IoT — All Widget Example    ║");
  Serial.println("║    Platform: Tewe.io                     ║");
  Serial.println("╚══════════════════════════════════════════╝");

  // ── 1. SETUP PIN HARDWARE ────────────────────────────────
  pinMode(PIN_RELAY1, OUTPUT);
  pinMode(PIN_RELAY2, OUTPUT);
  digitalWrite(PIN_RELAY1, LOW);
  digitalWrite(PIN_RELAY2, LOW);

  #ifdef ESP32
    // ESP32 PWM setup via LEDC
    ledcSetup(PWM_CHANNEL, PWM_FREQ, PWM_RESOLUTION);
    ledcAttachPin(PIN_LED_PWM, PWM_CHANNEL);
    ledcWrite(PWM_CHANNEL, 0);
  #else
    pinMode(PIN_LED_PWM, OUTPUT);
    analogWrite(PIN_LED_PWM, 0);
  #endif

  randomSeed(analogRead(0));  // Seed random untuk simulasi

  // ── 2. REGISTER CONTROL CALLBACKS ────────────────────────
  //    Saat user klik toggle/geser slider di dashboard web,
  //    fungsi callback akan dipanggil otomatis.
  //    Key HARUS sama persis dengan Widget Key di dashboard!
  
  SmartHome.onWrite(KEY_TOGGLE1, onToggle1);   // toggle1 → Relay 1
  SmartHome.onWrite(KEY_TOGGLE2, onToggle2);   // toggle2 → Relay 2
  SmartHome.onWrite(KEY_SLIDER1, onSlider1);   // slider1 → PWM Dimmer

  // ── 3. (OPSIONAL) TLS CERTIFICATE UNTUK PRODUCTION ──────
  //    Uncomment baris di bawah dan ganti dengan sertifikat
  //    root CA server Anda untuk keamanan penuh.
  //    Tanpa ini, koneksi tetap terenkripsi tapi rentan MITM.
  //
  // const char* ca_cert = R"EOF(
  // -----BEGIN CERTIFICATE-----
  // MIIDdzCCAl+gAwIBAgIEAgAAuTANBgk... (isi cert CA Anda)
  // -----END CERTIFICATE-----
  // )EOF";
  // SmartHome.setCACert(ca_cert);

  // ── 4. MULAI KONEKSI ────────────────────────────────────
  //    Library otomatis menghandle:
  //    ✅ Connect WiFi
  //    ✅ POST Auth HTTPS → dapat Sanctum Token
  //    ✅ GET MQTT Config → dapat kredensial MQTT per-device
  //    ✅ Connect MQTT TLS → subscribe ke control/#
  //    ✅ Auto-reconnect jika terputus
  
  SmartHome.begin(DEVICE_CODE, ssid, pass, SERVER_URL);

  // ── 5. SETUP TIMER ───────────────────────────────────────
  sensorTimer.setInterval(2000L, sendSensorData);   // Kirim sensor tiap 2 detik
  statusTimer.setInterval(10000L, sendStatusText);   // Kirim status tiap 10 detik
}

// ╔══════════════════════════════════════════════════╗
// ║                    LOOP                          ║
// ╚══════════════════════════════════════════════════╝

void loop() {
  // WAJIB — menjaga MQTT tetap hidup + auto-reconnect
  SmartHome.loop();
  
  // Jalankan semua timer
  sensorTimer.run();
  statusTimer.run();
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                   CATATAN PENTING                           ║
// ╠══════════════════════════════════════════════════════════════╣
// ║                                                             ║
// ║  📊 WIDGET TYPE DI DASHBOARD:                               ║
// ║                                                             ║
// ║  ┌──────────┬──────────┬────────────────────────────────┐  ║
// ║  │ Tipe     │ Arah     │ Contoh                         │  ║
// ║  ├──────────┼──────────┼────────────────────────────────┤  ║
// ║  │ gauge    │ ESP→Web  │ Suhu, Tegangan, Humidity       │  ║
// ║  │ text     │ ESP→Web  │ Status perangkat, peringatan   │  ║
// ║  │ chart    │ ESP→Web  │ Grafik historis (auto-log)     │  ║
// ║  │ toggle   │ Web→ESP  │ Relay ON/OFF, Lampu, Pompa    │  ║
// ║  │ slider   │ Web→ESP  │ Dimmer LED, Kecepatan Motor   │  ║
// ║  └──────────┴──────────┴────────────────────────────────┘  ║
// ║                                                             ║
// ║  🔗 CHART SOURCE KEY:                                       ║
// ║  Widget chart di dashboard memiliki "source_key" yang       ║
// ║  otomatis menghubungkan ke gauge widget.                    ║
// ║  Contoh: chart dengan source_key="temp1" akan otomatis      ║
// ║  menampilkan grafik dari data gauge suhu.                   ║
// ║  Tidak perlu kode tambahan di firmware!                     ║
// ║                                                             ║
// ║  🔐 KEAMANAN:                                               ║
// ║  1. Device harus di-approve admin sebelum bisa konek       ║
// ║  2. Token Sanctum otomatis dikelola library                ║
// ║  3. MQTT pakai TLS (port 8883)                             ║
// ║  4. Panggil setCACert() untuk production                   ║
// ║  5. JANGAN hardcode kredensial WiFi di public repo!        ║
// ║                                                             ║
// ╚══════════════════════════════════════════════════════════════╝
