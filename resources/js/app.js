console.log('🚀 app.js loading...');
import './bootstrap';
import './mqtt-client';

console.log('✅ app.js with MQTT client initialized');

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
