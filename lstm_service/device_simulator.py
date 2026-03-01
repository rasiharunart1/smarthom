import paho.mqtt.client as mqtt
import time
import random
import json
import argparse
import sys

# Configuration
BROKER = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud"
MQTT_PORT=8883
PORT = 1883

def on_connect(client, userdata, flags, rc):
    print(f"🔌 Connected to Broker (RC: {rc})")
    # Subscribe to control topic
    topic = f"users/{userdata['user_id']}/devices/{userdata['device_code']}/control/+"
    client.subscribe(topic)
    print(f"👂 Listening for commands on: {topic}")

def on_message(client, userdata, msg):
    print(f"\n📩 COMMAND RECEIVED: {msg.topic} -> {msg.payload.decode()}")
    
    # Simulate Device Action & Feedback
    # If we receive control/toggle1, we should publish status to sensors/toggle1
    try:
        topic_parts = msg.topic.split('/')
        if 'control' in topic_parts:
            key = topic_parts[-1] # e.g., toggle1 or lamp_1
            val = msg.payload.decode()
            
            # Publish Feedback so Dashboard updates
            feedback_topic = msg.topic.replace('control', 'sensors')
            client.publish(feedback_topic, val, qos=1)
            print(f"🔄 ACK Sent: {val} -> {feedback_topic}")
            
    except Exception as e:
        print(f"⚠️ ACK Error: {e}")

def simulate(user_id, device_code):
    client = mqtt.Client(userdata={'user_id': user_id, 'device_code': device_code})
    client.on_connect = on_connect
    client.on_message = on_message
    
    print(f"🚀 Starting Simulator for Device: {device_code} (User: {user_id})")
    
    # Enable TLS for HiveMQ Cloud
    client.tls_set()
    
    # Set Credentials
    if args.username and args.password:
        client.username_pw_set(args.username, args.password)
        print(f"🔐 Authenticating as {args.username}")
    
    try:
        client.connect(BROKER, MQTT_PORT, 60)
        client.loop_start()
        
        # Simulation Loop
        while True:
            # Simulate Soil Moisture (Random walk)
            moisture = random.uniform(20.0, 90.0)
            
            # Pub Topic
            topic = f"users/{user_id}/devices/{device_code}/sensors/gauge1"
            client.publish(topic, f"{moisture:.2f}")
            
            print(f"📤 Sent Moisture: {moisture:.2f}%", end='\r')
            
            time.sleep(5)
            
    except KeyboardInterrupt:
        print("\n🛑 Simulation Stopped")
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Device Simulator for LSTM Testing')
    parser.add_argument('--user', default='2')
    parser.add_argument('--device', default='DEV_AWOTQZEIPL')
    parser.add_argument('--username', default='harun')
    parser.add_argument('--password', default='@&13harunA')
    
    args = parser.parse_args()
    
    simulate(args.user, args.device)
