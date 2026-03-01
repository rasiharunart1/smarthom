import tensorflow as tf
import numpy as np
import os

# Define Model Parameters
TIME_STEPS = 10
FEATURES = 1

def create_and_save_compatible_model():
    print("🧠 Creating compatible LSTM model...")
    
    # 1. Define a simple LSTM model
    # We use a simple structure that converts easily to TFLite
    model = tf.keras.Sequential([
        # Input Layer
        tf.keras.layers.InputLayer(input_shape=(TIME_STEPS, FEATURES)),
        
        # LSTM Layer
        # return_sequences=False because we only want the final decision
        tf.keras.layers.LSTM(8, return_sequences=False),
        
        # Dense Layer for binary classification (0 or 1)
        tf.keras.layers.Dense(1, activation='sigmoid')
    ])
    
    # 2. Compile (needed for saving usually, though we just export)
    model.compile(optimizer='adam', loss='binary_crossentropy')
    
    # 3. Dummy Training (Optional, to initialize weights to not be NaN)
    # We can try to set weights manually to mimic the threshold logic roughly
    # High input -> High output
    
    # 4. Convert to TFLite with standard ops only
    converter = tf.lite.TFLiteConverter.from_keras_model(model)
    
    # FORCE Standard TFLite Ops (No Flex)
    converter.target_spec.supported_ops = [
        tf.lite.OpsSet.TFLITE_BUILTINS
    ]
    
    # Optional: Quantization for compatibility
    # converter.optimizations = [tf.lite.Optimize.DEFAULT]
    
    try:
        tflite_model = converter.convert()
        
        # 5. Save Model
        output_path = "lstm_moisture_server_fixed.tflite"
        with open(output_path, "wb") as f:
            f.write(tflite_model)
            
        print(f"✅ Success! Saved compatible model to: {output_path}")
        print("ℹ️  This model uses standard operations and should run without Flex errors.")
        
    except Exception as e:
        print(f"❌ Conversion Failed: {e}")

if __name__ == "__main__":
    create_and_save_compatible_model()
