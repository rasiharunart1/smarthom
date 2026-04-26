# NHSmart — Blynk-Style IoT Library

> Connect ESP32/ESP8266 to [Tewe.io](https://tewe.io) with just **4 lines of code**.

```cpp
#include <NHSmart.h>

NHTimer timer;

void sendSensor() {
  NHSmart.virtualWrite("temp1", 28.5);
}

void setup() {
  NHSmart.begin("DEV_XXXXXXXXXX", "WiFi", "pass", "https://your-server.com");
  timer.setInterval(2000, sendSensor);
}

void loop() {
  NHSmart.loop();
  timer.run();
}
```

## ✨ Features

- **Blynk-style API** — `virtualWrite()`, `onWrite()`, `NHTimer`
- **Zero config auth** — Sanctum token + MQTT credentials auto-provisioned
- **Secure by default** — HTTPS + MQTT over TLS (port 8883)
- **Auto reconnect** — WiFi, API, MQTT with exponential backoff
- **LWT support** — Automatic online/offline status detection
- **Multi-platform** — ESP32 and ESP8266 from the same code
- **Debug logging** — Toggleable via `NHSMART_DEBUG`

## 📦 Installation

### Method 1: Arduino IDE (ZIP)
1. Download this folder as `.zip`
2. Arduino IDE → **Sketch → Include Library → Add .ZIP Library**
3. Select the downloaded file

### Method 2: Manual
1. Copy the `NHSmart` folder to your Arduino libraries directory:
   - Windows: `Documents/Arduino/libraries/NHSmart/`
   - macOS: `~/Documents/Arduino/libraries/NHSmart/`
   - Linux: `~/Arduino/libraries/NHSmart/`
2. Restart Arduino IDE

### Dependencies
Install via Arduino Library Manager:
- **ArduinoJson** (v6+) by Benoit Blanchon
- **PubSubClient** (v2.8+) by Nick O'Leary

## 🔌 Widget Mapping

| Widget Type | Direction | API | Example |
|-------------|-----------|-----|---------|
| **gauge** | ESP → Web | `virtualWrite("temp1", 28.5)` | Temperature, Voltage |
| **text** | ESP → Web | `virtualWrite("status", "OK")` | Status messages |
| **chart** | ESP → Web | Auto (set `source_key` in dashboard) | Historical graphs |
| **toggle** | Web → ESP | `onWrite("toggle1", callback)` | Relay, LED |
| **slider** | Web → ESP | `onWrite("slider1", callback)` | PWM, Dimmer |

## 🚀 Quick Start

### 1. Setup Dashboard
- Create a device → copy **Device Code**
- Add widgets (gauge, toggle, etc.) → note the **Widget Key**

### 2. Upload Firmware
```cpp
#include <NHSmart.h>

NHTimer timer;

// Control: Dashboard → ESP
void onRelay(String value) {
  digitalWrite(18, value.toInt());
}

// Sensor: ESP → Dashboard
void sendData() {
  NHSmart.virtualWrite("temp1", 25.5);
  NHSmart.virtualWrite("humidity", 65.0);
}

void setup() {
  Serial.begin(115200);
  pinMode(18, OUTPUT);

  NHSmart.onWrite("toggle1", onRelay);
  NHSmart.begin("DEV_XXXXXXXXXX", "WiFi", "pass", "https://server.com");
  timer.setInterval(2000, sendData);
}

void loop() {
  NHSmart.loop();
  timer.run();
}
```

## 🔐 Security

| Feature | Status |
|---------|--------|
| HTTPS Auth | ✅ Sanctum Token |
| MQTT TLS | ✅ Port 8883 |
| Per-device MQTT credentials | ✅ Auto-provisioned |
| CA Certificate verification | ✅ Optional via `setCACert()` |
| Device approval gate | ✅ Admin must approve first |

### Production TLS
```cpp
const char* ca_cert = R"EOF(
-----BEGIN CERTIFICATE-----
MIIDdzCCAl+gAwIBAgIE... (your root CA)
-----END CERTIFICATE-----
)EOF";

NHSmart.setCACert(ca_cert);   // Call BEFORE begin()
NHSmart.begin(...);
```

## 📚 API Reference

### `NHSmart.begin(deviceCode, ssid, pass, serverUrl)`
Initialize WiFi, authenticate, and connect MQTT. Call once in `setup()`.

### `NHSmart.loop()`
**Must** be called in `loop()`. Handles reconnection and MQTT keepalive.

### `NHSmart.virtualWrite(key, value)`
Send sensor data to dashboard. Supports: `int`, `float`, `double`, `const char*`, `String`.

### `NHSmart.onWrite(key, callback)`
Register callback for dashboard control commands. Max 20 handlers.

### `NHSmart.setCACert(pemCert)`
Set root CA for TLS verification. Call before `begin()`.

### `NHSmart.connected()` / `NHSmart.isAuthenticated()`
Check connection status.

### `NHSmart.rssi()` / `NHSmart.uptime()`
WiFi signal strength (dBm) / seconds since boot.

### NHTimer
```cpp
NHTimer timer;
int id = timer.setInterval(2000, myFunc);  // Repeat every 2s
int id = timer.setTimeout(5000, myFunc);   // Run once after 5s
timer.disable(id);                          // Pause
timer.enable(id);                           // Resume
timer.run();                                // Call in loop()
```

## 📁 Examples

- **Basic** — Minimal: 1 sensor + 1 relay
- **AllWidgets** — Complete: all widget types demonstrated

## License

MIT License — See [LICENSE](LICENSE)
