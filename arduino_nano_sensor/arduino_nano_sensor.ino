// ============================================================
// arduino_nano_sensor.ino
// Arduino Nano — Sensor Node (I2C Slave 0x08)
// ============================================================
// Membaca:
//   - PZEM-004T (Tegangan, Arus, Daya, Energi, Frekuensi, PF)
//   - 4x DS18B20 (suhu OneWire, pin 7)
//   - 2x Voltage Divider resistor (ADC A0, A1 — tegangan baterai DC)
//
// Komunikasi: I2C Slave ke ESP8266 (max 32 byte / transaksi)
//
// Protokol Register (ESP8266 kirim 1 byte CMD, Nano balas ≤ 31 char):
//   0x01 → "V:230.1,I:1.23"    (Voltage + Current)
//   0x02 → "P:283.1,E:12.50"   (Power + Energy)
//   0x03 → "F:50.0,PF:0.99"    (Frequency + Power Factor)
//   0x10 → "B1:12.5,B2:11.8"   (Battery Voltage 1 + 2)
//   0x20 → "T1:28.5,T2:29.1"   (Temperature 1 + 2)
//   0x21 → "T3:27.8,T4:26.3"   (Temperature 3 + 4)
//
// Dependencies (Library Manager):
//   - PZEM004Tv30    by Jakub Mandula
//   - DallasTemperature by Miles Burton
//   - OneWire        by Paul Stoffregen
// ============================================================

#include <Wire.h>
#include <SoftwareSerial.h>
#include <PZEM004Tv30.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ── Pin Definitions ─────────────────────────────────────────
#define I2C_SLAVE_ADDR    0x08

// PZEM-004T: SoftwareSerial (hindari konflik dengan Serial/USB)
#define PZEM_RX_PIN       10   // Sambung ke TX PZEM
#define PZEM_TX_PIN       11   // Sambung ke RX PZEM

// DS18B20 OneWire bus
#define DS18B20_PIN       7

// Voltage divider ADC pins
#define VDIV1_PIN         A0   // Baterai 1
#define VDIV2_PIN         A1   // Baterai 2

// ── Voltage Divider Kalibrasi ───────────────────────────────
// Formula: Vbat = Vadc * (R1 + R2) / R2
// Contoh: R1=30kΩ, R2=7.5kΩ → faktor = 5.0 (bisa ukur tegangan max ~25V)
// Sesuaikan VDIV_FACTOR dengan nilai resistor fisik kamu:
#define VDIV1_FACTOR      5.0f    // (R1+R2)/R2 baterai 1
#define VDIV2_FACTOR      5.0f    // (R1+R2)/R2 baterai 2
#define ADC_REF_V         5.0f    // Referensi ADC (5V untuk Nano)
#define ADC_MAX           1023.0f

// ── Objects ─────────────────────────────────────────────────
SoftwareSerial pzemSerial(PZEM_RX_PIN, PZEM_TX_PIN);
PZEM004Tv30    pzem(pzemSerial);

OneWire        oneWire(DS18B20_PIN);
DallasTemperature ds18b20(&oneWire);

// ── Sensor Data Buffer ──────────────────────────────────────
struct SensorData {
  float voltage;      // PZEM Tegangan (V)
  float current;      // PZEM Arus (A)
  float power;        // PZEM Daya (W)
  float energy;       // PZEM Energi (kWh)
  float frequency;    // PZEM Frekuensi (Hz)
  float powerFactor;  // PZEM Power Factor
  float batt1;        // Tegangan Baterai 1 (V)
  float batt2;        // Tegangan Baterai 2 (V)
  float temp[4];      // Suhu DS18B20 [0..3] (°C)
  bool  pzemOk;       // Flag validitas PZEM
};
SensorData data;

// ── I2C State ───────────────────────────────────────────────
volatile byte currentCmd = 0x00;  // CMD terakhir dari Master
char          i2cBuf[32];          // Buffer respons

// ── Timing ──────────────────────────────────────────────────
unsigned long lastSensorRead = 0;
#define SENSOR_INTERVAL_MS  2000   // Baca semua sensor setiap 2 detik

// ── I2C Callbacks ──────────────────────────────────────────
void onReceive(int numBytes) {
  // Master mengirim 1 byte CMD
  if (Wire.available()) {
    currentCmd = Wire.read();
  }
  // Siapkan buffer sesuai CMD
  buildResponse(currentCmd);
}

void onRequest() {
  // Master minta data — kirim buffer (max 32 byte termasuk null)
  Wire.write((uint8_t*)i2cBuf, strlen(i2cBuf) + 1);
}

// ── Build Response String ───────────────────────────────────
void buildResponse(byte cmd) {
  memset(i2cBuf, 0, sizeof(i2cBuf));

  switch (cmd) {
    case 0x01:
      // Voltage + Current — contoh: "V:230.1,I:1.23" (15 char)
      if (data.pzemOk)
        snprintf(i2cBuf, 31, "V:%.1f,I:%.2f", data.voltage, data.current);
      else
        snprintf(i2cBuf, 31, "V:0.0,I:0.00");
      break;

    case 0x02:
      // Power + Energy — contoh: "P:283.1,E:12.50" (15 char)
      if (data.pzemOk)
        snprintf(i2cBuf, 31, "P:%.1f,E:%.2f", data.power, data.energy);
      else
        snprintf(i2cBuf, 31, "P:0.0,E:0.00");
      break;

    case 0x03:
      // Frequency + Power Factor — contoh: "F:50.0,PF:0.99" (14 char)
      if (data.pzemOk)
        snprintf(i2cBuf, 31, "F:%.1f,PF:%.2f", data.frequency, data.powerFactor);
      else
        snprintf(i2cBuf, 31, "F:0.0,PF:0.00");
      break;

    case 0x10:
      // Battery voltage 1 + 2 — contoh: "B1:12.5,B2:11.8" (15 char)
      snprintf(i2cBuf, 31, "B1:%.2f,B2:%.2f", data.batt1, data.batt2);
      break;

    case 0x20:
      // Temperature 1 + 2 — contoh: "T1:28.5,T2:29.1" (15 char)
      snprintf(i2cBuf, 31, "T1:%.1f,T2:%.1f", data.temp[0], data.temp[1]);
      break;

    case 0x21:
      // Temperature 3 + 4 — contoh: "T3:27.8,T4:26.3" (15 char)
      snprintf(i2cBuf, 31, "T3:%.1f,T4:%.1f", data.temp[2], data.temp[3]);
      break;

    default:
      snprintf(i2cBuf, 31, "ERR:UNK_CMD");
      break;
  }
}

// ── Read PZEM-004T ──────────────────────────────────────────
void readPZEM() {
  float v  = pzem.voltage();
  float i  = pzem.current();
  float p  = pzem.power();
  float e  = pzem.energy();
  float f  = pzem.frequency();
  float pf = pzem.pf();

  // isnan() cek jika PZEM tidak terhubung / timeout
  if (isnan(v) || isnan(i) || isnan(p)) {
    data.pzemOk = false;
    Serial.println(F("⚠ PZEM: Timeout / tidak terhubung"));
    return;
  }

  data.voltage     = v;
  data.current     = i;
  data.power       = p;
  data.energy      = isnan(e)  ? 0.0f : e;
  data.frequency   = isnan(f)  ? 0.0f : f;
  data.powerFactor = isnan(pf) ? 0.0f : pf;
  data.pzemOk      = true;

  Serial.print(F("PZEM → V="));  Serial.print(v);
  Serial.print(F("V I="));       Serial.print(i);
  Serial.print(F("A P="));       Serial.print(p);
  Serial.println(F("W"));
}

// ── Read Voltage Dividers ───────────────────────────────────
void readVoltDividers() {
  // Multi-sample untuk stabilitas ADC
  long sum1 = 0, sum2 = 0;
  const int SAMPLES = 8;
  for (int s = 0; s < SAMPLES; s++) {
    sum1 += analogRead(VDIV1_PIN);
    sum2 += analogRead(VDIV2_PIN);
    delay(2);
  }
  float adc1 = (float)(sum1 / SAMPLES);
  float adc2 = (float)(sum2 / SAMPLES);

  // Konversi ADC → Tegangan baterai
  data.batt1 = (adc1 / ADC_MAX) * ADC_REF_V * VDIV1_FACTOR;
  data.batt2 = (adc2 / ADC_MAX) * ADC_REF_V * VDIV2_FACTOR;

  Serial.print(F("Batt → B1="));  Serial.print(data.batt1);
  Serial.print(F("V B2="));       Serial.print(data.batt2);
  Serial.println(F("V"));
}

// ── Read DS18B20 ─────────────────────────────────────────────
void readTemperatures() {
  ds18b20.requestTemperatures();
  for (int i = 0; i < 4; i++) {
    float t = ds18b20.getTempCByIndex(i);
    // DEVICE_DISCONNECTED_C = -127
    data.temp[i] = (t < -100.0f) ? 0.0f : t;
  }
  Serial.print(F("Temp → T1="));  Serial.print(data.temp[0]);
  Serial.print(F(" T2="));        Serial.print(data.temp[1]);
  Serial.print(F(" T3="));        Serial.print(data.temp[2]);
  Serial.print(F(" T4="));        Serial.println(data.temp[3]);
}

// ── Setup ────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println(F("\n=== Arduino Nano Sensor Node ==="));
  Serial.println(F("I2C Slave addr: 0x08"));

  // I2C Slave
  Wire.begin(I2C_SLAVE_ADDR);
  Wire.onReceive(onReceive);
  Wire.onRequest(onRequest);
  Serial.println(F("✅ I2C Slave init OK"));

  // PZEM-004T
  pzemSerial.begin(9600);
  Serial.println(F("✅ PZEM SoftwareSerial init OK (9600)"));

  // DS18B20
  ds18b20.begin();
  uint8_t devCount = ds18b20.getDeviceCount();
  Serial.print(F("✅ DS18B20 ditemukan: "));
  Serial.println(devCount);

  // Init data buffer
  memset(&data, 0, sizeof(data));
  memset(i2cBuf, 0, sizeof(i2cBuf));

  // Baca pertama kali
  readPZEM();
  readVoltDividers();
  readTemperatures();

  Serial.println(F("✅ Sensor Node siap!\n"));
}

// ── Loop ─────────────────────────────────────────────────────
void loop() {
  unsigned long now = millis();

  if (now - lastSensorRead >= SENSOR_INTERVAL_MS) {
    lastSensorRead = now;
    readPZEM();
    readVoltDividers();
    readTemperatures();

    // Update I2C buffer dengan CMD terakhir (agar selalu fresh)
    noInterrupts();
    buildResponse(currentCmd);
    interrupts();
  }

  // Kecil delay untuk stabilitas I2C interrupt handling
  delay(10);
}
