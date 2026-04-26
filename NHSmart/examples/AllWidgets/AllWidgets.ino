/***************************************************
 *  NHSmart — All Widgets Example
 *  
 *  Demonstrasi LENGKAP semua tipe widget:
 *  
 *  📊 SENSOR (NHSmart.virtualWrite)
 *     gauge  → suhu, tegangan, humidity, gas, cahaya
 *     text   → status perangkat
 *     chart  → otomatis dari source_key gauge
 *
 *  🎮 CONTROL (NHSmart.onWrite)
 *     toggle → relay ON/OFF
 *     slider → PWM dimmer
 ***************************************************/

#include <NHSmart.h>

// ╔══════════════════════════════════════════╗
// ║          KONFIGURASI WAJIB               ║
// ╚══════════════════════════════════════════╝

#define DEVICE_CODE "DEV_XXXXXXXXXX"          // ← dari dashboard
#define SERVER_URL  "https://your-server.com"  // ← URL server Anda

char ssid[] = "YOUR_WIFI_SSID";
char pass[] = "YOUR_WIFI_PASSWORD";

// ╔══════════════════════════════════════════╗
// ║           PIN HARDWARE                   ║
// ╚══════════════════════════════════════════╝

#define PIN_RELAY1    18    // ESP32: GPIO18 | ESP8266: D5
#define PIN_RELAY2    19    // ESP32: GPIO19 | ESP8266: D6
#define PIN_LED_PWM   21    // ESP32: GPIO21 | ESP8266: D7
#define PIN_LDR       34    // ESP32: GPIO34 (ADC)

#ifdef ESP32
  #define PWM_CH   0
  #define PWM_FREQ 5000
  #define PWM_RES  8
#endif

// ╔══════════════════════════════════════════╗
// ║        WIDGET KEY MAPPING                ║
// ║  Harus SAMA dengan key di Dashboard!     ║
// ╚══════════════════════════════════════════╝

// Sensor → Dashboard
#define KEY_TEMP     "temp1"
#define KEY_HUMIDITY "humidity"
#define KEY_VOLTAGE  "voltage"
#define KEY_CURRENT  "current"
#define KEY_POWER    "power"
#define KEY_GAS      "gas"
#define KEY_LDR      "ldr"
#define KEY_STATUS   "status_text"

// Dashboard → Control
#define KEY_RELAY1   "toggle1"
#define KEY_RELAY2   "toggle2"
#define KEY_SLIDER   "slider1"

// ╔══════════════════════════════════════════╗
// ║           GLOBAL VARIABLES               ║
// ╚══════════════════════════════════════════╝

NHTimer timer;

float temp     = 28.0;
float humidity = 65.0;
float voltage  = 220.0;
float current_a = 1.5;
int   gasVal   = 200;
int   ldrVal   = 50;
int   sliderVal = 0;
bool  relay1   = false;
bool  relay2   = false;

// Smoothing helper
float smooth(float prev, float cur, float a = 0.3) {
  return (a * cur) + ((1.0 - a) * prev);
}

// ╔══════════════════════════════════════════╗
// ║      CONTROL CALLBACKS (Web → ESP)       ║
// ╚══════════════════════════════════════════╝

void onRelay1(String value) {
  relay1 = (value.toInt() == 1);
  digitalWrite(PIN_RELAY1, relay1 ? HIGH : LOW);
  Serial.printf("💡 Relay 1 → %s\n", relay1 ? "ON" : "OFF");
}

void onRelay2(String value) {
  relay2 = (value.toInt() == 1);
  digitalWrite(PIN_RELAY2, relay2 ? HIGH : LOW);
  Serial.printf("🌀 Relay 2 → %s\n", relay2 ? "ON" : "OFF");
}

void onSlider(String value) {
  sliderVal = constrain(value.toInt(), 0, 255);
  #ifdef ESP32
    ledcWrite(PWM_CH, sliderVal);
  #else
    analogWrite(PIN_LED_PWM, sliderVal);
  #endif
  Serial.printf("🎚️ Slider → %d\n", sliderVal);
}

// ╔══════════════════════════════════════════╗
// ║     SENSOR DATA (ESP → Dashboard)        ║
// ╚══════════════════════════════════════════╝

void sendSensors() {
  // Simulasi — ganti dengan sensor asli (DHT22, PZEM, MQ-2, dll)
  temp      = smooth(temp,      random(250, 350) / 10.0);
  humidity  = smooth(humidity,  random(400, 800) / 10.0);
  voltage   = smooth(voltage,   random(215, 225));
  current_a = smooth(current_a, random(10, 50) / 10.0);
  gasVal    = (int)smooth(gasVal, random(100, 600));
  
  #ifdef ESP32
    ldrVal = map(analogRead(PIN_LDR), 0, 4095, 0, 100);
  #else
    ldrVal = map(analogRead(A0), 0, 1023, 0, 100);
  #endif

  // Kirim ke widget gauge di dashboard
  NHSmart.virtualWrite(KEY_TEMP,     temp);
  NHSmart.virtualWrite(KEY_HUMIDITY, humidity);
  NHSmart.virtualWrite(KEY_VOLTAGE,  voltage);
  NHSmart.virtualWrite(KEY_CURRENT,  current_a);
  NHSmart.virtualWrite(KEY_POWER,    voltage * current_a);
  NHSmart.virtualWrite(KEY_GAS,      gasVal);
  NHSmart.virtualWrite(KEY_LDR,      ldrVal);

  Serial.printf("📊 T:%.1f H:%.1f V:%.1f I:%.2f G:%d L:%d\n",
                temp, humidity, voltage, current_a, gasVal, ldrVal);
}

void sendStatus() {
  char msg[64];
  if (gasVal > 500) {
    snprintf(msg, sizeof(msg), "⚠️ Gas tinggi! (%d ppm)", gasVal);
  } else if (temp > 35.0) {
    snprintf(msg, sizeof(msg), "🔥 Suhu tinggi! (%.1f°C)", temp);
  } else {
    snprintf(msg, sizeof(msg), "✅ Normal | Up: %lus | %ddBm",
             NHSmart.uptime(), NHSmart.rssi());
  }
  NHSmart.virtualWrite(KEY_STATUS, msg);
}

// ╔══════════════════════════════════════════╗
// ║               SETUP                      ║
// ╚══════════════════════════════════════════╝

void setup() {
  Serial.begin(115200);

  // Hardware setup
  pinMode(PIN_RELAY1, OUTPUT);
  pinMode(PIN_RELAY2, OUTPUT);
  digitalWrite(PIN_RELAY1, LOW);
  digitalWrite(PIN_RELAY2, LOW);

  #ifdef ESP32
    ledcSetup(PWM_CH, PWM_FREQ, PWM_RES);
    ledcAttachPin(PIN_LED_PWM, PWM_CH);
  #else
    pinMode(PIN_LED_PWM, OUTPUT);
  #endif

  randomSeed(analogRead(0));

  // Register control callbacks (Dashboard → ESP)
  NHSmart.onWrite(KEY_RELAY1, onRelay1);
  NHSmart.onWrite(KEY_RELAY2, onRelay2);
  NHSmart.onWrite(KEY_SLIDER, onSlider);

  // (Opsional) Set CA cert untuk production TLS
  // NHSmart.setCACert(ca_cert_pem);

  // Connect! (WiFi + Auth + MQTT otomatis)
  NHSmart.begin(DEVICE_CODE, ssid, pass, SERVER_URL);

  // Timer: kirim data berkala
  timer.setInterval(2000, sendSensors);    // setiap 2 detik
  timer.setInterval(10000, sendStatus);    // setiap 10 detik
}

// ╔══════════════════════════════════════════╗
// ║                LOOP                      ║
// ╚══════════════════════════════════════════╝

void loop() {
  NHSmart.loop();  // Wajib — keeps everything alive
  timer.run();     // Jalankan semua timer
}
