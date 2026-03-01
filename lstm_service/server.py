import os
import time
import json
import joblib
import numpy as np
import tensorflow as tf
import paho.mqtt.client as mqtt
import paho.mqtt.client as mqtt
from collections import deque
from datetime import datetime
from dotenv import load_dotenv

# Load .env from Laravel project root (one level up)
load_dotenv(os.path.join(os.path.dirname(__file__), '../.env'))

# Suppress sklearn warnings about feature names
import warnings
warnings.filterwarnings("ignore", category=UserWarning, module='sklearn')

# --- CONFIGURATION ---
MQTT_BROKER = os.getenv("MQTT_BROKER_HOST", "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud")
MQTT_PORT = int(os.getenv("MQTT_BROKER_PORT", 8883))
MQTT_USERNAME = os.getenv("MQTT_USERNAME", "harun")
MQTT_PASSWORD = os.getenv("MQTT_PASSWORD", "@&13harunA")
MQTT_TOPIC_SUB = "users/+/devices/+/sensors/#" 

# ALLOWED_USERS/DEVICES removed - using Database Logic
# ALLOWED_USERS = ['2'] 
# ALLOWED_DEVICES = ['DEV_AWOTQZEIPL']

# --- LSTM CONSTANTS ---
MODEL_PATH = "lstm_moisture_server.tflite"
SCALER_PATH = "scaler.pkl"
TIME_STEPS = 10
THRESHOLD_LOW = 30.0
# THRESHOLD_HIGH = 80.0

# --- STATE MANAGEMENT ---
# Buffers for each device: device_code -> deque
buffers = {}
# Last decision state to avoid spamming MQTT
last_decisions = {}

# --- LOAD MODEL ---
def load_model_and_scaler():
    try:
        interpreter = tf.lite.Interpreter(model_path=MODEL_PATH)
        interpreter.allocate_tensors()
        scaler = joblib.load(SCALER_PATH)
        print("✅ Model and Scaler loaded successfully.")
        return interpreter, scaler
    except Exception as e:
        print(f"❌ Failed to load model/scaler: {e}")
        exit(1)

interpreter, scaler = load_model_and_scaler()
input_details = interpreter.get_input_details()
output_details = interpreter.get_output_details()

# --- INFERENCE ---
def run_inference(device_code, moisture_val):
    global buffers
    
    # Initialize buffer for new device
    if device_code not in buffers:
        buffers[device_code] = deque(maxlen=TIME_STEPS)
    
    # Normalize
    try:
        moisture_norm = scaler.transform([[moisture_val]])[0][0]
    except Exception as e:
        print(f"Scaler error: {e}")
        return None

    # Append to buffer
    buffers[device_code].append(moisture_norm)
    
    # Need enough data
    if len(buffers[device_code]) < TIME_STEPS:
        return None
    
    # Prepare Input
    seq = np.array(buffers[device_code], dtype=np.float32).reshape(1, TIME_STEPS, 1)
    
    # Run TFLite
    try:
        interpreter.set_tensor(input_details[0]["index"], seq)
        interpreter.invoke()
        prob = interpreter.get_tensor(output_details[0]["index"])[0][0]
        decision = int(prob >= 0.5)
        # print(f"🧠 LSTM Decision: {decision} (Prob: {prob:.4f})")
    except Exception as e:
        # Fallback for Python 3.13 / Missing Flex Delegate
        print(f"⚠️ Model Error: {e}")
        print("⚠️ Switching to Heuristic Fallback (Logic from Snippet)")
        
        # Logic from provided code: Active if moisture is between 30 and 80
        # Arduino: shouldOpen = (soilMoisture > 30.0 && soilMoisture < 80.0);
        decision = 1 if (30.0 < moisture_val < 80.0) else 0
        
    return decision

import psycopg2

# --- DB CONFIGURATION ---
DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_DATABASE", "smarthomeclone")
DB_USER = os.getenv("DB_USERNAME", "postgres")
DB_PASSWORD = os.getenv("DB_PASSWORD", "")

# --- DATABASE HANDLER ---
def save_to_db(device_code, widget_key, value, event_type='telemetry'):
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD
        )
        cur = conn.cursor()
        
        # 1. Get Device ID
        cur.execute("SELECT id FROM devices WHERE device_code = %s", (device_code,))
        res = cur.fetchone()
        
        if res:
            device_id = res[0]
            # 2. Insert Log
            cur.execute("""
                INSERT INTO device_logs (device_id, widget_key, new_value, event_type, source, created_at, updated_at)
                VALUES (%s, %s, %s, %s, 'AI Service', NOW(), NOW())
            """, (device_id, widget_key, str(value), event_type))
            
            conn.commit()
            print(f"💾 Saved to DB: {widget_key}={value}")
        else:
            print(f"⚠️ Device not found in DB: {device_code}")
            
        cur.close()
        conn.close()
    except Exception as e:
        print(f"❌ DB Error: {e}")

# --- HELPER: CHECK DB PERMISSION ---
def check_device_lstm_enabled(device_code):
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD
        )
        cur = conn.cursor()
        
        # Check if device exists and has lstm_enabled = true
        cur.execute("SELECT lstm_enabled FROM devices WHERE device_code = %s", (device_code,))
        res = cur.fetchone()
        
        cur.close()
        conn.close()
        
        if res and res[0]: # res[0] is boolean or 1/0
            return True
        return False
    except Exception as e:
        print(f"❌ DB Check Error: {e}")
        return False

# --- MQTT HANDLERS ---
def on_connect(client, userdata, flags, rc):
    print(f"📡 Connected to MQTT Broker (RC: {rc})")
    client.subscribe(MQTT_TOPIC_SUB)
    print(f"👂 Subscribed to: {MQTT_TOPIC_SUB}")

def on_message(client, userdata, msg):
    try:
        # Topic Structure: users/{userId}/devices/{deviceCode}/sensors/{sensorName}
        topic_parts = msg.topic.split('/')
        if len(topic_parts) < 5:
            return

        user_id = topic_parts[1]
        device_code = topic_parts[3]
        topic_type = topic_parts[4] # sensors or control
        sensor_key = topic_parts[5] # specific widget key

        print(f"DEBUG MQTT: {msg.topic} | {msg.payload.decode()}")
        
        payload = msg.payload.decode()
        
        # --- DYNAMIC PERMISSION CHECK ---
        # Instead of hardcoded list, check DB if this device is 'smart' (lstm_enabled)
        # We cache this? For now, real-time check is safer but slower. 
        # Ideally, cache this in a dictionary with TTL.
        
        is_lstm_active = check_device_lstm_enabled(device_code)

        if is_lstm_active:
             # Determine event type
             event_type = 'control' if topic_type == 'control' else 'telemetry'
             
             # Save ALL data received for this device
             save_to_db(device_code, sensor_key, payload, event_type)
        else:
            # If not active, do nothing. Laravel MqttListener handles it.
            return
        
        # --- LSTM INFERENCE LOGIC (Only for soil measure) ---
        if sensor_key not in ['gauge1', 'moisture']:
            return
            
        try:
            moisture_val = float(payload)
        except ValueError:
            return
            
        print(f"📥 Data: Device={device_code}, Moist={moisture_val}%")

        # Reuse the check result
        if not is_lstm_active:
            return

        # 2. Run Inference
        decision = run_inference(device_code, moisture_val)
        
        if decision is not None:
             actuator_key = 'toggle1'
             command_val = '1' if decision == 1 else '0'
             
             control_topic = f"users/{user_id}/devices/{device_code}/control/{actuator_key}"
             
             # Publish Control
             client.publish(control_topic, command_val, qos=1)
             print(f"🚀 Action: {command_val} -> {control_topic}")
             
             # Also log the decision (control event) into DB!
             save_to_db(device_code, actuator_key, command_val, 'control')
             
             last_decisions[device_code] = command_val

    except Exception as e:
        print(f"❌ Processing Error: {e}")

# --- MAIN ---
if __name__ == "__main__":
    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message

    print(f"🔌 Connecting to {MQTT_BROKER}...")
    
    # Enable TLS
    client.tls_set()
    
    # Set Credentials
    client.username_pw_set(MQTT_USERNAME, MQTT_PASSWORD)

    try:
        client.connect(MQTT_BROKER, MQTT_PORT, 60)
        client.loop_forever()
    except KeyboardInterrupt:
        print("\n🛑 Service Stopped.")
    except Exception as e:
        print(f"\n❌ Connection Failed: {e}")
