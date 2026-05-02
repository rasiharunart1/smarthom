#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

#include <Wire.h>

#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>

#include "MAX30100_PulseOximeter.h"

#include <OneWire.h>
#include <DallasTemperature.h>

#include <ArduinoJson.h>

// ============================================================================
// WIFI
// ============================================================================

#define WIFI_SSID       "Harun"
#define WIFI_PASSWORD   "harun3211"

// ============================================================================
// FIREBASE
// ============================================================================

#define DATABASE_URL \
"lansiafall-default-rtdb.asia-southeast1.firebasedatabase.app"

#define DATABASE_SECRET \
"l7pwNcHHiFDuhp6UDNmgtTM2w1U40sHecu79v4wG"

// ============================================================================
// PIN
// ============================================================================

#define SDA_PIN         21
#define SCL_PIN         22

#define ONE_WIRE_BUS    4

// ============================================================================
// PERIOD
// ============================================================================

#define FIREBASE_PERIOD_MS   5000
#define CONFIG_PERIOD_MS     10000

// ============================================================================
// OBJECT
// ============================================================================

Adafruit_MPU6050 mpu;

PulseOximeter pox;

OneWire oneWire(ONE_WIRE_BUS);

DallasTemperature tempSensor(&oneWire);

WiFiClientSecure secureClient;

// ============================================================================
// TASK
// ============================================================================

TaskHandle_t taskMAXHandle;
TaskHandle_t taskSensorHandle;
TaskHandle_t taskFirebaseHandle;
TaskHandle_t taskConfigHandle;
TaskHandle_t taskWiFiHandle;

// ============================================================================
// MUTEX
//
// FIX #1 (UTAMA): Satu mutex I2C untuk semua perangkat I2C.
// MAX30100 (0x57) dan MPU6050 (0x68) berbagi bus fisik Wire yang sama.
// Dua mutex terpisah TIDAK mencegah tabrakan bus → bus corruption.
// Gunakan SATU mutex xI2CMutex untuk seluruh operasi Wire.
//
// FIX #2: xDataMutex untuk melindungi fallDirection (char[]).
// ============================================================================

SemaphoreHandle_t xI2CMutex;       // satu mutex untuk seluruh bus I2C
SemaphoreHandle_t xFirebaseMutex;  // untuk HTTP client
SemaphoreHandle_t xDataMutex;      // untuk fallDirection + fallDetected

// ============================================================================
// SENSOR VALUE
// ============================================================================

volatile float heartRate   = 0;
volatile int   spo2        = 0;

volatile float temperature = 0;

volatile float accX = 0;
volatile float accY = 0;
volatile float accZ = 0;

volatile float gyrX = 0;
volatile float gyrY = 0;
volatile float gyrZ = 0;

volatile float roll  = 0;
volatile float pitch = 0;

// ============================================================================
// FALL
//
// FIX #3 (UTAMA): Ganti String fallDirection → char fallDirection[16].
// String Arduino menggunakan heap (malloc/free) secara dinamis.
// Ketika taskSensor menulis (realloc) dan taskFirebase membaca secara
// bersamaan tanpa mutex, terjadi heap corruption → CORRUPT HEAP crash.
// char[] adalah stack/BSS memory: no malloc, no race on heap.
// ============================================================================

volatile bool   fallDetected      = false;
char            fallDirection[16] = "NORMAL";   // ← char[], bukan String
volatile bool   positionTriggered = false;

uint32_t positionStartMs = 0;

// ============================================================================
// THRESHOLD FROM FIREBASE
// ============================================================================

float    g_rollThreshold  = 50.0;
float    g_pitchThreshold = 50.0;
uint32_t g_fallDelayMs    = 2500;

// ============================================================================
// WIFI
// ============================================================================

volatile bool wifiConnected = false;

// ============================================================================
// CALLBACK
// ============================================================================

void onBeatDetected()
{
  Serial.println("Beat!");
}

// ============================================================================
// FIREBASE URL
// ============================================================================

String fbUrl(const char *path)
{
  return String("https://") +
         DATABASE_URL +
         path +
         ".json?auth=" +
         DATABASE_SECRET;
}

// ============================================================================
// FIREBASE GET
// ============================================================================

String fbGet(const char *path)
{
  if (!wifiConnected)
    return "";

  String payload;
  payload.reserve(1024);

  if (xSemaphoreTake(xFirebaseMutex, pdMS_TO_TICKS(10000)))
  {
    HTTPClient http;

    http.begin(secureClient, fbUrl(path));
    http.setTimeout(10000);

    int code = http.GET();

    if (code == 200)
    {
      payload = http.getString();
    }

    Serial.printf("[GET] %s -> %d\n", path, code);

    http.end();

    xSemaphoreGive(xFirebaseMutex);
  }

  return payload;
}

// ============================================================================
// FIREBASE PUT
// ============================================================================

void fbPut(const char *path, const String &body)
{
  if (!wifiConnected)
    return;

  if (xSemaphoreTake(xFirebaseMutex, pdMS_TO_TICKS(10000)))
  {
    HTTPClient http;

    http.begin(secureClient, fbUrl(path));
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(10000);

    int code = http.PUT(body);

    Serial.printf("[PUT] %s -> %d\n", path, code);

    http.end();

    xSemaphoreGive(xFirebaseMutex);
  }
}

// ============================================================================
// FIREBASE POST
// ============================================================================

void fbPost(const char *path, const String &body)
{
  if (!wifiConnected)
    return;

  if (xSemaphoreTake(xFirebaseMutex, pdMS_TO_TICKS(10000)))
  {
    HTTPClient http;

    http.begin(secureClient, fbUrl(path));
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(10000);

    int code = http.POST(body);

    Serial.printf("[POST] %s -> %d\n", path, code);

    http.end();

    xSemaphoreGive(xFirebaseMutex);
  }
}

// ============================================================================
// FETCH CONFIG
// ============================================================================

void fetchConfig()
{
  String payload = fbGet("/sachita/config");

  if (payload == "" || payload == "null")
  {
    StaticJsonDocument<256> doc;

    doc["roll_threshold"]  = g_rollThreshold;
    doc["pitch_threshold"] = g_pitchThreshold;
    doc["fall_delay_ms"]   = g_fallDelayMs;

    String body;
    serializeJson(doc, body);

    fbPut("/sachita/config", body);

    return;
  }

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, payload);

  if (err)
  {
    Serial.println("[CONFIG] JSON Error");
    return;
  }

  if (doc.containsKey("roll_threshold"))
    g_rollThreshold = doc["roll_threshold"];

  if (doc.containsKey("pitch_threshold"))
    g_pitchThreshold = doc["pitch_threshold"];

  if (doc.containsKey("fall_delay_ms"))
    g_fallDelayMs = doc["fall_delay_ms"];

  Serial.println("[CONFIG] Updated");
}

// ============================================================================
// DETECT FALL
//
// FIX #3: Tulis fallDirection via strncpy (aman untuk char[]),
// bungkus dengan xDataMutex agar taskFirebase tidak membaca setengah-tulis.
// ============================================================================

void detectFall()
{
  bool abnormalPosition = false;
  char direction[16]    = "NORMAL";

  float r = roll;
  float p = pitch;

  if (p > g_pitchThreshold)
  {
    abnormalPosition = true;
    strncpy(direction, "DEPAN", sizeof(direction) - 1);
  }
  else if (p < -g_pitchThreshold)
  {
    abnormalPosition = true;
    strncpy(direction, "BELAKANG", sizeof(direction) - 1);
  }
  else if (r > g_rollThreshold)
  {
    abnormalPosition = true;
    strncpy(direction, "KANAN", sizeof(direction) - 1);
  }
  else if (r < -g_rollThreshold)
  {
    abnormalPosition = true;
    strncpy(direction, "KIRI", sizeof(direction) - 1);
  }

  if (abnormalPosition)
  {
    // Update fallDirection dengan mutex
    if (xSemaphoreTake(xDataMutex, pdMS_TO_TICKS(100)))
    {
      strncpy(fallDirection, direction, sizeof(fallDirection) - 1);
      fallDirection[sizeof(fallDirection) - 1] = '\0';
      xSemaphoreGive(xDataMutex);
    }

    if (!positionTriggered)
    {
      positionTriggered = true;
      positionStartMs   = millis();
      Serial.println("[FALL] Triggered");
    }

    if (millis() - positionStartMs >= g_fallDelayMs)
    {
      if (!fallDetected)
      {
        fallDetected = true;
        Serial.println(String("[FALL DETECTED] ") + direction);
      }
    }
  }
  else
  {
    positionTriggered = false;
    fallDetected      = false;

    if (xSemaphoreTake(xDataMutex, pdMS_TO_TICKS(100)))
    {
      strncpy(fallDirection, "NORMAL", sizeof(fallDirection) - 1);
      xSemaphoreGive(xDataMutex);
    }
  }
}

// ============================================================================
// SEND REALTIME
//
// FIX #3: Baca fallDirection dengan salinan lokal (localDir) di dalam mutex
// agar tidak terjadi partial-read saat taskSensor sedang menulis.
// ============================================================================

void sendSensorData()
{
  // Ambil salinan fallDirection secara aman
  char localDir[16] = "NORMAL";

  if (xSemaphoreTake(xDataMutex, pdMS_TO_TICKS(100)))
  {
    strncpy(localDir, fallDirection, sizeof(localDir) - 1);
    localDir[sizeof(localDir) - 1] = '\0';
    xSemaphoreGive(xDataMutex);
  }

  StaticJsonDocument<768> doc;

  doc["heartRate"]       = heartRate;
  doc["spo2"]            = spo2;
  doc["temperature"]     = temperature;
  doc["accel_x"]         = accX;
  doc["accel_y"]         = accY;
  doc["accel_z"]         = accZ;
  doc["gyro_x"]          = gyrX;
  doc["gyro_y"]          = gyrY;
  doc["gyro_z"]          = gyrZ;
  doc["roll"]            = roll;
  doc["pitch"]           = pitch;
  doc["fall_detected"]   = (bool)fallDetected;
  doc["fall_direction"]  = localDir;
  doc["roll_threshold"]  = g_rollThreshold;
  doc["pitch_threshold"] = g_pitchThreshold;
  doc["fall_delay_ms"]   = g_fallDelayMs;
  doc["wifi_rssi"]       = WiFi.RSSI();
  doc["uptime"]          = millis();

  String body;
  serializeJson(doc, body);

  fbPut("/sachita/realtime", body);
}

// ============================================================================
// SEND ALERT
// ============================================================================

void sendFallAlert()
{
  char localDir[16] = "NORMAL";

  if (xSemaphoreTake(xDataMutex, pdMS_TO_TICKS(100)))
  {
    strncpy(localDir, fallDirection, sizeof(localDir) - 1);
    localDir[sizeof(localDir) - 1] = '\0';
    xSemaphoreGive(xDataMutex);
  }

  StaticJsonDocument<512> doc;

  doc["status"]      = "FALL_DETECTED";
  doc["direction"]   = localDir;
  doc["heartRate"]   = heartRate;
  doc["spo2"]        = spo2;
  doc["temperature"] = temperature;
  doc["roll"]        = roll;
  doc["pitch"]       = pitch;
  doc["timestamp"]   = millis();

  String body;
  serializeJson(doc, body);

  fbPost("/sachita/alerts", body);
}

// ============================================================================
// TASK MAX30100
//
// FIX #1: Tetap di Core 0. Namun sekarang menggunakan xI2CMutex (shared
// dengan MPU6050) karena mereka berada di BUS FISIK YANG SAMA.
//
// Agar pox.update() tidak terlalu jarang (yang menyebabkan HR = 0),
// mutex di-take hanya selama pox.update() (sangat singkat ~1–2ms),
// lalu segera dilepas. taskSensor hanya aktif setiap 1000ms sehingga
// window untuk MAX30100 sangat besar.
// ============================================================================

void taskMAX30100(void *pvParameters)
{
  while (true)
  {
    // Take mutex hanya selama operasi I2C berlangsung
    if (xSemaphoreTake(xI2CMutex, pdMS_TO_TICKS(50)))
    {
      pox.update();

      float hr = pox.getHeartRate();
      int   sp = pox.getSpO2();

      xSemaphoreGive(xI2CMutex);

      // Tulis nilai global di luar mutex
      heartRate = hr;
      spo2      = sp;
    }

    // ~1ms yield, cukup agar pox.update() terpanggil sangat sering
    vTaskDelay(1);
  }
}

// ============================================================================
// TASK SENSOR (MPU6050 + DS18B20)
//
// FIX #1: Sekarang menggunakan xI2CMutex yang sama dengan taskMAX30100.
// DS18B20 (OneWire) tidak butuh mutex karena bukan I2C.
// ============================================================================

void taskSensor(void *pvParameters)
{
  while (true)
  {
    // ── MPU6050 (I2C, butuh mutex) ────────────────────────────
    if (xSemaphoreTake(xI2CMutex, pdMS_TO_TICKS(1000)))
    {
      sensors_event_t a, g, temp;
      mpu.getEvent(&a, &g, &temp);

      accX = a.acceleration.x;
      accY = a.acceleration.y;
      accZ = a.acceleration.z;

      gyrX = g.gyro.x;
      gyrY = g.gyro.y;
      gyrZ = g.gyro.z;

      roll  = atan2(accY, accZ) * 180.0 / PI;
      pitch = atan2(-accX, sqrt(accY * accY + accZ * accZ))
              * 180.0 / PI;

      xSemaphoreGive(xI2CMutex);
    }

    // ── Deteksi jatuh (tidak butuh mutex I2C) ────────────────
    detectFall();

    // ── DS18B20 (OneWire, bukan I2C) ─────────────────────────
    tempSensor.requestTemperatures();

    float t = tempSensor.getTempCByIndex(0);

    if (t != DEVICE_DISCONNECTED_C)
    {
      temperature = t;
    }

    // ── Log Serial ────────────────────────────────────────────
    char localDir[16] = "NORMAL";
    if (xSemaphoreTake(xDataMutex, pdMS_TO_TICKS(50)))
    {
      strncpy(localDir, fallDirection, sizeof(localDir) - 1);
      xSemaphoreGive(xDataMutex);
    }

    Serial.printf(
      "HR: %.1f | SpO2: %d | Temp: %.1f | "
      "Roll: %.1f | Pitch: %.1f | Fall: %s\n",
      (float)heartRate,
      (int)spo2,
      (float)temperature,
      (float)roll,
      (float)pitch,
      localDir);

    vTaskDelay(1000 / portTICK_PERIOD_MS);
  }
}

// ============================================================================
// TASK FIREBASE
// ============================================================================

void taskFirebase(void *pvParameters)
{
  while (true)
  {
    if (wifiConnected)
    {
      sendSensorData();

      if (fallDetected)
      {
        sendFallAlert();
      }
    }

    vTaskDelay(FIREBASE_PERIOD_MS / portTICK_PERIOD_MS);
  }
}

// ============================================================================
// TASK CONFIG
// ============================================================================

void taskConfig(void *pvParameters)
{
  while (true)
  {
    if (wifiConnected)
    {
      fetchConfig();
    }

    vTaskDelay(CONFIG_PERIOD_MS / portTICK_PERIOD_MS);
  }
}

// ============================================================================
// TASK WIFI
// ============================================================================

void taskWiFi(void *pvParameters)
{
  while (true)
  {
    if (WiFi.status() != WL_CONNECTED)
    {
      wifiConnected = false;

      Serial.println("[WiFi] Reconnecting...");

      WiFi.disconnect();
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

      uint8_t retry = 0;

      while (WiFi.status() != WL_CONNECTED && retry < 20)
      {
        vTaskDelay(500 / portTICK_PERIOD_MS);
        retry++;
      }

      if (WiFi.status() == WL_CONNECTED)
      {
        wifiConnected = true;
        secureClient.setInsecure();
        Serial.println("[WiFi] Connected");
      }
    }

    vTaskDelay(5000 / portTICK_PERIOD_MS);
  }
}

// ============================================================================
// SETUP
// ============================================================================

void setup()
{
  Serial.begin(115200);
  delay(1000);
  Serial.println("=== SACHITA ESP32 ===");

  // ──────────────────────────────────────────────────────────
  // I2C
  // ──────────────────────────────────────────────────────────

  Wire.begin(SDA_PIN, SCL_PIN);
  Wire.setClock(400000);

  // ──────────────────────────────────────────────────────────
  // MUTEX
  // FIX: Satu mutex I2C, satu mutex Firebase, satu mutex data
  // ──────────────────────────────────────────────────────────

  xI2CMutex      = xSemaphoreCreateMutex();
  xFirebaseMutex = xSemaphoreCreateMutex();
  xDataMutex     = xSemaphoreCreateMutex();

  if (!xI2CMutex || !xFirebaseMutex || !xDataMutex)
  {
    Serial.println("[ERROR] Mutex creation FAILED!");
    while (1);
  }

  // ──────────────────────────────────────────────────────────
  // MAX30100
  // ──────────────────────────────────────────────────────────

  if (!pox.begin())
  {
    Serial.println("MAX30100 FAIL");
    // while (1);
  }
  else
  {
    pox.setIRLedCurrent(MAX30100_LED_CURR_7_6MA);
    pox.setOnBeatDetectedCallback(onBeatDetected);
    Serial.println("MAX30100 OK");
  }

  // ──────────────────────────────────────────────────────────
  // MPU6050
  // ──────────────────────────────────────────────────────────

  if (!mpu.begin())
  {
    Serial.println("MPU6050 FAIL");
  }
  else
  {
    mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
    mpu.setGyroRange(MPU6050_RANGE_500_DEG);
    mpu.setFilterBandwidth(MPU6050_BAND_5_HZ);
    Serial.println("MPU6050 OK");
  }

  // ──────────────────────────────────────────────────────────
  // DS18B20
  // ──────────────────────────────────────────────────────────

  tempSensor.begin();

  // ──────────────────────────────────────────────────────────
  // WIFI
  // ──────────────────────────────────────────────────────────

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("Connecting WiFi");

  while (WiFi.status() != WL_CONNECTED)
  {
    Serial.print(".");
    delay(500);
  }

  Serial.println();

  wifiConnected = true;
  secureClient.setInsecure();

  Serial.println(WiFi.localIP());

  fetchConfig();

  // ──────────────────────────────────────────────────────────
  // TASK
  //
  // Core 0 → taskMAX30100 (prio 3, tertinggi di core-nya)
  //           taskWiFi    (prio 1)
  //
  // Core 1 → taskSensor   (prio 2)
  //           taskFirebase (prio 1)
  //           taskConfig   (prio 1)
  //
  // taskMAX30100 di Core 0, taskSensor di Core 1.
  // Keduanya share xI2CMutex yang sama → bus I2C aman.
  // Interval mutex take di taskMAX hanya ~1-2ms (pox.update),
  // taskSensor take selama mpu.getEvent() ~5ms, lalu lepas.
  // ──────────────────────────────────────────────────────────

  xTaskCreatePinnedToCore(
    taskMAX30100,
    "MAX30100",
    4096,
    NULL,
    3,              // prioritas tertinggi di core 0
    &taskMAXHandle,
    0);             // Core 0

  xTaskCreatePinnedToCore(
    taskWiFi,
    "WiFi",
    4096,
    NULL,
    1,
    &taskWiFiHandle,
    0);             // Core 0

  xTaskCreatePinnedToCore(
    taskSensor,
    "Sensor",
    4096,
    NULL,
    2,
    &taskSensorHandle,
    1);             // Core 1

  xTaskCreatePinnedToCore(
    taskFirebase,
    "Firebase",
    12288,
    NULL,
    1,
    &taskFirebaseHandle,
    1);             // Core 1

  xTaskCreatePinnedToCore(
    taskConfig,
    "Config",
    12288,
    NULL,
    1,
    &taskConfigHandle,
    1);             // Core 1

  Serial.println("SYSTEM READY");
}

// ============================================================================
// LOOP
// ============================================================================

void loop()
{
  vTaskDelay(portMAX_DELAY);
}
