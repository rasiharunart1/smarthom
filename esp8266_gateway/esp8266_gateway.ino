/***************************************************
 *  NHSmart — ESP8266 I2C Slave Reader (Nano)
 *
 *  Membaca 48-byte data dari Arduino Nano via I2C
 *  kemudian mengirim ke dashboard NHSmart.
 *
 *  Format data48 dari Nano (total 48 char):
 *  [sb1:6][sb2:6][st1:5][st2:5][st3:5][st4:5][sv:6][si:5][sp:5]
 *
 *  Wiring:
 *  ESP8266 D1 (GPIO5)  → Nano A5 (SCL)
 *  ESP8266 D2 (GPIO4)  → Nano A4 (SDA)
 *  GND                 → GND (wajib common ground!)
 ***************************************************/

#include <NHSmart.h>
#include <Wire.h>

// ===== KONFIGURASI =====
#define DEVICE_CODE "DEV_OU87QYJQ0I"
#define SERVER_URL  "https://nh.mdpower.io"

char ssid[] = "MEGADATA";
char pass[] = "MEGADATA";

// ===== I2C =====
#define NANO_ADDR   0x09
#define DATA_LEN    48

// ESP8266: SDA=D2(GPIO4), SCL=D1(GPIO5) — default Wire
// Wire.begin() → GPIO4(SDA), GPIO5(SCL)

NHTimer timer;

// ===== BACA I2C DARI NANO (OFFSET MODE) =====
bool readNano(char *out) {
  memset(out, 0, DATA_LEN + 1);

  // --- Baca chunk 1: offset 0, ambil 32 byte ---
  Wire.beginTransmission(NANO_ADDR);
  Wire.write(0);  // kirim offset = 0
  if (Wire.endTransmission(true) != 0) {
    Serial.println("[I2C] ERROR: Chunk 1 send offset gagal");
    return false;
  }
  delay(5);

  uint8_t got1 = Wire.requestFrom((uint8_t)NANO_ADDR, (uint8_t)32, (uint8_t)true);
  if (got1 < 32) {
    Serial.printf("[I2C] ERROR: Chunk 1 hanya dapat %d byte\n", got1);
    return false;
  }
  for (int i = 0; i < 32; i++) out[i] = Wire.read();

  // --- Baca chunk 2: offset 32, ambil 16 byte ---
  Wire.beginTransmission(NANO_ADDR);
  Wire.write(32);  // kirim offset = 32
  if (Wire.endTransmission(true) != 0) {
    Serial.println("[I2C] ERROR: Chunk 2 send offset gagal");
    return false;
  }
  delay(5);

  uint8_t got2 = Wire.requestFrom((uint8_t)NANO_ADDR, (uint8_t)16, (uint8_t)true);
  if (got2 < 16) {
    Serial.printf("[I2C] ERROR: Chunk 2 hanya dapat %d byte\n", got2);
    return false;
  }
  for (int i = 0; i < 16; i++) out[32 + i] = Wire.read();

  out[DATA_LEN] = '\0';
  return true;
}

// ===== PARSE & KIRIM KE DASHBOARD =====
void sendSensor() {
  char raw[DATA_LEN + 1];

  if (!readNano(raw)) {
    Serial.println("[SENSOR] Gagal baca Nano, skip.");
    return;
  }

  Serial.printf("[RAW] %s\n", raw);

  /*
   * Layout 48 char:
   * [0..5]   sb1   → battery 1  (xx.xx V, 6 char)
   * [6..11]  sb2   → battery 2  (xx.xx V, 6 char)
   * [12..16] st1   → suhu 1     (xx.xx °C, 5 char)
   * [17..21] st2   → suhu 2
   * [22..26] st3   → suhu 3
   * [27..31] st4   → suhu 4
   * [32..37] sv    → tegangan   (xxx.xx V, 6 char)
   * [38..42] si    → arus       (xx.xx A, 5 char)
   * [43..47] sp    → daya       (xxxxx W, 5 char)
   */

  char tmp[8];

  // Battery 1
  strncpy(tmp, raw + 0,  6); tmp[6] = '\0';
  float b1 = atof(tmp);

  // Battery 2
  strncpy(tmp, raw + 6,  6); tmp[6] = '\0';
  float b2 = atof(tmp);

  // Suhu 1..4
  strncpy(tmp, raw + 12, 5); tmp[5] = '\0';
  float t1 = atof(tmp);

  strncpy(tmp, raw + 17, 5); tmp[5] = '\0';
  float t2 = atof(tmp);

  strncpy(tmp, raw + 22, 5); tmp[5] = '\0';
  float t3 = atof(tmp);

  strncpy(tmp, raw + 27, 5); tmp[5] = '\0';
  float t4 = atof(tmp);

  // Tegangan PZEM
  strncpy(tmp, raw + 32, 6); tmp[6] = '\0';
  float voltage = atof(tmp);

  // Arus PZEM
  strncpy(tmp, raw + 38, 5); tmp[5] = '\0';
  float current = atof(tmp);

  // Daya PZEM
  strncpy(tmp, raw + 43, 5); tmp[5] = '\0';
  float power = atof(tmp);

  // ===== KIRIM KE NHSMART =====
  NHSmart.virtualWrite("bat1",    b1);
  NHSmart.virtualWrite("bat2",    b2);
  NHSmart.virtualWrite("temp1",   t1);
  NHSmart.virtualWrite("temp2",   t2);
  NHSmart.virtualWrite("temp3",   t3);
  NHSmart.virtualWrite("temp4",   t4);
  NHSmart.virtualWrite("voltage", voltage);
  NHSmart.virtualWrite("current", current);
  NHSmart.virtualWrite("power",   power);

  Serial.printf("[SENSOR] Bat: %.2f | %.2f V\n", b1, b2);
  Serial.printf("[SENSOR] Suhu: %.2f | %.2f | %.2f | %.2f °C\n", t1, t2, t3, t4);
  Serial.printf("[SENSOR] PZEM: %.2fV | %.2fA | %.0fW\n", voltage, current, power);
}

// ===== SETUP =====
void setup() {
  Serial.begin(115200);

  // I2C sebagai master, default ESP8266: SDA=D2(4), SCL=D1(5)
  Wire.begin();
  Wire.setClock(100000);  // 100kHz, aman untuk SoftwareSerial di Nano

  NHSmart.begin(DEVICE_CODE, ssid, pass, SERVER_URL);

  // Kirim sensor tiap 2 detik
  timer.setInterval(2000, sendSensor);

  Serial.println("ESP8266 READY");
}

// ===== LOOP =====
void loop() {
  NHSmart.loop();
  timer.run();
}