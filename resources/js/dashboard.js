// Global vars
const prevWidgetValues = {};
let pollingInterval = 5000;
let editMode = false;

// Init dashboard
function initDashboard() {
    console.log('Dashboard initialized');

    // Modal handlers
    $('#widgetType').on('change', function() {
        const type = $(this).val();
        if (type === 'slider' || type === 'gauge') {
            $('.range-field').slideDown(300);
        } else {
            $('.range-field').slideUp(300);
        }
    });

    $('#addWidgetForm').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $submitBtn = $('#submitWidgetBtn');
        const originalBtnText = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            success: function(response) {
                showNotification('Widget added successfully!', 'success');
                setTimeout(() => {
                    $('#addWidgetModal').modal('hide');
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                console.error('❌ Widget creation failed:', xhr);
                let errorMessage = 'Failed to add widget.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                alert(errorMessage);
            },
            complete: () => {
                $submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });

    $('#addWidgetModal').on('hidden.bs.modal', () => {
        $('#addWidgetForm')[0].reset();
        $('.range-field').hide();
    });

    $('#addWidgetModal').on('shown.bs.modal', () => {
        $('#widgetName').focus();
    });

    // GridStack init
    @if (isset($selectedDevice) && $selectedDevice && $widgets->count() > 0)
        console.log(`📱 Device: {{ $selectedDevice->name }} ({{ $selectedDevice->device_code }})`);
        console.log(`📊 Widgets: {{ $widgets->count() }}`);

        if (typeof GridStack !== 'undefined') {
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
                console.log('✅ GridStack initialized');
                window.grid.on('change', (event, items) => {
                    if (editMode && items && items.length > 0) {
                        items.forEach(item => {
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
                initializeGaugesLazy();
                startOptimizedPolling();
            } else {
                console.error('❌ GridStack init failed');
            }
        } else {
            console.error('❌ GridStack library not loaded');
        }
    @else
        console.log('ℹ️ No widgets');
    @endif
}

// Lazy gauges
function initializeGaugesLazy() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const $container = $(entry.target);
                const widgetKey = $container.attr('id').replace('gauge-svg-', '');
                const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
                const value = parseFloat($widget.find('.gauge-value').text()) || 0;
                const min = parseFloat($widget.find('.label-min').text()) || 0;
                const max = parseFloat($widget.find('.label-max').text()) || 100;
                updateGaugeModern(widgetKey, value, min, max);
            }
        });
    });
    $('.gauge-modern-container').each(function() {
        observer.observe(this);
    });
    console.log('✅ Gauges lazy loaded');
}

// Polling
function startOptimizedPolling() {
    if (window._pollingInterval) clearInterval(window._pollingInterval);

    function poll() {
        const deviceCode = $('#last-seen').data('device-code');
        if (!deviceCode) return;

        fetch(`/api/devices/${deviceCode}/dashboard-data`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.ok ? res.json() : Promise.reject())
            .then(data => {
                updateLastSeenText(data.last_seen_at);
                updateStatusDot(data.is_online);

                if (data.widgets) {
                    let anyChanged = false;
                    Object.keys(data.widgets).forEach(key => {
                        const newVal = String(data.widgets[key].value || '');
                        if (!(key in prevWidgetValues) || prevWidgetValues[key] !== newVal) {
                            anyChanged = true;
                            prevWidgetValues[key] = newVal;
                            if (!editMode) updateWidgetUI(key, data.widgets[key].value, data.widgets[key].type);
                        }
                    });
                    if (anyChanged) {
                        $('#last-seen').attr('data-last-seen', new Date().toISOString()).html('Last seen: Just now');
                        $('#device-status-dot').removeClass('device-offline').addClass('device-online');
                    }
                }
            })
            .catch(err => console.debug('Polling error:', err));
    }

    poll();
    window._pollingInterval = setInterval(poll, pollingInterval);
    console.log('🔄 Polling started (5s)');
}

function stopOptimizedPolling() {
    if (window._pollingInterval) clearInterval(window._pollingInterval);
    console.log('🛑 Polling stopped');
}

// Edit mode toggle
$('#toggleEditMode').on('click', function(e) {
    e.preventDefault();
    console.log('Edit button clicked');

    if (!window.grid) {
        console.error('GridStack not available');
        showNotification('GridStack not loaded. Check dependencies.', 'error');
        return;
    }

    editMode = !editMode;
    if (editMode) {
        window.grid.enable();
        window.grid.enableMove(true);
        window.grid.enableResize(true);
        $('.grid-stack').addClass('edit-mode');
        $(this).html('<i class="fas fa-times"></i> Cancel').removeClass('btn-primary').addClass('btn-secondary');
        $('#saveLayout').show();
        $('.widget-actions').hide();
        showNotification('Edit mode enabled! Drag widgets.', 'info');
        console.log('✏️ Edit mode enabled');
    } else {
        window.grid.disable();
        $('.grid-stack').removeClass('edit-mode');
        $(this).html('<i class="fas fa-edit"></i> Edit Layout').removeClass('btn-secondary').addClass('btn-primary');
        $('#saveLayout').hide();
        $('.widget-actions').show();
        showNotification('Edit mode disabled', 'info');
        console.log('🔒 Edit mode disabled');
    }
});

// Save layout
$('#saveLayout').on('click', function(e) {
    e.preventDefault();

    if (!window.grid) {
        showNotification('No grid available', 'error');
        return;
    }

    const positions = {};
    $('.grid-stack-item').each(function() {
        const key = $(this).attr('gs-id');
        if (key) {
            positions[key] = {
                x: parseInt($(this).attr('gs-x')) || 0,
                y: parseInt($(this).attr('gs-y')) || 0,
                w: parseInt($(this).attr('gs-w')) || 4,
                h: parseInt($(this).attr('gs-h')) || 2
            };
        }
    });

    console.log('💾 Saving positions:', positions);

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: '{{ isset($selectedDevice) && $selectedDevice ? route('widgets.update-positions', $selectedDevice) : '#' }}',
        method: 'POST',
        data: { positions },
        success: () => {
            console.log('✅ Layout saved');
            showNotification('Layout saved!', 'success');
            setTimeout(() => location.reload(), 1000);
        },
        error: (xhr) => {
            console.error('❌ Save error:', xhr);
            showNotification('Save failed', 'error');
        },
        complete: () => {
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Layout');
        }
    });
});

// Update widget value
function updateWidgetValue(widgetKey, value) {
    @if (isset($selectedDevice) && $selectedDevice)
        const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
        $widget.find('.widget-toggle-checkbox, .widget-slider').prop('disabled', true);

        $.ajax({
            url: `/api/devices/{{ $selectedDevice->device_code }}/widgets/${widgetKey}`,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: JSON.stringify({ value: value.toString() }),
            success: (response) => {
                if (response.success) {
                    updateWidgetUI(widgetKey, response.widget.value, response.widget.type);
                    showNotification('Widget updated!', 'success');
                    $('#last-seen').attr('data-last-seen', new Date().toISOString()).html('Last seen: Just now');
                    $('#device-status-dot').removeClass('device-offline').addClass('device-online');
                    prevWidgetValues[widgetKey] = String(response.widget.value);
                }
            },
            error: (xhr) => {
                console.error(`❌ Update failed for ${widgetKey}:`, xhr);
                showNotification('Update failed', 'error');
            },
            complete: () => {
                $widget.find('.widget-toggle-checkbox, .widget-slider').prop('disabled', false);
            }
        });
    @endif
}

// Update UI
function updateWidgetUI(widgetKey, value, type) {
    const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
    if (!$widget.length) return;

    switch (type) {
        case 'toggle':
            const isOn = value == '1';
            $widget.find('.widget-toggle-checkbox').prop('checked', isOn);
            $widget.find('.status-text').text(isOn ? 'ON' : 'OFF').removeClass('status-on status-off').addClass(isOn ? 'status-on' : 'status-off');
            break;
        case 'slider':
            const sliderVal = parseFloat(value) || 0;
            $widget.find('.widget-slider').val(sliderVal);
            $widget.find('.widget-value-display').text(sliderVal);
            const min = parseFloat($widget.find('.widget-slider').attr('min')) || 0;
            const max = parseFloat($widget.find('.widget-slider').attr('max')) || 100;
            const pct = ((sliderVal - min) / (max - min)) * 100;
            $widget.find('.slider-progress').css('width', pct + '%');
            break;
        case 'gauge':
            const gaugeVal = parseFloat(value) || 0;
            $widget.find('.gauge-value').text(gaugeVal);
            const gaugeMin = parseFloat($widget.find('.label-min').text()) || 0;
            const gaugeMax = parseFloat($widget.find('.label-max').text()) || 100;
            updateGaugeModern(widgetKey, gaugeVal, gaugeMin, gaugeMax);
            break;
        case 'text':
            $widget.find('.widget-value-display').text(value);
            break;
    }
    $widget.find('.update-time').html('<i class="far fa-clock"></i> Just now');
}

// Helpers
function updateLastSeenText(iso) {
    const text = iso ? timeAgoFromISO(iso) : 'Never';
    $('#last-seen').html('Last seen: ' + text).attr('data-last-seen', iso || '');
}

function updateStatusDot(isOnline) {
    const $dot = $('#device-status-dot');
    if (isOnline) {
        $dot.removeClass('device-offline').addClass('device-online');
    } else {
        $dot.removeClass('device-online').addClass('device-offline');
    }
}

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

// Widget handlers
$(document).on('change', '.widget-toggle-checkbox', function() {
    const widgetKey = $(this).closest('.grid-stack-item').attr('gs-id');
    const value = $(this).is(':checked') ? '1' : '0';
    updateWidgetValue(widgetKey, value);
});

$(document).on('change', '.widget-slider', function() {
    const widgetKey = $(this).closest('.grid-stack-item').attr('gs-id');
    const value = $(this).val();
    updateWidgetValue(widgetKey, value);
});

$(document).on('input', '.widget-slider', function() {
    const widgetKey = $(this).closest('.grid-stack-item').attr('gs-id');
    const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
    const value = parseFloat($(this).val());
    const min = parseFloat($(this).attr('min')) || 0;
    const max = parseFloat($(this).attr('max')) || 100;
    $widget.find('.widget-value-display').text(value);
    const pct = ((value - min) / (max - min)) * 100;
    $widget.find('.slider-progress').css('width', pct + '%');
});

// Management
function deleteWidget(widgetKey) {
    if (!confirm('Delete this widget?')) return;
    $.ajax({
        url: `/widgets/${widgetKey}`,
        method: 'DELETE',
        success: () => {
            if (window.grid) {
                const el = $(`.grid-stack-item[gs-id="${widgetKey}"]`)[0];
                window.grid.removeWidget(el);
            }
            showNotification('Widget deleted!', 'success');
            setTimeout(() => location.reload(), 1000);
        },
        error: (xhr) => {
            console.error('❌ Delete failed:', xhr);
            showNotification('Delete failed', 'error');
        }
    });
}

function editWidget(widgetKey) {
    showNotification('Edit coming soon!', 'info');
}

// Notification
function showNotification(message, type) {
    const $alert = $(`<div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 80px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);"><i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i><strong>${type}:</strong> ${message}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>`);
    $('body').append($alert);
    setTimeout(() => $alert.alert('close'), 4000);
}

// Cleanup
$(window).on('beforeunload', () => {
    stopOptimizedPolling();
});

// Expose
window.initDashboard = initDashboard;
window.updateGaugeModern = updateGaugeModern; // Define elsewhere if needed