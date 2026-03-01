#!/usr/bin/env python3
"""
IoT Device Simulator - Python
Simulates ESP32 device with MQTT communication
Compatible with Laravel backend API
"""

import time
import json
import random
import ssl
import requests
from datetime import datetime
import paho.mqtt.client as mqtt
from threading import Thread, Event

# ===== Configuration =====
DEVICE_CODE = "DEV_1IGXEOR0PQ"
API_BASE_URL = "http://10.199.9.103:8000/api/devices"

# ===== Global Variables =====
mqtt_config = {}
widgets = []
user_id = None
mqtt_client = None
connected = Event()
running = True

# ===== Pin Mapping (Simulation) =====
pin_mappings = {
    "lampu": {"pin": 2, "mode": "OUTPUT", "state": False},
    "lamp teras": {"pin": 4, "mode": "OUTPUT", "state": False},
    "lamp rumah": {"pin": 5, "mode": "OUTPUT", "state": False},
    "lampu 2": {"pin": 18, "mode": "OUTPUT", "state": False},
    "level sumur": {"pin": 34, "mode": "INPUT", "value": 0},
    "kipas": {"pin": 19, "mode": "OUTPUT", "state": False},
    "terang": {"pin": 21, "mode": "OUTPUT", "state": False},
}


def log(emoji, message, details=None):
    """Pretty logging with emojis"""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {emoji} {message}")
    if details:
        for key, value in details.items():
            print(f"         └─ {key}: {value}")


def authenticate_device():
    """Authenticate device and get MQTT credentials"""
    global mqtt_config, user_id

    log("🔐", "Authenticating device...", {
        "URL": f"{API_BASE_URL}/auth",
        "Device Code": DEVICE_CODE
    })

    try:
        response = requests.post(
            f"{API_BASE_URL}/auth",
            json={"device_code": DEVICE_CODE},
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            timeout=10
        )

        if response.status_code == 200:
            data = response.json()

            if data.get("success"):
                mqtt_config = data["mqtt"]
                user_id = data["device"]["user_id"]

                log("✅", "Authentication successful!", {
                    "Device": data["device"]["name"],
                    "User ID": user_id,
                    "User": data["device"]["user_name"],
                    "MQTT Host": mqtt_config["host"]
                })
                return True
            else:
                log("❌", "API returned success=false", {"Response": data})
        else:
            log("❌", f"HTTP Error: {response.status_code}", {
                "Response": response.text
            })

    except Exception as e:
        log("❌", f"Authentication error: {str(e)}")

    return False


def fetch_widgets():
    """Fetch widgets configuration from API"""
    global widgets

    log("📡", "Fetching widgets...")

    try:
        response = requests.get(
            f"{API_BASE_URL}/{DEVICE_CODE}/widgets",
            headers={"Accept": "application/json"},
            timeout=10
        )

        if response.status_code == 200:
            data = response.json()

            if data.get("success"):
                widgets_data = data.get("widgets", {})

                # Handle both object and array formats
                if isinstance(widgets_data, dict):
                    widgets = []
                    for key, widget in widgets_data.items():
                        widgets.append({
                            "key": key,
                            "name": widget.get("name", ""),
                            "type": widget.get("type", "text"),
                            "value": widget.get("value", ""),
                            "min": widget.get("min", 0),
                            "max": widget.get("max", 100),
                            "pin": -1,
                            "auto_setup": False
                        })
                elif isinstance(widgets_data, list):
                    widgets = widgets_data

                log("✅", f"Fetched {len(widgets)} widgets")
                auto_setup_widgets()
                print_widget_configuration()
                return True
            else:
                log("❌", "API returned success=false")
        else:
            log("❌", f"HTTP Error: {response.status_code}")

    except Exception as e:
        log("❌", f"Fetch widgets error: {str(e)}")

    return False


def auto_setup_widgets():
    """Auto-map widgets to pins based on name"""
    log("🔧", "AUTO-SETUP WIDGETS")
    print("=" * 60)

    setup_count = 0

    for widget in widgets:
        name_lower = widget["name"].lower()

        # Find matching pin mapping
        for mapping_name, mapping in pin_mappings.items():
            if mapping_name.lower() in name_lower or name_lower in mapping_name.lower():
                widget["pin"] = mapping["pin"]
                widget["auto_setup"] = True
                widget["mode"] = mapping["mode"]

                log("✅", f"Widget '{widget['name']}' → GPIO {widget['pin']} ({mapping['mode']})")
                setup_count += 1
                break

        if not widget.get("auto_setup"):
            log("⚠️", f"Widget '{widget['name']}' → NOT MAPPED")

    print("=" * 60)
    log("📊", f"Setup: {setup_count}/{len(widgets)} widgets\n")


def print_widget_configuration():
    """Print widgets in a formatted table"""
    print("\n📋 WIDGET CONFIGURATION")
    print("=" * 80)
    print(f"{'Key':<15} | {'Type':<10} | {'Name':<20} | {'GPIO':<6} | {'Status':<8}")
    print("-" * 80)

    for widget in widgets:
        pin = widget.get("pin", -1)
        pin_str = str(pin) if pin >= 0 else "N/A"
        status = "✓" if widget.get("auto_setup") else "✗"

        print(f"{widget['key']:<15} | {widget['type']:<10} | {widget['name']:<20} | {pin_str:<6} | {status:<8}")

    print("=" * 80 + "\n")


def on_mqtt_connect(client, userdata, flags, rc):
    """MQTT connection callback"""
    if rc == 0:
        log("✅", "Connected to MQTT broker!")

        # Subscribe to control topics
        control_topic = f"users/{user_id}/devices/{DEVICE_CODE}/control/#"
        client.subscribe(control_topic)
        log("📥", f"Subscribed to: {control_topic}")

        connected.set()
    else:
        log("❌", f"MQTT connection failed with code: {rc}")


def on_mqtt_message(client, userdata, msg):
    """MQTT message callback"""
    topic = msg.topic
    message = msg.payload.decode('utf-8')

    log("📩", "MQTT Message received", {
        "Topic": topic,
        "Value": message
    })

    # Parse topic to get widget key
    # Format: users/{userId}/devices/{deviceCode}/control/{widgetKey}
    parts = topic.split('/')
    if len(parts) >= 6:
        widget_key = parts[5]

        # Update widget value
        for widget in widgets:
            if widget["key"] == widget_key:
                old_value = widget["value"]
                widget["value"] = message

                log("✅", f"Updated widget: {widget['name']}", {
                    "Key": widget_key,
                    "Old": old_value,
                    "New": message
                })

                # Simulate pin action
                simulate_pin_action(widget)
                break


def on_mqtt_disconnect(client, userdata, rc):
    """MQTT disconnect callback"""
    log("⚠️", "Disconnected from MQTT broker")
    connected.clear()


def connect_mqtt():
    """Connect to MQTT broker"""
    global mqtt_client

    if not mqtt_config:
        log("⚠️", "MQTT credentials not loaded yet")
        return False

    log("📡", "Connecting to MQTT...")

    try:
        client_id = f"Python-Simulator-{DEVICE_CODE}-{int(time.time())}"

        mqtt_client = mqtt.Client(client_id)
        mqtt_client.username_pw_set(
            mqtt_config["username"],
            mqtt_config["password"]
        )

        # Setup TLS if enabled
        if mqtt_config.get("use_tls", True):
            mqtt_client.tls_set(cert_reqs=ssl.CERT_NONE)
            mqtt_client.tls_insecure_set(True)

        mqtt_client.on_connect = on_mqtt_connect
        mqtt_client.on_message = on_mqtt_message
        mqtt_client.on_disconnect = on_mqtt_disconnect

        mqtt_client.connect(
            mqtt_config["host"],
            mqtt_config["port"],
            keepalive=60
        )

        mqtt_client.loop_start()

        # Wait for connection
        if connected.wait(timeout=10):
            return True
        else:
            log("❌", "MQTT connection timeout")
            return False

    except Exception as e:
        log("❌", f"MQTT connection error: {str(e)}")
        return False


def simulate_pin_action(widget):
    """Simulate pin action based on widget type"""
    pin = widget.get("pin", -1)
    if pin < 0:
        return

    widget_type = widget["type"]
    value = widget["value"]

    # Find pin mapping
    for mapping_name, mapping in pin_mappings.items():
        if mapping["pin"] == pin:
            if widget_type == "toggle":
                state = value in ["1", "true", "on", "ON", "True"]
                mapping["state"] = state
                log("💡", f"GPIO {pin} ({mapping_name}): {'HIGH' if state else 'LOW'}")

            elif widget_type == "slider":
                try:
                    pwm_value = int(value)
                    min_val = widget.get("min", 0)
                    max_val = widget.get("max", 100)
                    pwm_value = max(min_val, min(max_val, pwm_value))

                    # Map to 0-255 for PWM simulation
                    pwm_mapped = int((pwm_value - min_val) / (max_val - min_val) * 255)
                    log("🎚️", f"GPIO {pin} ({mapping_name}): PWM={pwm_mapped}/255 ({pwm_value}%)")
                except ValueError:
                    pass

            break


def read_and_publish_sensors():
    """Read sensors and publish to MQTT"""
    if not mqtt_client or not connected.is_set():
        return

    log("📊", "Reading & Publishing Sensors...")

    for widget in widgets:
        if widget.get("pin", -1) < 0 or not widget.get("auto_setup"):
            continue

        if widget["type"] not in ["gauge", "sensor", "chart"]:
            continue

        # Simulate sensor reading
        sensor_value = simulate_sensor_reading(widget)

        # Publish to MQTT using widget key
        topic = f"users/{user_id}/devices/{DEVICE_CODE}/sensors/{widget['key']}"

        try:
            result = mqtt_client.publish(topic, sensor_value, qos=1)

            if result.rc == mqtt.MQTT_ERR_SUCCESS:
                log("✅", f"Published: {topic} = {sensor_value}", {
                    "Widget": widget['name'],
                    "Key": widget['key']
                })
            else:
                log("❌", f"Failed to publish: {topic}")

        except Exception as e:
            log("❌", f"Publish error: {str(e)}")

        time.sleep(0.1)


def simulate_sensor_reading(widget):
    """Simulate sensor readings"""
    name_lower = widget["name"].lower()

    # Water level sensor
    if "level" in name_lower or "sumur" in name_lower:
        # Simulate water level 0-100%
        base_value = pin_mappings.get("level sumur", {}).get("value", 50)
        # Add small random variation
        value = base_value + random.uniform(-5, 5)
        value = max(0, min(100, value))
        pin_mappings["level sumur"]["value"] = value
        return f"{value:.1f}"

    # Temperature sensor
    elif "temp" in name_lower or "suhu" in name_lower:
        # Simulate temperature 20-35°C
        temp = 25 + random.uniform(-5, 10)
        return f"{temp:.1f}"

    # Humidity sensor
    elif "humid" in name_lower or "kelembab" in name_lower:
        # Simulate humidity 40-80%
        humidity = 60 + random.uniform(-20, 20)
        return f"{humidity:.1f}"

    # Generic sensor
    else:
        # Random value between min and max
        min_val = widget.get("min", 0)
        max_val = widget.get("max", 100)
        value = random.uniform(min_val, max_val)
        return f"{value:.1f}"


def send_heartbeat():
    """Send heartbeat to server"""
    try:
        response = requests.post(
            f"{API_BASE_URL}/{DEVICE_CODE}/heartbeat",
            json={
                "status": "online",
                "uptime": int(time.time() - start_time),
                "free_heap": random.randint(100000, 200000),
                "rssi": random.randint(-80, -40)
            },
            headers={"Content-Type": "application/json"},
            timeout=5
        )

        if response.status_code == 200:
            log("💓", "Heartbeat sent")
        else:
            log("⚠️", f"Heartbeat failed: {response.status_code}")

    except Exception as e:
        log("❌", f"Heartbeat error: {str(e)}")


def sensor_publisher_thread():
    """Thread for publishing sensor data periodically"""
    while running:
        time.sleep(10)  # Every 10 seconds
        if connected.is_set():
            read_and_publish_sensors()


def heartbeat_thread():
    """Thread for sending heartbeat periodically"""
    while running:
        time.sleep(60)  # Every 60 seconds
        send_heartbeat()


def main():
    """Main program"""
    global start_time, running

    print("\n" + "=" * 60)
    print("║   Python IoT Device Simulator                         ║")
    print("║   Widget Key-Based MQTT Communication                 ║")
    print("=" * 60 + "\n")

    start_time = time.time()

    # Step 1: Authenticate
    if not authenticate_device():
        log("❌", "Authentication failed! Exiting...")
        return

    # Step 2: Fetch widgets
    if not fetch_widgets():
        log("⚠️", "Failed to fetch widgets, continuing anyway...")

    # Step 3: Connect to MQTT
    if not connect_mqtt():
        log("❌", "MQTT connection failed! Exiting...")
        return

    # Start background threads
    Thread(target=sensor_publisher_thread, daemon=True).start()
    Thread(target=heartbeat_thread, daemon=True).start()

    log("🚀", "Simulator running! Press Ctrl+C to stop\n")

    try:
        # Keep main thread alive
        while True:
            time.sleep(1)

    except KeyboardInterrupt:
        log("⏹️", "Stopping simulator...")
        running = False

        if mqtt_client:
            mqtt_client.loop_stop()
            mqtt_client.disconnect()

        log("👋", "Simulator stopped")


if __name__ == "__main__":
    main()
