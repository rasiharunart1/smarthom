@extends('layouts.app')

@section('title', $device->name)

@php
    // Prepare widgets data from JSON structure
    $widgetsData = $device->widget ? $device->widget->getAllWidgets() : [];
    $widgets = collect($widgetsData)->map(function ($widget, $key) {
        $w = (object) $widget;
        $w->key = $key;
        $w->position_x = $w->position_x ?? 0;
        $w->position_y = $w->position_y ?? 0;
        $w->width = $w->width ?? 4;
        $w->height = $w->height ?? 2;
        $w->value = $w->value ?? 0;
        return $w;
    })->sortBy('order');
@endphp

@section('content')
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0" style="color: var(--text-primary);">
                <i id="device-status-dot" class="fas fa-circle {{ $device->isOnline() ? 'device-online' : 'device-offline' }}" style="font-size: 0.8rem; margin-right: 10px;"></i>
                {{ $device->name }}
            </h1>
            <small style="color: var(--text-secondary);" id="last-seen" 
                   data-device-code="{{ $device->device_code }}"
                   data-last-seen="{{ $device->last_seen_at ? $device->last_seen_at->toIso8601String() : '' }}">
                Last seen: {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
            </small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm shadow-sm" data-toggle="modal" data-target="#addWidgetModal">
                <i class="fas fa-plus"></i> Add Widget
            </button>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="toggleEditMode">
                <i class="fas fa-edit"></i> Edit Layout
            </button>
            <button type="button" class="btn btn-success btn-sm shadow-sm" id="saveLayout" style="display: none;">
                <i class="fas fa-save"></i> Save Layout
            </button>
            <a href="{{ route('widgets.index') }}" class="btn btn-info btn-sm shadow-sm" style="color: white; background: rgba(59, 130, 246, 0.8);">
                <i class="fas fa-list"></i> Widget Keys
            </a>
            <a href="{{ route('device.edit') }}" class="btn btn-warning btn-sm shadow-sm">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
    </div>

    <!-- Device Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="glass-label mb-1">Device Identification</div>
                            <div class="d-flex align-items-center">
                                <code id="deviceCode" style="color: var(--primary-green-light); background: rgba(255,255,255,0.05); padding: 0.3rem 0.6rem; border-radius: 8px;">{{ $device->device_code }}</code>
                                <button class="btn btn-sm btn-link text-muted ml-2" onclick="copyDeviceCode()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="glass-label mb-1">Connection State</div>
                            <span class="badge py-2 px-3" style="background: {{ $device->isOnline() ? 'rgba(16, 185, 129, 0.15)' : 'rgba(255, 255, 255, 0.05)' }}; color: {{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }}; border: 1px solid {{ $device->isOnline() ? 'rgba(16, 185, 129, 0.3)' : 'rgba(255, 255, 255, 0.1)' }}; border-radius: 10px;">
                                {{ strtoupper($device->status) }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <div class="glass-label mb-1">Active Modules</div>
                            <div style="font-weight: 700; font-size: 1.1rem; color: white;">{{ $widgets->count() }} <small style="font-weight: 400; color: var(--text-muted);">Widgets</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Widgets Grid -->
    <div class="row">
        <div class="col-12">
            @if ($widgets->count() > 0)
                <div class="grid-stack" style="min-height: 400px;">
                    @foreach ($widgets as $widget)
                        <div class="grid-stack-item" 
                             gs-id="{{ $widget->key }}" 
                             gs-x="{{ $widget->position_x }}" 
                             gs-y="{{ $widget->position_y }}" 
                             gs-w="{{ $widget->width }}" 
                             gs-h="{{ $widget->height }}">
                            <div class="grid-stack-item-content">
                                <div class="widget-wrapper">
                                    @include('partials.widget-card', ['widget' => $widget, 'loop' => $loop])
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-5">
                    <div class="card shadow-lg d-inline-block" style="max-width: 500px; border-radius: 20px;">
                        <div class="card-body p-5">
                            <i class="fas fa-inbox fa-4x mb-3" style="color: var(--primary-green); opacity: 0.3;"></i>
                            <p style="color: var(--text-secondary);">No widgets yet. Add your first widget to get started!</p>
                            <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#addWidgetModal">
                                <i class="fas fa-plus"></i> Add Widget
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Add Widget Modal -->
    @include('partials.add-widget-modal', ['device' => $device])
@endsection

@push('styles')
    <style>
        /* Reusing styles from dashboard */
        .grid-stack { background: transparent !important; }
        .grid-stack-item-content { border-radius: 15px !important; }
        .device-online { color: #10b981 !important; text-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
        .device-offline { color: #6c757d !important; }
        .card { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important; color: #fff !important; }
        .card-body { color: #fff !important; }
        h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 { color: #fff !important; }
        p, .text-muted, small { color: #a0aec0 !important; }
        .btn-primary, .btn-success { background: linear-gradient(135deg, #10b981, #059669) !important; border: none; }
        .btn-secondary { background: rgba(255, 255, 255, 0.1) !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; color: #fff !important; }
        
        .grid-stack.edit-mode .grid-stack-item { cursor: move; }
        .grid-stack.edit-mode .grid-stack-item:hover { transform: scale(1.02); z-index: 100; }
        .grid-stack-item { transition: all 0.3s ease; }
    </style>
@endpush

@push('scripts')
<script type="module">
    // mqttClient is global from app.js

    let grid;
    let editMode = false;
    let subscribedTopics = [];
    const userId = {{ auth()->id() }};
    const deviceCode = '{{ $device->device_code }}';

    $(document).ready(function() {
        console.log('Device Detail initialized with HiveMQ');

        // Initialize GridStack
        if (typeof GridStack !== 'undefined') {
            grid = GridStack.init({
                cellHeight: 100,
                column: 12,
                margin: 20,
                float: true,
                animate: true,
                disableDrag: true,
                disableResize: true,
                handle: '.card-header-modern',
                resizable: { handles: 'se, s, e' }
            });
            
            // Re-layout on mode change
            grid.on('change', (event, items) => {
                // handled in saveLayout
            });

            // Initialize Gauges
            initializeGaugesLazy();
        }

        // Initialize MQTT
        initializeMqttWebSocket();

        // Copy Code Handler
        window.copyDeviceCode = function() {
            const code = document.getElementById('deviceCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('Device code copied!');
            });
        };
    });

    // MQTT Initialization
    function initializeMqttWebSocket() {
        if (typeof window.mqttClient === 'undefined') {
            console.warn('🕒 Waiting for window.mqttClient...');
            setTimeout(initializeMqttWebSocket, 200);
            return;
        }
        console.log(`📡 Initializing HiveMQ for device: ${deviceCode}`);

        window.mqttClient.onConnect = () => {
            console.log('✅ Connected to HiveMQ');
            updateStatusDot(true);
            
            // Subscribe to sensors
            const sensorTopic = `users/${userId}/devices/${deviceCode}/sensors/+`;
            window.mqttClient.subscribe(sensorTopic, handleSensorMessage, 1);
            
            // Subscribe to control
            const controlTopic = `users/${userId}/devices/${deviceCode}/control/+`;
            window.mqttClient.subscribe(controlTopic, handleControlMessage, 1);
        };

        window.mqttClient.onOffline = () => updateStatusDot(false);
        window.mqttClient.onDisconnect = () => updateStatusDot(false);
        
        try {
            window.mqttClient.connect();
        } catch (e) {
            console.error('MQTT Connect failed', e);
        }

        setInterval(tickTimeAgo, 1000);
    }

    // Handle sensor updates
    function handleSensorMessage(topic, message) {
        console.log('📥 MQTT message:', topic, '=', message);
        // users/{userId}/devices/{deviceCode}/sensors/{widgetPart}
        const parts = topic.split('/');
        const topicSlug = parts[parts.length - 1]; 

        let widgetKey = null;
        let type = null;

        // 1. Try case-insensitive ID match
        $('.grid-stack-item').each(function() {
            const key = $(this).attr('gs-id');
            if (key && key.toLowerCase() === topicSlug.toLowerCase()) {
                widgetKey = key;
                type = $(this).find('.widget-type-badge').text().trim().toLowerCase();
                return false;
            }
        });

        // 2. Try Name match if ID didn't work
        if (!widgetKey) {
            $('.grid-stack-item').each(function() {
                const $w = $(this);
                const name = $w.find('.widget-title').text().trim().toLowerCase();
                const nameSlug = name.replace(/\s+/g, '_');
                const cleanTopicSlug = topicSlug.toLowerCase().replace(/_/g, ' ');
                
                if (nameSlug === topicSlug.toLowerCase() || name.includes(cleanTopicSlug) || cleanTopicSlug.includes(name)) {
                    widgetKey = $w.attr('gs-id');
                    type = $w.find('.widget-type-badge').text().trim().toLowerCase();
                    return false;
                }
            });
        }

        if (widgetKey) {
            updateWidgetUI(widgetKey, message, type);
            
            // Update device metadata if it's a sensor/status
            if (topic.includes('/sensors/')) {
                $('#last-seen').attr('data-last-seen', new Date().toISOString());
                updateStatusDot(true);
            }
        } else {
            console.warn('⚠️ No widget found for topic part:', topicSlug);
        }
    }

    function handleControlMessage(topic, message) {
        // Feedback handling
    }

    // UI Updates
    function updateWidgetUI(key, value, type) {
        const $widget = $(`.grid-stack-item[gs-id="${key}"]`);
        if (!$widget.length) return;
        
        // Update generic value display
        $widget.find('.widget-value-display').text(value);

        switch (type) {
            case 'toggle':
                const isOn = value == '1' || value == 'true' || value.toLowerCase() === 'on';
                $widget.find('.widget-toggle-checkbox').prop('checked', isOn);
                $widget.find('.status-text').text(isOn ? 'ON' : 'OFF').removeClass('status-on status-off').addClass(isOn ? 'status-on' : 'status-off');
                break;
            case 'slider':
                $widget.find('.widget-slider').val(value);
                // update progress
                const min_s = parseFloat($widget.find('.label-min').text()) || parseFloat($widget.find('.widget-slider').attr('min')) || 0;
                const max_s = parseFloat($widget.find('.label-max').text()) || parseFloat($widget.find('.widget-slider').attr('max')) || 100;
                const pct_s = ((value - min_s) / (max_s - min_s)) * 100;
                $widget.find('.slider-progress').css('width', Math.max(0, Math.min(100, pct_s)) + '%');
                break;
            case 'gauge':
                const min_g = parseFloat($widget.find('.label-min').text()) || 0;
                const max_g = parseFloat($widget.find('.label-max').text()) || 100;
                if (window.updateGaugeModern) window.updateGaugeModern(key, parseFloat(value), min_g, max_g);
                break;
        }
        
        $widget.find('.update-time').html('<i class="far fa-clock"></i> Just now');
    }

    // Edit Layout Logic
    $('#toggleEditMode').click(function() {
        editMode = !editMode;
        if (editMode) {
            grid.enable();
            grid.enableMove(true);
            grid.enableResize(true);
            $('.grid-stack').addClass('edit-mode');
            $(this).html('<i class="fas fa-times"></i> Cancel').removeClass('btn-primary').addClass('btn-secondary');
            $('#saveLayout').show();
        } else {
            grid.disable();
            $('.grid-stack').removeClass('edit-mode');
            $(this).html('<i class="fas fa-edit"></i> Edit Layout').removeClass('btn-secondary').addClass('btn-primary');
            $('#saveLayout').hide();
        }
    });

    $('#saveLayout').click(function() {
        const positions = {};
        $('.grid-stack-item').each(function() {
            const key = $(this).attr('gs-id');
            positions[key] = {
                x: parseInt($(this).attr('gs-x')),
                y: parseInt($(this).attr('gs-y')),
                w: parseInt($(this).attr('gs-w')),
                h: parseInt($(this).attr('gs-h'))
            };
        });

        $.post('{{ route('widgets.update-positions') }}', {
            positions: positions,
            _token: '{{ csrf_token() }}'
        }).done(() => {
            alert('Layout saved!');
            location.reload();
        });
    });

    // Interaction Handlers (Send Command)
    $(document).on('change', '.widget-toggle-checkbox', function() {
        const key = $(this).data('widget-key');
        const val = $(this).is(':checked') ? '1' : '0';
        sendCommand(key, val);
    });

    $(document).on('change', '.widget-slider', function() {
         const key = $(this).data('widget-key');
         const val = $(this).val();
         sendCommand(key, val);
    });

    function sendCommand(key, value) {
        // Publish to MQTT
        const topic = `users/${userId}/devices/${deviceCode}/control/${key}`;
        if (window.mqttClient.isConnected()) {
            window.mqttClient.publish(topic, value, 1);
        }
        
        // Sync to DB
        $.ajax({
            url: `/api/devices/${deviceCode}/widgets/${key}`,
            method: 'POST',
            data: JSON.stringify({ 
                value: value,
                skip_mqtt: 1 // ✅ Don't publish again
            }),
            contentType: 'application/json',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
    }

    // Helper: Time Ago
    function tickTimeAgo() {
        const $el = $('#last-seen');
        const iso = $el.attr('data-last-seen');
        if (iso) {
            $el.html('Last seen: ' + timeAgo(new Date(iso)));
        }
    }
    
    function timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return seconds + " seconds ago";
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + " minutes ago";
        return Math.floor(minutes / 60) + " hours ago";
    }

    function updateStatusDot(online) {
        const $dot = $('#device-status-dot');
        if (online) $dot.removeClass('device-offline').addClass('device-online');
        else $dot.removeClass('device-online').addClass('device-offline');
    }

    function initializeGaugesLazy() {
         // Logic to update gauge SVGs based on values
         // Copied from dashboard or simplified
         $('.gauge-modern-container').each(function() {
             const key = $(this).find('svg').attr('id').replace('gauge-svg-', '');
             const val = parseFloat($(this).find('.gauge-value').text());
             const min = parseFloat($(this).find('.label-min').text());
             const max = parseFloat($(this).find('.label-max').text());
             updateGaugeModern(key, val, min, max);
         });
    }

    // Gauge global function helper because widget-card uses it
    window.updateGaugeModern = function(key, value, min, max) {
        const pct = Math.max(0, Math.min(1, (value - min) / (max - min)));
        const circumference = 2 * Math.PI * 80;
        const offset = circumference - (pct * circumference * 0.75); // 270 degree arc
        // Logic for specific gauge SVG implementation from previous tasks
        // Simplified for this context:
        const $circle = $(`#gauge-circle-${key}`);
        if ($circle.length) {
            $circle.css('stroke-dashoffset', offset); // This depends on how the SVG is set up
        }
    };
    function copyDeviceCode() {
        const code = $('#deviceCode').text().trim();
        navigator.clipboard.writeText(code).then(function() {
            showNotification('Device key copied', 'success');
        });
    }
</script>
@endpush

