// ============================================================
// Tewe Smart Home — WebSocket + REST Client
// Optimized for ESP8266 LittleFS
// ============================================================

(function () {
    'use strict';

    // ── State ─────────────────────────────────────────────────
    let ws = null;
    let widgets = [];
    let reconnectTimer = null;

    // ── DOM refs ──────────────────────────────────────────────
    const grid = document.getElementById('widgetGrid');
    const syncBadge = document.getElementById('syncBadge');
    const queueBadge = document.getElementById('queueBadge');
    const deviceCode = document.getElementById('deviceCode');
    const uptimeText = document.getElementById('uptimeText');
    const heapText = document.getElementById('heapText');
    const ipText = document.getElementById('ipText');

    // ── Icons (emoji fallback, no fonts needed) ───────────────
    const ICONS = {
        lightbulb: '💡', lamp: '💡', fan: '🌀', pump: '💧',
        lock: '🔒', door: '🚪', thermometer: '🌡️', humidity: '💧',
        'thermometer-half': '🌡️', 'info-circle': 'ℹ️',
        circle: '⚪', power: '⚡', plug: '🔌', default: '⚙️'
    };

    function getIcon(name) {
        return ICONS[name] || ICONS[(name || '').toLowerCase()] || ICONS.default;
    }

    // ── WebSocket ─────────────────────────────────────────────
    function connectWS() {
        const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
        const url = proto + '//' + location.host + '/ws';
        console.log('🔌 WS connecting:', url);

        ws = new WebSocket(url);

        ws.onopen = function () {
            console.log('✅ WS connected');
            clearTimeout(reconnectTimer);
        };

        ws.onmessage = function (e) {
            try {
                const data = JSON.parse(e.data);
                handleMessage(data);
            } catch (err) {
                console.error('Parse error:', err);
            }
        };

        ws.onclose = function () {
            console.log('🔌 WS disconnected');
            reconnectTimer = setTimeout(connectWS, 3000);
        };

        ws.onerror = function () {
            ws.close();
        };
    }

    // ── Handle incoming messages ──────────────────────────────
    function handleMessage(data) {
        if (data.event === 'init') {
            // Full state from server
            widgets = data.widgets || [];
            deviceCode.textContent = data.device || '---';
            updateFooter(data);
            updateSync(data);
            renderWidgets();
        }
        else if (data.event === 'state') {
            // Single widget update
            updateWidgetUI(data.key, data.value);
        }
        else if (data.key === '__network__') {
            // Network status change
            if (data.value === 'online') {
                syncBadge.className = 'badge badge-online';
                syncBadge.textContent = 'ONLINE';
            } else {
                syncBadge.className = 'badge badge-offline';
                syncBadge.textContent = 'OFFLINE';
            }
        }
    }

    // ── Render all widgets ────────────────────────────────────
    function renderWidgets() {
        grid.innerHTML = '';

        if (widgets.length === 0) {
            grid.innerHTML = '<div class="loading">No widgets configured</div>';
            return;
        }

        widgets.forEach(function (w) {
            const card = document.createElement('div');
            card.className = 'widget-card' + (w.type === 'toggle' && w.value === '1' ? ' on' : '');
            card.id = 'card-' + w.key;
            card.setAttribute('data-key', w.key);
            card.setAttribute('data-type', w.type);

            let inner = '';

            switch (w.type) {
                case 'toggle':
                    inner = renderToggle(w);
                    card.onclick = function () { sendToggle(w.key); };
                    break;
                case 'slider':
                    inner = renderSlider(w);
                    break;
                case 'gauge':
                    inner = renderGauge(w);
                    break;
                case 'text':
                    inner = renderText(w);
                    break;
            }

            card.innerHTML = inner;
            grid.appendChild(card);

            // Bind slider events after DOM insert
            if (w.type === 'slider') {
                const input = card.querySelector('.slider-input');
                if (input) {
                    let debounce = null;
                    input.oninput = function () {
                        const valEl = card.querySelector('.widget-value');
                        if (valEl) valEl.textContent = this.value;
                        clearTimeout(debounce);
                        debounce = setTimeout(function () {
                            sendSet(w.key, input.value);
                        }, 200);
                    };
                }
            }
        });
    }

    function renderToggle(w) {
        const on = w.value === '1';
        return '<div class="widget-icon">' + getIcon(w.icon) + '</div>' +
            '<div class="widget-name">' + esc(w.name) + '</div>' +
            '<div class="toggle-track' + (on ? ' on' : '') + '" id="track-' + w.key + '">' +
            '<div class="toggle-thumb"></div>' +
            '</div>';
    }

    function renderSlider(w) {
        return '<div class="widget-icon">' + getIcon(w.icon) + '</div>' +
            '<div class="widget-name">' + esc(w.name) + '</div>' +
            '<div class="widget-value">' + (w.value || '0') + '</div>' +
            '<input type="range" class="slider-input" ' +
            'min="' + (w.min || 0) + '" max="' + (w.max || 100) + '" ' +
            'value="' + (w.value || 0) + '">';
    }

    function renderGauge(w) {
        const unit = (w.icon === 'thermometer-half' || w.icon === 'thermometer') ? '°C' :
            (w.icon === 'humidity') ? '%' : '';
        return '<div class="widget-icon">' + getIcon(w.icon) + '</div>' +
            '<div class="widget-name">' + esc(w.name) + '</div>' +
            '<div class="gauge-value">' + (w.value || '0') +
            '<span class="gauge-unit">' + unit + '</span></div>';
    }

    function renderText(w) {
        return '<div class="widget-icon">' + getIcon(w.icon) + '</div>' +
            '<div class="widget-name">' + esc(w.name) + '</div>' +
            '<div class="text-value">' + esc(w.value || '---') + '</div>';
    }

    // ── Update single widget in-place ─────────────────────────
    function updateWidgetUI(key, value) {
        // Update internal state
        for (let i = 0; i < widgets.length; i++) {
            if (widgets[i].key === key) {
                widgets[i].value = value;
                break;
            }
        }

        const card = document.getElementById('card-' + key);
        if (!card) return;

        const type = card.getAttribute('data-type');

        if (type === 'toggle') {
            const on = value === '1';
            card.className = 'widget-card' + (on ? ' on' : '');
            const track = document.getElementById('track-' + key);
            if (track) track.className = 'toggle-track' + (on ? ' on' : '');
        }
        else if (type === 'slider') {
            const valEl = card.querySelector('.widget-value');
            const input = card.querySelector('.slider-input');
            if (valEl) valEl.textContent = value;
            if (input) input.value = value;
        }
        else if (type === 'gauge') {
            const valEl = card.querySelector('.gauge-value');
            if (valEl) {
                const unit = valEl.querySelector('.gauge-unit');
                const unitText = unit ? unit.outerHTML : '';
                valEl.innerHTML = value + unitText;
            }
        }
        else if (type === 'text') {
            const valEl = card.querySelector('.text-value');
            if (valEl) valEl.textContent = value || '---';
        }
    }

    // ── Send commands via WebSocket ───────────────────────────
    function sendToggle(key) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ action: 'toggle', key: key }));
        } else {
            // Fallback: REST API
            fetch('/api/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=' + encodeURIComponent(key)
            });
        }
    }

    function sendSet(key, value) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ action: 'set', key: key, value: String(value) }));
        } else {
            fetch('/api/set', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=' + encodeURIComponent(key) + '&value=' + encodeURIComponent(value)
            });
        }
    }

    // ── UI helpers ────────────────────────────────────────────
    function updateSync(data) {
        if (data.internet) {
            syncBadge.className = 'badge badge-online';
            syncBadge.textContent = data.mqtt ? 'SYNCED' : 'ONLINE';
        } else {
            syncBadge.className = 'badge badge-offline';
            syncBadge.textContent = 'OFFLINE';
        }

        if (data.queue > 0) {
            queueBadge.style.display = '';
            queueBadge.textContent = 'Q:' + data.queue;
        } else {
            queueBadge.style.display = 'none';
        }
    }

    function updateFooter(data) {
        if (data.uptime !== undefined) {
            const m = Math.floor(data.uptime / 60);
            const h = Math.floor(m / 60);
            uptimeText.textContent = 'Uptime: ' + (h > 0 ? h + 'h ' + m % 60 + 'm' : m + 'm ' + data.uptime % 60 + 's');
        }
        if (data.heap) heapText.textContent = 'Heap: ' + (data.heap / 1024).toFixed(1) + 'KB';
        if (data.ip) ipText.textContent = 'IP: ' + data.ip;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // ── Init ──────────────────────────────────────────────────
    connectWS();

})();
