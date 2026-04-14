// ============================================================
// Arduino Nano — I2C Slave FINAL (OFFSET MODE - COMPAT ESP)
// Real Sensor: DS18B20 + PZEM + Voltage Divider
// ============================================================

#include <Wire.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <PZEM004Tv30.h>
#include <SoftwareSerial.h>

#define I2C_ADDR 0x09

// =========================
// DS18B20
// =========================
#define ONE_WIRE_BUS 4
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature ds(&oneWire);

// =========================
// PZEM
// =========================
SoftwareSerial pzemSerial(2, 3);
PZEM004Tv30 pzem(pzemSerial);

// =========================
// Voltage Divider
// =========================
const float REF = 5.0;
const float R1 = 1000000.0;
const float R2 = 100000.0;
const float RATIO = R2 / (R1 + R2);

// =========================
// BUFFER
// =========================
char data48[49];
char data48_send[49];
volatile uint8_t offset = 0;

// =========================
// UTIL
// =========================
void fix(char *s, int len) {
  s[len] = '\0';
  for (int i = 0; i < len; i++) {
    if (s[i] == ' ') s[i] = '0';
  }
}

// =========================
// I2C RECEIVE (OFFSET)
// =========================
void receiveEvent(int howMany) {
  if (Wire.available()) {
    offset = Wire.read();
if (offset > 47) offset = 0;
  }
}

// =========================
// I2C REQUEST (CHUNK)
// =========================
void requestEvent() {
  if (offset >= 48) {
    Wire.write((uint8_t*)data48_send, 32);
    return;
  }

  uint8_t len = min(32, 48 - offset);
  Wire.write((uint8_t *)(data48_send + offset), len);

  if (offset + len >= 48) {
    offset = 0;
  }
}

// =========================
// READ SENSOR
// =========================
void baca() {

  // ===== BATTERY (A0, A1) =====
  float v1 = analogRead(A0) * REF / 1024.0;
  float v2 = analogRead(A1) * REF / 1024.0;

  // float b1 = v1 / RATIO;
  // float b2 = v2 / RATIO;
  float b1 = 48.11;
  float b2 = 48.22;

  // ===== DS18B20 =====
  ds.requestTemperatures();

  // float t1 = ds.getTempCByIndex(0);
  // float t2 = ds.getTempCByIndex(1);
  // float t3 = ds.getTempCByIndex(2);
  // float t4 = ds.getTempCByIndex(3);
   float t1 = 30.01;
  float t2 = 30.02;
  float t3 =30.03;
  float t4 = 30.04;

  if (t1 == DEVICE_DISCONNECTED_C) t1 = 0;
  if (t2 == DEVICE_DISCONNECTED_C) t2 = 0;
  if (t3 == DEVICE_DISCONNECTED_C) t3 = 0;
  if (t4 == DEVICE_DISCONNECTED_C) t4 = 0;

  // ===== PZEM =====
  // float v = pzem.voltage();
  // float i = pzem.current();
  // float p = pzem.power();
  float v = 220.10;
  float i  = 100.11;
  float p = 22000.00;

  if (isnan(v)) v = 0;
  if (isnan(i)) i = 0;
  if (isnan(p)) p = 0;
  // =========================
// LIMIT (WAJIB)
// =========================
if (b1 > 99.99) b1 = 99.99;
if (b2 > 99.99) b2 = 99.99;

if (t1 > 99.99) t1 = 99.99;
if (t2 > 99.99) t2 = 99.99;
if (t3 > 99.99) t3 = 99.99;
if (t4 > 99.99) t4 = 99.99;

if (v > 999.99) v = 999.99;
if (i > 99.99)  i = 99.99;
if (p > 99999)  p = 99999;

  // =========================
  // FORMAT FIXED LENGTH
  // =========================
  char sb1[7], sb2[7];
  char st1[6], st2[6], st3[6], st4[6];
  char sv[7], si[6], sp[6];

  dtostrf(b1, 6, 2, sb1); fix(sb1, 6);
  dtostrf(b2, 6, 2, sb2); fix(sb2, 6);

  dtostrf(t1, 5, 2, st1); fix(st1, 5);
  dtostrf(t2, 5, 2, st2); fix(st2, 5);
  dtostrf(t3, 5, 2, st3); fix(st3, 5);
  dtostrf(t4, 5, 2, st4); fix(st4, 5);

  dtostrf(v, 6, 2, sv); fix(sv, 6);
  dtostrf(i, 5, 2, si); fix(si, 5);
  dtostrf(p, 5, 0, sp); fix(sp, 5);

  snprintf(data48, 49, "%s%s%s%s%s%s%s%s%s",
           sb1, sb2, st1, st2, st3, st4, sv, si, sp);

  Serial.println(data48);
  noInterrupts();
memcpy(data48_send, data48, 48);
interrupts();
}

// =========================
// SETUP
// =========================
void setup() {
  Serial.begin(115200);

  Wire.begin(I2C_ADDR);
  Wire.onReceive(receiveEvent);
  Wire.onRequest(requestEvent);

  ds.begin();

  // Optional: kurangi resolusi biar cepat
  ds.setResolution(10);

  Serial.println("Nano READY (OFFSET MODE)");
}

// =========================
// LOOP
// =========================
void loop() {
  static unsigned long t = 0;

  if (millis() - t > 1500) {
    t = millis();
    baca();
  }
}