#include <DHT.h>
#include <ESP32Servo.h>
#include <PubSubClient.h>
#include <WiFiClientSecure.h>

/* ================= WIFI CONFIG ================= */
const char *ssid = "SIKASIK";
const char *password = "12345678";

/* ================= MQTT CONFIG (HiveMQ Cloud) ================= */
const char *mqtt_server = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud";
const int mqtt_port = 8883; // TLS Port
const char *mqtt_user = "harun";
const char *mqtt_pass = "@&13harunA";

// IMPORTANT: Update these!
String USER_ID = "2";
String DEVICE_CODE = "DEV_AWOTQZEIPL";

/* ================= PIN CONFIG ================= */
const int soilPin = 34;  // ADC
const int servoPin = 26; // PWM
#define DHTPIN 27
#define DHTTYPE DHT22

/* ================= OBJECTS ================= */
Servo servoMotor;
DHT dht(DHTPIN, DHTTYPE);
WiFiClientSecure espClient;
PubSubClient client(espClient);

/* ================= VARIABLES ================= */
const int ADC_MAX = 4095;
unsigned long lastMsg = 0;
#define MSG_BUFFER_SIZE (50)
char msg[MSG_BUFFER_SIZE];

/* ================= SETUP ================= */
void setup_wifi() {
  delay(10);
  Serial.println();
  Serial.print("Connecting to ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("");
  Serial.println("WiFi connected");
  Serial.println("IP address: ");
  Serial.println(WiFi.localIP());
}

void callback(char *topic, byte *payload, unsigned int length) {
  Serial.print("Message arrived [");
  Serial.print(topic);
  Serial.print("] ");

  String message = "";
  for (int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  Serial.println(message);

  // Check valid topic for control
  // Topic: users/{id}/devices/{code}/control/{widget}
  String topicStr = String(topic);

  // Logic Control (toggle1 / lamp_1)
  // Adjust 'toggle1' to match your Server & Dashboard key!
  if (topicStr.endsWith("toggle1") || topicStr.endsWith("lamp_1")) {
    if ((char)payload[0] == '1' || message == "ON") {
      servoMotor.write(90); // OPEN
      Serial.println("ACTUATOR: OPEN (AI/Manual Command)");

      // ACK to dashboard (Optional but good)
      // Publish back to sensors topic so toggle widget updates
      String sensorTopic =
          "users/" + USER_ID + "/devices/" + DEVICE_CODE + "/sensors/toggle1";
      client.publish(sensorTopic.c_str(), "1");

    } else {
      servoMotor.write(0); // CLOSE
      Serial.println("ACTUATOR: CLOSE");

      String sensorTopic =
          "users/" + USER_ID + "/devices/" + DEVICE_CODE + "/sensors/toggle1";
      client.publish(sensorTopic.c_str(), "0");
    }
  }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Attempting MQTT connection...");

    // Create Random Client ID
    String clientId = "ESP32Client-";
    clientId += String(random(0xffff), HEX);

    if (client.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println("connected");

      // Subscribe to Control Topic
      String controlTopic =
          "users/" + USER_ID + "/devices/" + DEVICE_CODE + "/control/+";
      client.subscribe(controlTopic.c_str());
      Serial.println("Subscribed to: " + controlTopic);

    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());
      Serial.println(" try again in 5 seconds");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);

  // Init Pins
  pinMode(soilPin, INPUT);
  servoMotor.attach(servoPin);
  servoMotor.write(0);
  dht.begin();

  // Secure WiFi
  setup_wifi();

  // Secure MQTT (Verify Certificate or Skip)
  espClient
      .setInsecure(); // Skip certificate validation for HiveMQ Cloud (easiest)

  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);
}

void loop() {
  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  long now = millis();
  if (now - lastMsg > 5000) { // Send every 5 seconds
    lastMsg = now;

    // 1. Read Soil Moisture
    int rawValue = analogRead(soilPin);
    // Convert to % (Approximate)
    float moisture = (float)rawValue / ADC_MAX * 100.0;

    // 2. Read DHT
    float t = dht.readTemperature();
    float h = dht.readHumidity();

    if (isnan(t))
      t = 0;
    if (isnan(h))
      h = 0;

    // 3. Publish to MQTT
    // Using 'gauge1' because your simulator used it, and likely dashboard too.
    // Change 'gauge1' to 'soil_moisture' if dashboard widget key is that.
    String baseTopic =
        "users/" + USER_ID + "/devices/" + DEVICE_CODE + "/sensors/";

    client.publish((baseTopic + "gauge1").c_str(), String(moisture).c_str());
    client.publish((baseTopic + "temperature").c_str(), String(t).c_str());
    client.publish((baseTopic + "humidity").c_str(), String(h).c_str());

    Serial.print("Published: Moisture=");
    Serial.print(moisture);
    Serial.println("%");
  }
}
