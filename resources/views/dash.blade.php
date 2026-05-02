@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @if ($devices->count() == 0)
        <!-- No Devices State -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4" style="border-radius: 20px;">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-microchip fa-4x mb-4" style="color: var(--primary-green); opacity: 0.3;"></i>
                        <h4 style="color: rgba(255, 255, 255, 0.9);">No Devices Yet</h4>
                        <p class="text-muted">Start by adding your first smart device</p>
                        <a href="{{ route('devices.create') }}" class="btn btn-primary btn-lg mt-3">
                            <i class="fas fa-plus"></i> Add Your First Device
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @elseif(!$selectedDevice)
        <!-- Device Not Found -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning shadow" style="border-radius: 12px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Selected device not found. Please select another device.
                </div>
            </div>
        </div>
    @else
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i id="device-status-dot"
                        class="fas fa-circle {{ $selectedDevice->isOnline() ? 'device-online' : 'device-offline' }}"
                        style="font-size: 0.8rem; margin-right: 10px;"></i>
                    {{ $selectedDevice->name }}
                </h1>

                <!-- UPDATED: add id and data-last-seen (ISO8601) -->
                <small class="text-muted" id="last-seen" data-device-code="{{ $selectedDevice->device_code }}"
                    data-last-seen="{{ $selectedDevice->last_seen_at ? $selectedDevice->last_seen_at->toIso8601String() : '' }}">
                    Last seen:
                    {{ $selectedDevice->last_seen_at ? $selectedDevice->last_seen_at->diffForHumans() : 'Never' }}
                </small>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm shadow-sm" data-toggle="modal"
                    data-target="#addWidgetModal">
                    <i class="fas fa-plus"></i> Add Widget
                </button>
                <button type="button" class="btn btn-primary btn-sm shadow-sm" id="toggleEditMode">
                    <i class="fas fa-edit"></i> Edit Layout
                </button>
                <button type="button" class="btn btn-success btn-sm shadow-sm" id="saveLayout" style="display: none;">
                    <i class="fas fa-save"></i> Save Layout
                </button>
                <a href="{{ route('device.edit') }}" class="btn btn-warning btn-sm shadow-sm">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>

        <!-- Widgets Grid -->
        @if ($widgets->count() > 0)
            <div class="grid-stack" style="min-height: 600px;">
                @foreach ($widgets as $widget)
                    <div class="grid-stack-item" gs-id="{{ $widget->key }}" gs-x="{{ $widget->position_x }}"
                        gs-y="{{ $widget->position_y }}" gs-w="{{ $widget->width }}" gs-h="{{ $widget->height }}">
                        <div class="grid-stack-item-content">
                            <div class="widget-wrapper">
                                @include('partials.widget-card', ['widget' => $widget, 'loop' => $loop])
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-5">
                <div class="card shadow-lg d-inline-block" style="max-width: 500px; border-radius: 20px;">
                    <div class="card-body p-5">
                        <i class="fas fa-inbox fa-4x mb-3" style="color: var(--primary-green); opacity: 0.3;"></i>
                        <p class="text-muted">No widgets yet. Add your first widget to get started!</p>
                        <button type="button" class="btn btn-primary mt-3" data-toggle="modal"
                            data-target="#addWidgetModal">
                            <i class="fas fa-plus"></i> Add Widget
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Add Widget Modal -->
        @include('partials.add-widget-modal', ['device' => $selectedDevice])
    @endif
@endsection

@push('styles')
    <style>
        .grid-stack {
            background: transparent;
        }

        .grid-stack-item {
            transition: all 0.3s ease;
        }

        .grid-stack.edit-mode .grid-stack-item {
            cursor: move;
        }

        .grid-stack.edit-mode .grid-stack-item:hover {
            transform: scale(1.02);
            z-index: 100;
        }

        .widget-wrapper {
            height: 100%;
            width: 100%;
        }

        .d-flex.gap-2>* {
            margin-right: 8px;
        }

        .d-flex.gap-2>*:last-child {
            margin-right: 0;
        }

        /* status dot colors */
        .device-online {
            color: #28a745;
        }

        /* green */
        .device-offline {
            color: #6c757d;
        }

        /* gray */
    </style>
@endpush

@push('scripts')
    <script>
        // Global variables
        // ============================================
        // FIXED DASHBOARD SCRIPT
        // ============================================

        $(document).ready(function() {
            console.log('═══════════════════════════════════════════════════════');
            console.log('🎯 Dashboard initialized');
            console.log('═══════════════════════════════════════════════════════');

            // Modal handlers
            $('#widgetType').on('change', function() {
                const type = $(this).val();
                if (type === 'slider' || type === 'gauge') {
                    $('.range-field').slideDown(300);
                } else {
                    $('.range-field').slideUp(300);
                }
            });

            // Add widget form submit
            $('#addWidgetForm').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $submitBtn = $('#submitWidgetBtn');
                const originalBtnText = $submitBtn.html();

                $submitBtn.prop('disabled', true).html(
                    '<i class="fas fa-spinner fa-spin"></i> Creating...');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        showNotification('Widget added successfully!', 'success');
                        setTimeout(function() {
                            $('#addWidgetModal').modal('hide');
                            location.reload();
                        }, 1000);
                    },
                    error: function(xhr) {
                        console.error('❌ Widget creation failed:', xhr);
                        let errorMessage = 'Failed to add widget.';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errorMessage = Object.values(xhr.responseJSON.errors).flat().join(
                                '\n');
                        }
                        alert(errorMessage);
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });

            // Reset form when modal closes
            $('#addWidgetModal').on('hidden.bs.modal', function() {
                $('#addWidgetForm')[0].reset();
                $('.range-field').hide();
            });

            // Auto-focus when modal opens
            $('#addWidgetModal').on('shown.bs.modal', function() {
                $('#widgetName').focus();
            });

            @if (isset($selectedDevice) && $selectedDevice && $widgets->count() > 0)
                console.log(`📱 Device: {{ $selectedDevice->name }} ({{ $selectedDevice->device_code }})`);
                console.log(`📊 Widgets: {{ $widgets->count() }}`);

                // Initialize GridStack
                window.grid = GridStack.init({
                    cellHeight: 100,
                    column: 12,
                    margin: 20,
                    float: true,
                    animate: true,
                    disableDrag: true,
                    disableResize: true,
                    handle: '.card-header-modern',
                    resizable: {
                        handles: 'se, s, e'
                    }
                });

                if (window.grid) {
                    console.log('✅ GridStack initialized successfully');

                    window.grid.on('change', function(event, items) {
                        if (window.editMode && items && items.length > 0) {
                            items.forEach(function(item) {
                                if (item.el) {
                                    $(item.el).attr({
                                        'gs-x': item.x,
                                        'gs-y': item.y,
                                        'gs-w': item.w,
                                        'gs-h': item.h
                                    });
                                }
                            });
                        }
                    });
                }

                // Initialize gauges on page load
                initializeGauges();

                // Start auto-refresh polling (refreshDashboard handles both last_seen and widgets)
                window.refreshInterval = setInterval(refreshDashboard, 2000);
                console.log('🔄 Auto-refresh started (every 2 seconds)');
            @else
                console.log('ℹ️ No widgets to display');
            @endif

            console.log('═══════════════════════════════════════════════════════\n');
        });

        // ============================================
        // INITIALIZE GAUGES
        // ============================================
        function initializeGauges() {
            $('.gauge-modern-container').each(function() {
                const $container = $(this);
                const $svg = $container.find('.gauge-svg');
                const widgetKey = $svg.attr('id').replace('gauge-svg-', '');
                const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
                const value = parseFloat($widget.find('.gauge-value').text()) || 0;
                const min = parseFloat($widget.find('.label-min').text()) || 0;
                const max = parseFloat($widget.find('.label-max').text()) || 100;

                updateGaugeModern(widgetKey, value, min, max);
            });
        }

        // ============================================
        // EDIT MODE TOGGLE
        // ============================================
        window.editMode = false;

        $('#toggleEditMode').on('click', function(e) {
            e.preventDefault();
            if (!window.grid) {
                showNotification('No grid available', 'error');
                return;
            }

            window.editMode = !window.editMode;

            if (window.editMode) {
                window.grid.enable();
                window.grid.enableMove(true);
                window.grid.enableResize(true);
                $('.grid-stack').addClass('edit-mode');
                $(this).html('<i class="fas fa-times"></i> Cancel')
                    .removeClass('btn-primary').addClass('btn-secondary');
                $('#saveLayout').show();
                $('.widget-actions').hide();
                showNotification('Edit mode enabled!', 'info');
            } else {
                window.grid.disable();
                $('.grid-stack').removeClass('edit-mode');
                $(this).html('<i class="fas fa-edit"></i> Edit Layout')
                    .removeClass('btn-secondary').addClass('btn-primary');
                $('#saveLayout').hide();
                $('.widget-actions').show();
            }
        });

        // ============================================
        // SAVE LAYOUT
        // ============================================
        $('#saveLayout').on('click', function(e) {
            e.preventDefault();
            if (!window.grid) return;

            const positions = {};
            $('.grid-stack-item').each(function() {
                const $item = $(this);
                const key = $item.attr('gs-id');
                if (key) {
                    positions[key] = {
                        x: parseInt($item.attr('gs-x')) || 0,
                        y: parseInt($item.attr('gs-y')) || 0,
                        w: parseInt($item.attr('gs-w')) || 4,
                        h: parseInt($item.attr('gs-h')) || 2
                    };
                }
            });

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '{{ isset($selectedDevice) && $selectedDevice ? route('widgets.update-positions') : '#' }}',
                method: 'POST',
                data: {
                    positions: positions
                },
                success: function(response) {
                    showNotification('Layout saved!', 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                error: function(xhr) {
                    console.error('Save layout error:', xhr);
                    showNotification('Failed to save layout', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Layout');
                }
            });
        });

        // ============================================
        // UPDATE WIDGET VALUE (User Interaction)
        // ============================================
        function updateWidgetValue(widgetKey, value) {
            @if (isset($selectedDevice) && $selectedDevice)
                const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
                $widget.find('.widget-toggle-checkbox, .widget-slider').prop('disabled', true);

                console.log(`📤 Sending update: ${widgetKey} = ${value}`);

                $.ajax({
                    url: `/api/devices/{{ $selectedDevice->device_code }}/widgets/${widgetKey}`,
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        value: value.toString()
                    }),
                    success: function(response) {
                        if (response.success) {
                            console.log(`✅ Widget updated: ${widgetKey}`);
                            updateWidgetUI(widgetKey, response.widget.value, response.widget.type);
                            showNotification('Widget updated!', 'success');
                        }
                    },
                    error: function(xhr) {
                        console.error(`❌ Update failed for ${widgetKey}:`, xhr);
                        showNotification('Update failed', 'error');
                    },
                    complete: function() {
                        $widget.find('.widget-toggle-checkbox, .widget-slider').prop('disabled', false);
                    }
                });
            @endif
        }

        // ============================================
        // UPDATE WIDGET UI (from server data)
        // ============================================
        function updateWidgetUI(widgetKey, value, type) {
            const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
            if (!$widget.length) {
                console.warn(`Widget not found: ${widgetKey}`);
                return;
            }

            console.log(`🔄 Updating UI: ${widgetKey} (${type}) = ${value}`);

            switch (type) {
                case 'toggle':
                    const isOn = value == '1' || value === 'ON' || value === true;

                    // Update checkbox
                    $widget.find('.widget-toggle-checkbox').prop('checked', isOn);

                    // Update status text
                    const $statusText = $widget.find('.status-text');
                    $statusText.text(isOn ? 'ON' : 'OFF')
                        .removeClass('status-on status-off')
                        .addClass(isOn ? 'status-on' : 'status-off');
                    break;

                case 'slider':
                    const sliderValue = parseFloat(value) || 0;
                    const $slider = $widget.find('.widget-slider');
                    const min = parseFloat($slider.attr('min')) || 0;
                    const max = parseFloat($slider.attr('max')) || 100;

                    // Update slider position
                    $slider.val(sliderValue);

                    // Update display value
                    $widget.find('.widget-value-display').text(sliderValue);

                    // Update progress bar
                    const percentage = ((sliderValue - min) / (max - min)) * 100;
                    $widget.find('.slider-progress').css('width', percentage + '%');
                    break;

                case 'gauge':
                    const gaugeValue = parseFloat(value) || 0;
                    const $gaugeWidget = $widget.find('.gauge-modern-container');
                    const gaugeMin = parseFloat($widget.find('.label-min').text()) || 0;
                    const gaugeMax = parseFloat($widget.find('.label-max').text()) || 100;

                    // Update gauge display
                    $widget.find('.gauge-value').text(isNaN(gaugeValue) ? gaugeMin : gaugeValue);

                    // Update gauge circle
                    updateGaugeModern(widgetKey, isNaN(gaugeValue) ? gaugeMin : gaugeValue, gaugeMin, gaugeMax);
                    break;

                case 'text':
                    $widget.find('.widget-value-display').text(value);
                    break;

                default:
                    console.warn(`Unknown widget type: ${type}`);
            }

            // Update timestamp
            $widget.find('.update-time').html('<i class="far fa-clock"></i> Just now');
        }

        // --- Single function to refresh both last-seen and all widgets ---
        (function() {
            const REFRESH_INTERVAL_MS = 2000; // how often to refresh dashboard
            const TIMEAGO_INTERVAL_MS = 1000; // how often to update "time ago" display

            function timeAgoFromISO(iso) {
                if (!iso) return 'Never';
                const then = new Date(iso);
                const now = new Date();
                const diffMs = now - then;
                if (diffMs < 1000) return 'Just now';
                const diff = Math.floor(diffMs / 1000);
                if (diff < 60) return diff + ' seconds ago';
                const diffMin = Math.floor(diff / 60);
                if (diffMin < 60) return diffMin + ' minutes ago';
                const diffH = Math.floor(diffMin / 60);
                if (diffH < 24) return diffH + ' hours ago';
                const diffD = Math.floor(diffH / 24);
                return diffD + ' days ago';
            }

            function updateLastSeenText(iso) {
                const $el = $('#last-seen');
                if (!$el.length) return;
                const text = iso ? timeAgoFromISO(iso) : 'Never';
                $el.html('Last seen: ' + text);
                $el.attr('data-last-seen', iso ? iso : '');
            }

            function updateStatusDot(isOnline) {
                const $dot = $('#device-status-dot');
                if (!$dot.length) return;
                if (isOnline) {
                    $dot.removeClass('device-offline').addClass('device-online');
                } else {
                    $dot.removeClass('device-online').addClass('device-offline');
                }
            }

            async function refreshDashboard() {
                const $lastSeenEl = $('#last-seen');
                if (!$lastSeenEl.length) return;
                const deviceCode = $lastSeenEl.data('device-code');
                if (!deviceCode) return;

                const statusUrl = `/api/devices/${deviceCode}/status`;
                const widgetsUrl = `/api/devices/${deviceCode}/widgets`;

                try {
                    const [statusResp, widgetsResp] = await Promise.all([
                        fetch(statusUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        }),
                        fetch(widgetsUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                    ]);

                    // status update
                    if (statusResp.ok) {
                        const statusData = await statusResp.json();
                        const iso = statusData.last_seen_at || null;
                        const isOnline = !!statusData.is_online;
                        updateLastSeenText(iso);
                        updateStatusDot(isOnline);
                    }

                    // widgets update
                    if (widgetsResp.ok) {
                        const widgetsData = await widgetsResp.json();
                        if (widgetsData.success && widgetsData.widgets) {
                            const widgets = Array.isArray(widgetsData.widgets) ?
                                widgetsData.widgets :
                                Object.keys(widgetsData.widgets).map(k => {
                                    const w = widgetsData.widgets[k];
                                    w.key = k;
                                    return w;
                                });

                            widgets.forEach(widget => {
                                if (!widget.key) return;
                                updateWidgetUI(widget.key, widget.value, widget.type);
                            });
                        }
                    }
                } catch (err) {
                    console.debug('refreshDashboard error', err);
                }
            }

            function tickTimeAgo() {
                const $el = $('#last-seen');
                if (!$el.length) return;
                const iso = $el.attr('data-last-seen') || $el.data('last-seen') || null;
                updateLastSeenText(iso);
            }

            function startAutoRefresh() {
                if (window._dashboardRefreshInterval) clearInterval(window._dashboardRefreshInterval);
                if (window._dashboardTimeAgoInterval) clearInterval(window._dashboardTimeAgoInterval);

                refreshDashboard();

                window._dashboardRefreshInterval = setInterval(refreshDashboard, REFRESH_INTERVAL_MS);
                window._dashboardTimeAgoInterval = setInterval(tickTimeAgo, TIMEAGO_INTERVAL_MS);
                console.log('🔄 Dashboard auto-refresh started');
            }

            function stopAutoRefresh() {
                if (window._dashboardRefreshInterval) clearInterval(window._dashboardRefreshInterval);
                if (window._dashboardTimeAgoInterval) clearInterval(window._dashboardTimeAgoInterval);
                console.log('🛑 Dashboard auto-refresh stopped');
            }

            // start when DOM ready
            $(function() {
                if ($('#last-seen').length) startAutoRefresh();

                $(window).on('beforeunload', function() {
                    stopAutoRefresh();
                });
            });

            // expose controls for debugging/manual trigger
            window.refreshDashboard = refreshDashboard;
            window.startDashboardRefresh = startAutoRefresh;
            window.stopDashboardRefresh = stopAutoRefresh;
        })();

        // ============================================
        // GAUGE UPDATE FUNCTION
        // ============================================
        function updateGaugeModern(widgetKey, value, min, max) {
            const circle = document.getElementById('gauge-circle-' + widgetKey);
            if (!circle) {
                console.warn(`Gauge circle not found: gauge-circle-${widgetKey}`);
                return;
            }

            // safe number parsing
            min = Number(min);
            max = Number(max);
            value = Number(value);

            if (isNaN(min)) min = 0;
            if (isNaN(max)) max = min + 100; // fallback
            if (isNaN(value)) value = min;

            // avoid division by zero
            if (max === min) {
                max = min + 1;
            }

            const radius = 80; // match r in SVG
            const circumference = 2 * Math.PI * radius; // ~502.65
            // ensure svg attribute exist
            circle.setAttribute('stroke-dasharray', String(circumference));

            // clamp percentage 0..1
            const pct = Math.max(0, Math.min(1, (value - min) / (max - min)));
            const offset = circumference * (1 - pct);

            // smooth animation
            circle.style.transition = 'stroke-dashoffset 500ms ease';
            // set as number/string (no 'px')
            circle.style.strokeDashoffset = String(offset);

            // debug
            console.debug(
                `Gauge[${widgetKey}] value=${value} min=${min} max=${max} pct=${(pct*100).toFixed(1)}% offset=${offset.toFixed(2)}`
            );
        }

        // ============================================
        // WIDGET EVENT HANDLERS
        // ============================================
        // Toggle switch change
        $(document).on('change', '.widget-toggle-checkbox', function() {
            const widgetKey = $(this).closest('.grid-stack-item').attr('gs-id');
            const newValue = $(this).is(':checked') ? '1' : '0';
            updateWidgetValue(widgetKey, newValue);
        });

        // Slider change (on release)
        $(document).on('change', '.widget-slider', function() {
            const widgetKey = $(this).closest('.grid-stack-item').attr('gs-id');
            updateWidgetValue(widgetKey, $(this).val());
        });

        // Slider input (real-time UI update while dragging)
        $(document).on('input', '.widget-slider', function() {
            const widgetKey = $(this).closest('.grid-stack-item').attr('gs-id');
            const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
            const value = parseFloat($(this).val());
            const min = parseFloat($(this).attr('min')) || 0;
            const max = parseFloat($(this).attr('max')) || 100;

            // Update display immediately
            $widget.find('.widget-value-display').text(value);

            // Update progress bar
            const percentage = ((value - min) / (max - min)) * 100;
            $widget.find('.slider-progress').css('width', percentage + '%');
        });

        // ============================================
        // WIDGET MANAGEMENT
        // ============================================
        function deleteWidget(widgetKey) {
            if (!confirm('Are you sure you want to delete this widget?')) return;

            $.ajax({
                url: `/widgets/${widgetKey}`,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    if (window.grid) {
                        const element = $(`.grid-stack-item[gs-id="${widgetKey}"]`)[0];
                        window.grid.removeWidget(element);
                    }
                    showNotification('Widget deleted!', 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                error: function(xhr) {
                    console.error('Delete failed:', xhr);
                    showNotification('Failed to delete widget', 'error');
                }
            });
        }

        function editWidget(widgetKey) {
            showNotification('Edit feature coming soon!', 'info');
        }

        // ============================================
        // NOTIFICATION SYSTEM
        // ============================================
        function showNotification(message, type) {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            } [type] || 'alert-info';

            const $alert = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed"
             style="top: 80px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    `);

            $('body').append($alert);
            setTimeout(() => $alert.alert('close'), 4000);
        }

        // ============================================
        // CLEANUP ON PAGE UNLOAD
        // ============================================
        $(window).on('beforeunload', function() {
            if (window.refreshInterval) {
                clearInterval(window.refreshInterval);
                console.log('🛑 Polling stopped');
            }
            if (window._dashboardRefreshInterval) clearInterval(window._dashboardRefreshInterval);
            if (window._dashboardTimeAgoInterval) clearInterval(window._dashboardTimeAgoInterval);
        });
    </script>
@endpush
