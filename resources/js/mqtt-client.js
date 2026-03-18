import mqtt from 'mqtt';

class MqttWebSocketClient {
    constructor() {
        this.client = null;
        this.connected = false;
        this.reconnecting = false;
        this.subscriptions = new Map();
        this.messageHandlers = new Map();
    }

    connect(options = {}) {
        const defaultOptions = {
            host: null,
            port: null,
            protocol: 'wss',
            username: null,
            password: null,
            clientId: 'web_client_' + Math.random().toString(16).substr(2, 8),
            clean: true,
            reconnectPeriod: 5000,
            connectTimeout: 30000,
            keepalive: 60,
        };

        const mqttConnectConfig = { ...defaultOptions, ...options };

        // Custom WebSocket URL (Supports Standard Ports 80/443 without explicit port)
        let url;
        if (mqttConnectConfig.port && mqttConnectConfig.port !== 80 && mqttConnectConfig.port !== 443) {
            url = `${mqttConnectConfig.protocol}://${mqttConnectConfig.host}:${mqttConnectConfig.port}/mqtt`;
        } else {
            url = `${mqttConnectConfig.protocol}://${mqttConnectConfig.host}/mqtt`;
        }

        console.log('🔌 Connecting to WebSocket:', url);

        try {
            this.client = mqtt.connect(url, {
                username: mqttConnectConfig.username,
                password: mqttConnectConfig.password,
                clientId: mqttConnectConfig.clientId,
                clean: mqttConnectConfig.clean,
                reconnectPeriod: mqttConnectConfig.reconnectPeriod,
                connectTimeout: mqttConnectConfig.connectTimeout,
                keepalive: mqttConnectConfig.keepalive,
            });

            this.setupEventHandlers();

            return this.client;
        } catch (error) {
            console.error('❌ MQTT Connection Error:', error);
            throw error;
        }
    }

    setupEventHandlers() {
        this.client.on('connect', () => {
            this.connected = true;
            this.reconnecting = false;
            console.log('✅ Connected to HiveMQ WebSocket MQTT');

            // Resubscribe to all topics after reconnect
            this.subscriptions.forEach((qos, topic) => {
                this.client.subscribe(topic, { qos }, (err) => {
                    if (!err) {
                        console.log('🔄 Resubscribed to:', topic);
                    }
                });
            });

            // Trigger custom connect event
            if (this.onConnect) this.onConnect();
        });

        this.client.on('reconnect', () => {
            this.reconnecting = true;
            console.log('🔄 Reconnecting to HiveMQ...');
            if (this.onReconnect) this.onReconnect();
        });

        this.client.on('close', () => {
            this.connected = false;
            console.log('🔌 Disconnected from HiveMQ');
            if (this.onDisconnect) this.onDisconnect();
        });

        this.client.on('error', (error) => {
            console.error('❌ MQTT Error:', error);
            if (this.onError) this.onError(error);
        });

        this.client.on('message', (topic, message) => {
            const payload = message.toString();
            console.log('📥 MQTT Message:', topic, '=', payload);

            // Match handlers with wildcards
            this.messageHandlers.forEach((handlers, pattern) => {
                if (this.matchTopic(pattern, topic)) {
                    handlers.forEach(handler => {
                        try {
                            handler(topic, payload);
                        } catch (error) {
                            console.error('❌ Message handler error:', error);
                        }
                    });
                }
            });

            // Trigger custom message event
            if (this.onMessage) this.onMessage(topic, payload);
        });

        this.client.on('offline', () => {
            this.connected = false;
            console.log('📴 MQTT Client is offline');
            if (this.onOffline) this.onOffline();
        });
    }

    subscribe(topic, handler, qos = 0) {
        if (!this.client) {
            console.error('❌ MQTT client not initialized');
            return false;
        }

        console.log('📡 Subscribing to:', topic);

        this.client.subscribe(topic, { qos }, (err) => {
            if (err) {
                console.error('❌ Subscribe error:', err);
                return;
            }

            console.log('✅ Subscribed to:', topic);

            // Store subscription
            this.subscriptions.set(topic, qos);

            // Store handler
            if (handler) {
                if (!this.messageHandlers.has(topic)) {
                    this.messageHandlers.set(topic, []);
                }
                this.messageHandlers.get(topic).push(handler);
            }
        });

        return true;
    }

    unsubscribe(topic, handler = null) {
        if (!this.client) return;

        // Remove specific handler or all handlers
        if (handler) {
            const handlers = this.messageHandlers.get(topic) || [];
            const index = handlers.indexOf(handler);
            if (index > -1) {
                handlers.splice(index, 1);
            }

            // If no more handlers, unsubscribe from MQTT
            if (handlers.length === 0) {
                this.client.unsubscribe(topic);
                this.subscriptions.delete(topic);
                this.messageHandlers.delete(topic);
                console.log('🔕 Unsubscribed from:', topic);
            }
        } else {
            this.client.unsubscribe(topic);
            this.subscriptions.delete(topic);
            this.messageHandlers.delete(topic);
            console.log('🔕 Unsubscribed from:', topic);
        }
    }

    publish(topic, message, qos = 0, retain = false) {
        if (!this.client || !this.connected) {
            console.error('❌ Cannot publish: MQTT not connected');
            return false;
        }

        console.log('📤 Publishing to:', topic, '=', message);

        this.client.publish(topic, message.toString(), { qos, retain }, (err) => {
            if (err) {
                console.error('❌ Publish error:', err);
            } else {
                console.log('✅ Published successfully');
            }
        });

        return true;
    }

    disconnect() {
        if (this.client) {
            console.log('👋 Disconnecting from HiveMQ...');
            this.client.end();
            this.client = null;
            this.connected = false;
            this.subscriptions.clear();
            this.messageHandlers.clear();
        }
    }

    isConnected() {
        return this.connected;
    }

    matchTopic(pattern, topic) {
        if (pattern === topic) return true;

        // Convert MQTT wildcard to Regex
        // + -> match any one level
        // # -> match everything after
        const regexPattern = pattern
            .replace(/\//g, '\\/') // escape slashes
            .replace(/\+/g, '[^\\/]+') // + -> non-slash chars
            .replace(/#/g, '.*'); // # -> everything

        const regex = new RegExp('^' + regexPattern + '$');
        return regex.test(topic);
    }

    // Event callbacks (can be overridden)
    onConnect = null;
    onReconnect = null;
    onDisconnect = null;
    onError = null;
    onMessage = null;
    onOffline = null;
}

// Export singleton instance
const mqttClient = new MqttWebSocketClient();
window.mqttClient = mqttClient;

export default mqttClient;