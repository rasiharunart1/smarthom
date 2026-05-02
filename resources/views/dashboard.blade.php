@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @if ($devices->count() == 0)
        <!-- No Devices State -->
        <div class="row">
            <div class="col-12">
                <div class="glass-card text-center py-5">
                    <i class="fas fa-microchip fa-4x mb-4" style="color: var(--primary-green); opacity: 0.3;"></i>
                    <h4 style="color: var(--text-primary);">Universal Fleet Empty</h4>
                    <p style="color: var(--text-muted);">No hardware nodes are currently registered in the inventory.</p>
                    <a href="{{ route('devices.create') }}" class="btn glass-button glass-button-primary mt-3" style="width: auto;">
                        Provision First Hardware
                    </a>
                </div>
            </div>
        </div>
    @elseif(! $selectedDevice)
        <!-- Device Not Found -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning border-0 shadow" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24; border-radius: 12px; backdrop-filter: blur(10px);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Selected telemetry node not found. Please re-assign from the fleet selector.
                </div>
            </div>
        </div>
    @else
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
                    <i id="device-status-dot"
                        class="fas fa-circle {{ $selectedDevice->isOnline() ? 'pulse-online' : '' }}"
                        style="font-size: 0.8rem; margin-right: 12px; color: {{ $selectedDevice->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};"></i>
                    {{ $selectedDevice->name }}
                </h1>

                <small style="color: var(--text-muted);" id="last-seen" 
                    data-last-seen="{{ $selectedDevice->last_seen_at ? $selectedDevice->last_seen_at->toIso8601String() : '' }}">
                    Last sync: {{ $selectedDevice->last_seen_at ? $selectedDevice->last_seen_at->diffForHumans() : 'Standby' }}
                </small>
            </div>
            <div class="d-flex gap-2">
                @php $canAdd = auth()->user()->canAddWidget($selectedDevice); @endphp
                <button type="button" class="btn glass-button {{ $canAdd ? 'btn-success' : 'btn-secondary' }}" 
                    id="topAddModuleBtn"
                    {{ $canAdd ? 'data-toggle=modal data-target=#addWidgetModal' : 'disabled' }}
                    style="background: {{ $canAdd ? 'rgba(16, 185, 129, 0.1)' : 'rgba(255, 255, 255, 0.05)' }}; 
                           color: {{ $canAdd ? 'var(--primary-green-light)' : 'var(--text-muted)' }}; 
                           border: 1px solid {{ $canAdd ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255, 255, 255, 0.1)' }}; 
                           width: auto; margin-top: 0;"
                    title="{{ $canAdd ? 'Add Module' : 'Module capacity reached for this node (' . auth()->user()->getLimit('max_widgets_per_device') . ' Max)' }}">
                    <i class="fas fa-plus mr-2"></i>Module
                </button>
                <button type="button" class="btn glass-button btn-primary" id="toggleEditMode" style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); width: auto; margin-top: 0;">
                    <i class="fas fa-th-large mr-2"></i>Layout
                </button>
                
                @if(auth()->user()->canUseLstm())
                    <button type="button" class="btn glass-button {{ $selectedDevice->lstm_enabled ? 'btn-ai-active' : '' }}" 
                        id="toggleLstmBtn"
                        data-device-id="{{ $selectedDevice->id }}"
                        style="width: auto; margin-top: 0; position: relative; overflow: hidden; {{ $selectedDevice->lstm_enabled ? 'background: rgba(139, 92, 246, 0.2); border-color: rgba(139, 92, 246, 0.5); color: #a78bfa;' : 'background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text-muted);' }}">
                        <i class="fas fa-brain mr-2 {{ $selectedDevice->lstm_enabled ? 'fa-pulse' : '' }}"></i>
                        <span id="lstmBtnText">{{ $selectedDevice->lstm_enabled ? 'AI Active' : 'AI Control' }}</span>
                        @if($selectedDevice->lstm_enabled)
                            <div class="ai-glow"></div>
                        @endif
                    </button>
                @endif
                <button type="button" class="btn glass-button glass-button-primary" id="saveLayout" style="display: none; width: auto; margin-top: 0;">
                    <i class="fas fa-save mr-2"></i>Store Grid
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="row">
            <!-- Main Grid Area -->
            <div id="mainGridContainer" class="col-12 transition-all duration-300">
                @if ($widgets->count() > 0)
                    <div class="grid-stack" style="min-height: 500px;">
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
                    <div class="glass-card text-center py-5">
                        <i class="fas fa-th-large fa-3x mb-3" style="color: var(--primary-green); opacity: 0.2;"></i>
                        <h5 style="color: white;">Node Empty</h5>
                        <p style="color: var(--text-muted);">This node has no active modules.</p>
                        <p class="small text-muted mb-0">Click Layout to start adding modules.</p>
                    </div>
                @endif
            </div>

            <!-- Widget Sidebar -->
            <div id="widgetSidebar" class="col-lg-3 d-none">
                <div class="sidebar-wrapper sticky-top" style="top: 100px; z-index: 900;">
                    <div class="glass-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-white font-weight-bold mb-0">
                                <i class="fas fa-cubes mr-2" style="color: var(--primary-green);"></i>Widget Box
                            </h6>
                            <!-- Manual Add Button moved here -->
                            <button class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#addWidgetModal" title="Manual Add">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <p class="small text-muted mb-3">Drag items to grid to create</p>
                        
                        <div class="widget-toolbox">
                            <!-- Toggle Widget -->
                            <div class="toolbox-item" gs-w="4" gs-h="2">
                                <input type="hidden" class="widget-type-input" value="toggle">
                                <div class="toolbox-icon">
                                    <i class="fas fa-toggle-on"></i>
                                </div>
                                <div class="toolbox-label">Switch</div>
                                <i class="fas fa-grip-vertical toolbox-drag-handle"></i>
                            </div>

                            <!-- Slider Widget -->
                            <div class="toolbox-item" gs-w="4" gs-h="2">
                                <input type="hidden" class="widget-type-input" value="slider">
                                <div class="toolbox-icon">
                                    <i class="fas fa-sliders-h"></i>
                                </div>
                                <div class="toolbox-label">Slider</div>
                                <i class="fas fa-grip-vertical toolbox-drag-handle"></i>
                            </div>

                            <!-- Gauge Widget -->
                            <div class="toolbox-item" gs-w="3" gs-h="3">
                                <input type="hidden" class="widget-type-input" value="gauge">
                                <div class="toolbox-icon">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <div class="toolbox-label">Gauge</div>
                                <i class="fas fa-grip-vertical toolbox-drag-handle"></i>
                            </div>

                            <!-- Value Display -->
                            <div class="toolbox-item" gs-w="3" gs-h="2">
                                <input type="hidden" class="widget-type-input" value="text">
                                <div class="toolbox-icon">
                                    <i class="fas fa-font"></i>
                                </div>
                                <div class="toolbox-label">Value Display</div>
                                <i class="fas fa-grip-vertical toolbox-drag-handle"></i>
                            </div>

                            <!-- Chart Widget -->
                            <div class="toolbox-item" gs-w="6" gs-h="4">
                                <input type="hidden" class="widget-type-input" value="chart">
                                <div class="toolbox-icon">
                                    <i class="fas fa-chart-area"></i>
                                </div>
                                <div class="toolbox-label">Chart</div>
                                <i class="fas fa-grip-vertical toolbox-drag-handle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Widget Modal -->
    @if ($selectedDevice)
        @include('partials.add-widget-modal', ['device' => $selectedDevice])
        @include('partials.edit-widget-modal', ['device' => $selectedDevice])
    @endif

    <!-- Expiration Popup -->
    @if(isset($showExpirationPopup) && $showExpirationPopup)
        <div class="modal fade show" id="expirationModal" tabindex="-1" role="dialog" aria-labelledby="expirationModalLabel" aria-hidden="true" style="display: block; background: rgba(0,0,0,0.8);">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content glass-card border-0">
                    <div class="modal-header border-0">
                        <h5 class="modal-title text-white" id="expirationModalLabel">
                            <i class="fas fa-exclamation-circle text-warning mr-2"></i> Subscription Expired
                        </h5>
                    </div>
                    <div class="modal-body text-white">
                        <p>Your subscription plan has expired. Previous access to advanced features may be restricted.</p>
                        <p>Please contact the administrator to renew your plan.</p>
                    </div>
                    <div class="modal-footer border-0">
                        @if(isset($adminWhatsapp) && $adminWhatsapp)
                            <a href="https://wa.me/{{ $adminWhatsapp }}" target="_blank" class="btn glass-button btn-success">
                                <i class="fab fa-whatsapp mr-2"></i> Contact Admin
                            </a>
                        @else
                            <button type="button" class="btn glass-button btn-secondary" disabled>
                                Contact Admin (No Number Set)
                            </button>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="btn glass-button btn-primary">
                            <i class="fas fa-user mr-2"></i> View Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Disable closing by clicking outside or escape key
            document.addEventListener('DOMContentLoaded', function() {
                var expirationModal = new bootstrap.Modal(document.getElementById('expirationModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                expirationModal.show();
            });
        </script>
    @endif
@endsection

@push('styles')
    <style>
        .pulse-online {
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { opacity: 1; text-shadow: 0 0 5px currentColor; }
            50% { opacity: 0.4; text-shadow: none; }
            100% { opacity: 1; text-shadow: 0 0 5px currentColor; }
        }

        /* Essential GridStack Styling for Empty State */
        .grid-stack {
            background: rgba(255, 255, 255, 0.02); /* Slight tint to show area */
            min-height: 400px !important;    /* Ensure drop/click area exists */
            border-radius: 12px;
            border: 1px dashed rgba(255, 255, 255, 0.1); /* Helper border */
            transition: all 0.3s ease;
        }
        
        /* GridStack Item Styling */
        .grid-stack-item {
            transition: filter 0.2s ease;
        }
        
        .grid-stack-item-content {
            background: transparent !important;
            border-radius: var(--radius-md);
            border: none !important;
            transition: all 0.3s ease;
        }
        
        .widget-wrapper {
            height: 100%;
            padding: 5px;
        }
        
        /* Placeholder when dragging */
        .grid-stack-placeholder > .grid-stack-item-content {
            background: rgba(16, 185, 129, 0.1) !important;
            border: 2px dashed rgba(16, 185, 129, 0.4) !important;
            border-radius: var(--radius-md);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }
        
        /* Edit Mode Enhancements */
        .grid-stack.edit-mode .grid-stack-item {
            cursor: move;
        }
        
        .grid-stack.edit-mode .grid-stack-item:hover {
            filter: brightness(1.05);
        }
        
        .grid-stack.edit-mode .grid-stack-item-content {
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
        }
        
        .grid-stack.edit-mode .widget-actions {
            opacity: 1 !important;
        }
        
        /* Drag handle indicator (show when hovering in edit mode) */
        .grid-stack.edit-mode .grid-stack-item::before {
            content: '\f58e'; /* FontAwesome grip icon */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(16, 185, 129, 0.5);
            font-size: 14px;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
        }
        
        .grid-stack.edit-mode .grid-stack-item:hover::before {
            opacity: 0.8;
        }
        
        /* Resize handle styling */
        .grid-stack.edit-mode .ui-resizable-se {
            background: rgba(16, 185, 129, 0.3);
            border-radius: 4px;
            width: 12px;
            height: 12px;
            right: 2px;
            bottom: 2px;
            transition: all 0.2s ease;
        }
        
        .grid-stack.edit-mode .ui-resizable-se:hover {
            background: rgba(16, 185, 129, 0.6);
            width: 16px;
            height: 16px;
        }
        
        /* Smooth transitions */
        .grid-stack-item {
            transition: transform 0.3s ease, opacity 0.2s ease !important;
        }
        
        .grid-stack-item.grid-stack-item-moving {
            opacity: 0.8;
            z-index: 100;
        }
        
        /* AI Status Indicators */
        .ai-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #718096;
        }
        .ai-status-ready { background: #10b981; box-shadow: 0 0 8px #10b981; }
        .ai-status-error { background: #ef4444; }
        .ai-status-processing { background: #3b82f6; animation: ai-pulse 1s infinite; }
        @keyframes ai-pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
        /* Sidebar Toolbox */
        .toolbox-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: move; /* Fallback */
            cursor: grab;
            transition: all 0.2s ease;
        }

        .toolbox-item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .toolbox-icon {
            width: 36px;
            height: 36px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary-green-light);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .toolbox-label {
            flex: 1;
            font-weight: 500;
            color: var(--text-primary);
        }

        .toolbox-drag-handle {
            color: var(--text-muted);
            opacity: 0.5;
            font-size: 0.9rem;
        }

        .toolbox-item:hover .toolbox-drag-handle {
            opacity: 1;
            color: var(--primary-green);
        }
        
        /* When dragging from sidebar */
        .grid-stack-drag-helper {
            z-index: 9999 !important;
            opacity: 0.9;
        }

        .grid-stack-drag-helper .toolbox-item {
            background: #1e2749; /* Solid bg when dragging */
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid var(--primary-green);
        }

        /* AI Glow Effect */
        .ai-glow {
            position: absolute;
            top: 0;
            left: -50%;
            width: 200%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.4), transparent);
            transform: skewX(-20deg);
            animation: shine 3s infinite;
            pointer-events: none;
        }
        
        @keyframes shine {
            0% { left: -50%; }
            100% { left: 150%; }
        }
    </style>
@endpush

@push('scripts')
    {{-- Gridstack.js --}}
    <script src="https://cdn.jsdelivr.net/npm/gridstack@9.4.0/dist/gridstack-all.js"></script>
    {{-- MQTT.js --}}
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Global Widget Data for Editing
        const widgetRegistry = {!! json_encode($widgets->keyBy('key')) !!};
        const chartInstances = {}; // Store chart instances by widget key

        // Initialize Charts
        function initCharts() {
            console.log('🎨 Initializing charts...');
            let chartCount = 0;
            
            Object.values(widgetRegistry).forEach(widget => {
                if (widget.type === 'chart') {
                    console.log(`🎨 Found chart widget: ${widget.key}`, widget);
                    createChart(widget);
                    chartCount++;
                }
            });
            
            console.log(`🎨 Total charts initialized: ${chartCount}`);
        }

        function createChart(widget) {
            let ctx = document.getElementById(`chart-${widget.key}`);
            if (!ctx) {
                console.error(`📊 Chart ${widget.key}: Canvas element not found!`);
                return;
            }

            // NUCLEAR OPTION: Forcefully clear canvas by replacing it with a FRESH element
            // cloneNode(true) might copy Chart.js internal properties/listeners, so we create from scratch
            const parent = ctx.parentNode;
            const newCanvas = document.createElement('canvas');
            
            // Copy essential attributes
            if (ctx.id) newCanvas.id = ctx.id;
            // Remove 'chartjs-render-monitor' class if present to be safe, but copying className is usually fine 
            // as long as we don't copy internal properties.
            if (ctx.className) newCanvas.className = ctx.className; 
            
            // Copy styling to maintain layout
            newCanvas.style.cssText = ctx.style.cssText;
            // Explicitly set width/height attributes if present (Chart.js often sets these)
            if (ctx.getAttribute('width')) newCanvas.setAttribute('width', ctx.getAttribute('width'));
            if (ctx.getAttribute('height')) newCanvas.setAttribute('height', ctx.getAttribute('height'));

            // Try graceful destroy on old canvas first
            try {
                const existingChart = Chart.getChart(ctx);
                if (existingChart) {
                    existingChart.destroy();
                }
            } catch (e) { console.warn('Chart destroy error:', e); }

            if (chartInstances[widget.key]) {
                try {
                    chartInstances[widget.key].destroy();
                } catch (e) {}
                delete chartInstances[widget.key];
            }

            // Swap with clean canvas
            parent.replaceChild(newCanvas, ctx);
            ctx = newCanvas; // Update reference to new canvas

            // Single source key (changed from array to single value)
            const sourceKey = widget.config?.source_key;
            if (!sourceKey) {
                console.warn(`📊 Chart ${widget.key}: No source key configured. Please edit widget and select a variable.`);
                return;
            }

            console.log(`📊 Chart ${widget.key}: Creating chart for source: ${sourceKey}`);

            const sourceWidget = widgetRegistry[sourceKey];
            const label = sourceWidget ? sourceWidget.name : sourceKey;
            const color = 'rgba(16, 185, 129, 0.8)'; // Primary green
            
            const dataset = {
                label: label,
                data: [], // Starts empty, fills with real-time data
                borderColor: color,
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 0 // Hide points for clean look
            };

            // Build Y-axis configuration
            const yAxisConfig = {
                beginAtZero: false,
                grid: { color: 'rgba(255, 255, 255, 0.1)' },
                ticks: { 
                    color: 'rgba(255, 255, 255, 0.5)'
                }
            };

            // Apply min/max if specified
            if (widget.min !== undefined && widget.min !== null && widget.min !== '') {
                yAxisConfig.min = parseFloat(widget.min);
                console.log(`📊 Chart ${widget.key}: Y-axis min = ${yAxisConfig.min}`);
            }
            if (widget.max !== undefined && widget.max !== null && widget.max !== '') {
                yAxisConfig.max = parseFloat(widget.max);
                console.log(`📊 Chart ${widget.key}: Y-axis max = ${yAxisConfig.max}`);
            }

            // Apply Y-axis step size if specified
            if (widget.config?.y_axis_step) {
                yAxisConfig.ticks.stepSize = parseFloat(widget.config.y_axis_step);
                console.log(`📊 Chart ${widget.key}: Y-axis step = ${yAxisConfig.ticks.stepSize}`);
            }

            chartInstances[widget.key] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [], // Time labels
                    datasets: [dataset] // Single dataset
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false, // Disable for performance
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            display: false, // Hide time axis for clean look
                            grid: { display: false }
                        },
                        y: yAxisConfig
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: 'white' }
                        }
                    }
                }
            });
            
            console.log(`📊 Chart ${widget.key}: Chart instance created successfully`);
            
            // Load Historical Data
            loadChartHistory(chartInstances[widget.key], widget);
        }

        function loadChartHistory(chart, widget) {
            const sourceKey = widget.config?.source_key;
            if (!sourceKey) {
                console.warn(`📊 Chart ${widget.key}: No source key configured`);
                return;
            }

            const period = widget.config?.period || '24h'; // Default to 24h
            const url = `/history`;
            
            console.log(`📊 Chart ${widget.key}: Loading history for key: ${sourceKey}, Period: ${period}`);
            console.log(`📊 Chart ${widget.key}: API URL: ${url}?keys=${sourceKey}&period=${period}`);
            
            $.get(url, { keys: sourceKey, period: period })
                .done(response => {
                    console.log(`📊 Chart ${widget.key}: History loaded`, response);
                    
                    if (response.success && response.data) {
                        const historicData = response.data[sourceKey];
                        
                        if (historicData && Array.isArray(historicData) && historicData.length > 0) {
                            const dataset = chart.data.datasets[0];
                            
                            // Map data
                            dataset.data = historicData.map(d => parseFloat(d.value));
                            
                            // Set labels from timestamps
                            chart.data.labels = historicData.map(d => {
                                const date = new Date(d.timestamp);
                                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            });
                            
                            console.log(`📊 Chart ${widget.key}: Loaded ${dataset.data.length} data points`);
                            chart.update();
                        } else {
                            console.warn(`📊 Chart ${widget.key}: No data found for key: ${sourceKey}`);
                        }
                    }
                })
                .fail(xhr => {
                    console.error(`📊 Chart ${widget.key}: Failed to load history`, xhr);
                });
        }

        function stringToColor(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                hash = str.charCodeAt(i) + ((hash << 5) - hash);
            }
            const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
            return '#' + '00000'.substring(0, 6 - c.length) + c;
        }

        // Call init on load
        document.addEventListener('DOMContentLoaded', initCharts);

        function updateDependentCharts(sourceKey, value) {
            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            Object.keys(chartInstances).forEach(widgetKey => {
                const chart = chartInstances[widgetKey];
                const widget = widgetRegistry[widgetKey];
                
                // Check if this chart uses the sourceKey (single source)
                if (widget && widget.config && widget.config.source_key === sourceKey) {
                    const dataset = chart.data.datasets[0];
                    if (dataset) {
                        // Add new data point
                        chart.data.labels.push(now);
                        dataset.data.push(parseFloat(value));
                        
                        // Keep only last 30 points
                        if (chart.data.labels.length > 30) {
                            chart.data.labels.shift();
                        }
                        if (dataset.data.length > 30) {
                            dataset.data.shift();
                        }
                        
                        chart.update('none');
                    }
                }
            });
        }
        
        // Expose to global scope for handleSensorMessage
        window.updateDependentCharts = updateDependentCharts;
        window.updateChart = updateChart;

        function updateChart(widgetKey, value) {
            const chart = chartInstances[widgetKey];
            if (!chart) return;

            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dataset = chart.data.datasets[0];
            
            if (dataset) {
                chart.data.labels.push(now);
                dataset.data.push(parseFloat(value));

                if (chart.data.labels.length > 30) {
                    chart.data.labels.shift();
                }
                if (dataset.data.length > 30) {
                    dataset.data.shift();
                }

                chart.update('none');
            }
        }

        // GridStack Initialization

        let grid;
        let autoSaveTimer = null;
        let isSaving = false;
        
        if (document.querySelector('.grid-stack')) {
            // Initialize GridStack
            grid = GridStack.init({
                cellHeight: 80,
                margin: 10,
                staticGrid: true, // Start locked
                animate: true,
                float: false,
                resizable: { handles: 'se', autoHide: true },
                disableOneColumnMode: false,
                minRow: 1,
                acceptWidgets: function(el) { return true; }, // Accept anything
            });

            // Explicitly setup drag from sidebar
            GridStack.setupDragIn('.toolbox-item', { 
                appendTo: 'body', 
                helper: 'clone',
                revert: 'invalid'
            });

            // Handle dropped widgets (Creation)
            grid.on('added', function(e, items) {
                items.forEach(function(item) {
                    // Check if it's a new widget from sidebar (doesn't have real DB ID yet)
                    const $el = $(item.el);
                    const $typeInput = $el.find('.widget-type-input');
                    
                    if (!item.id && $typeInput.length > 0) {
                        const widgetType = $typeInput.val();
                        
                        // Show loading state
                        $el.html('<div class="d-flex justify-content-center align-items-center h-100"><i class="fas fa-spinner fa-spin text-white"></i></div>');

                        // Create Widget via AJAX using the route helper (numeric device ID)
                        $.post('{{ route("widgets.store", $selectedDevice ?? 0) }}', {
                            _token: '{{ csrf_token() }}',
                            name: 'New ' + widgetType.charAt(0).toUpperCase() + widgetType.slice(1),
                            type: widgetType,
                            width: item.w,
                            height: item.h,
                            position_x: item.x,
                            position_y: item.y
                        })
                        .done(function(response) {
                            if(response.success) {
                                showNotification('Module created successfully', 'success');
                                
                                // UPDATE ID BEFORE SAVING
                                item.id = response.widget.key;
                                item.el.setAttribute('gs-id', response.widget.key);
                                
                                // UPDATE REGISTRY FOR EDIT MODAL
                                widgetRegistry[response.widget.key] = response.widget;
                                
                                saveGridLayout(true); // Now save with the correct ID
                                
                                // Replace loading spinner with actual widget HTML
                                const widgetContent = $(item.el).find('.grid-stack-item-content');
                                widgetContent.html('<div class="widget-wrapper">' + response.html + '</div>');
                                
                                // Initialize any plugins for the new widget (e.g., Charts)
                                // This is simplified; for charts we might need a separate init trigger
                                
                                // Initialize any plugins for the new widget (e.g., Charts)
                                // This is simplified; for charts we might need a separate init trigger
                            }
                        })
                        .fail(function(xhr) {
                            grid.removeWidget(item.el); // Remove on failure
                            const msg = xhr.responseJSON?.message || 'Failed to create widget';
                            showNotification(msg, 'error');
                        });
                    }
                });
            });
            
            // Auto-save on change (debounced) - Existing logic
            grid.on('change', function(event, items) {
                if (!editMode) return;
                
                // Clear previous timer
                clearTimeout(autoSaveTimer);
                
                // Show saving indicator
                showSaveStatus('saving');
                
                // Debounce: wait 2 seconds after last change
                autoSaveTimer = setTimeout(() => {
                    saveGridLayout(true); // true = auto-save (no reload)
                }, 2000);
            });
        }

        // Toggle Edit Mode
        let editMode = false;
        $('#toggleEditMode').on('click', function() {
            if (!grid) return;
            editMode = !editMode;
            grid.setStatic(!editMode);
            $('.grid-stack').toggleClass('edit-mode', editMode);
            $(this).toggleClass('btn-primary btn-outline-primary');
            $('#saveLayout').toggle(editMode);
            
            if (editMode) {
                $(this).html('<i class="fas fa-times mr-2"></i>Exit');
                showNotification('Layout editor enabled - drag widgets to rearrange', 'info');
                // Add visual cue
                $('.grid-stack').css('outline', '2px dashed rgba(16, 185, 129, 0.3)');
                
                // Show Sidebar & Adjust Grid
                $('#widgetSidebar').removeClass('d-none');
                $('#mainGridContainer').removeClass('col-12').addClass('col-lg-9 col-md-12');
                
                // Trigger GridStack resize after transition
                setTimeout(() => {
                    grid.onResize();
                }, 350);
                
            } else {
                $(this).html('<i class="fas fa-th-large mr-2"></i>Layout');
                // Remove visual cue
                $('.grid-stack').css('outline', 'none');
                
                // Hide Sidebar & Reset Grid
                $('#widgetSidebar').addClass('d-none');
                $('#mainGridContainer').removeClass('col-lg-9 col-md-12').addClass('col-12');
                
                // Trigger GridStack resize
                setTimeout(() => {
                    grid.onResize();
                }, 350);
                // Auto-save when exiting edit mode if there are pending changes
                if (autoSaveTimer) {
                    clearTimeout(autoSaveTimer);
                    saveGridLayout(false); // Save and reload
                }
            }
        });

        // Save Layout (Manual Save)
        $('#saveLayout').on('click', function() {
            saveGridLayout(false); // Manual save with reload
        });
        
        // Unified save function
        function saveGridLayout(isAutoSave = false) {
            if (!grid || isSaving) return;
            
            isSaving = true;
            const $btn = $('#saveLayout');
            const originalText = $btn.html();
            
            if (!isAutoSave) {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Storing...');
            }
            
            const positions = grid.save(false)
                .filter(item => !!item.id)   // Skip unsaved widgets (no DB key yet)
                .map(item => ({
                    key:  String(item.id),
                    x:    Math.round(item.x ?? 0),
                    y:    Math.round(item.y ?? 0),
                    w:    Math.round(item.w ?? 4),
                    h:    Math.round(item.h ?? 2)
                }));

            // Nothing to save (e.g. all widgets were freshly dropped, not yet in DB)
            if (positions.length === 0) {
                isSaving = false;
                if (!isAutoSave) {
                    $btn.prop('disabled', false).html(originalText);
                }
                console.warn('⚠️ saveGridLayout: no saveable positions found, skipping.');
                return;
            }

            $.post('{{ route("widgets.update-positions", $selectedDevice ?? 0) }}', {
                positions: positions,
                _token: '{{ csrf_token() }}'
            })
            .done(() => {
                if (isAutoSave) {
                    showSaveStatus('saved');
                    console.log('✅ Layout auto-saved');
                } else {
                    showNotification('Grid layout saved successfully', 'success');
                    setTimeout(() => location.reload(), 800);
                }
            })
            .fail((xhr) => {
                showSaveStatus('error');
                showNotification('Failed to save layout', 'error');
                console.error('❌ Save failed:', xhr);
            })
            .always(() => {
                isSaving = false;
                if (!isAutoSave) {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
        
        // Save status indicator
        function showSaveStatus(status) {
            let $indicator = $('#saveStatusIndicator');
            if (!$indicator.length) {
                $indicator = $('<div id="saveStatusIndicator" style="position: fixed; bottom: 20px; right: 20px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; z-index: 9999; transition: all 0.3s ease;"></div>');
                $('body').append($indicator);
            }
            
            $indicator.stop(true, true);
            
            switch(status) {
                case 'saving':
                    $indicator
                        .html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...')
                        .css({
                            background: 'rgba(59, 130, 246, 0.15)',
                            color: '#60a5fa',
                            border: '1px solid rgba(59, 130, 246, 0.3)',
                            display: 'block',
                            opacity: 1
                        });
                    break;
                case 'saved':
                    $indicator
                        .html('<i class="fas fa-check mr-2"></i>Saved')
                        .css({
                            background: 'rgba(16, 185, 129, 0.15)',
                            color: 'var(--primary-green-light)',
                            border: '1px solid rgba(16, 185, 129, 0.3)',
                            display: 'block',
                            opacity: 1
                        });
                    // Auto-hide after 2 seconds
                    setTimeout(() => {
                        $indicator.fadeOut(300);
                    }, 2000);
                    break;
                case 'error':
                    $indicator
                        .html('<i class="fas fa-exclamation-triangle mr-2"></i>Error')
                        .css({
                            background: 'rgba(239, 68, 68, 0.15)',
                            color: '#f87171',
                            border: '1px solid rgba(239, 68, 68, 0.3)',
                            display: 'block',
                            opacity: 1
                        });
                    setTimeout(() => {
                        $indicator.fadeOut(300);
                    }, 3000);
                    break;
            }
        }

        // MQTT Logic
        const teweMqttConfig = {
            host: '{{ config("mqtt.host") }}',
            port: {{ config("mqtt.websocket_port") }},
            path: '{{ config("mqtt.websocket_path") }}',
            protocol: '{{ config("mqtt.protocol") }}'
        };

        // [SECURITY FIX] deviceCode and userId are NOT embedded in HTML.
        // They are fetched securely from the AJAX endpoint (auth-gated) and set after the call below.
        let deviceCode = null;
        let userId     = null;
        // isOwner: set after AJAX resolves — true if logged-in user owns this device.
        // Shared users must NOT write back to DB from sensor updates to avoid race condition / echo loops.
        const loggedInUserId = {{ auth()->id() }};
        let isDeviceOwner = false;
        // Numeric device ID — safe to expose (no security value, requires auth for all operations)
        const currentDeviceId = {{ $selectedDevice->id ?? 'null' }};

        function initMqtt() {
            if (typeof window.mqttClient !== 'undefined') {
                console.log('🚀 Dashboard: Initializing MQTT Session...');
                
                // [SECURITY FIX C-1] Fetch MQTT credentials + device context via authenticated AJAX.
                // deviceCode, userId are NEVER embedded in HTML source.
                $.getJSON('{{ route("mqtt.credentials") }}')
                    .done(function(creds) {
                        // Set device context from secure AJAX response
                        deviceCode    = creds.device_code || '';
                        userId        = creds.owner_user_id || '';
                        isDeviceOwner = (creds.owner_user_id === loggedInUserId);

                        if (!deviceCode) {
                            console.warn('⚠️ No device context in credentials — MQTT topics unavailable.');
                            return;
                        }

                        console.log('📡 MQTT credentials loaded, connecting...');
                        
                        window.mqttClient.connect({
                            host: teweMqttConfig.host,
                            port: teweMqttConfig.port,
                            username: creds.username,
                            password: creds.password,
                            rejectUnauthorized: false
                        });

                        const client = window.mqttClient;

                        // Override/Add listeners to the global client
                        client.onConnect = () => {
                            console.log('✅ Connected to HiveMQ Cloud');

                            // Subscribe to sensor feedback (confirmed state from device)
                            const sensorTopic = `users/${userId}/devices/${deviceCode}/sensors/#`;
                            client.subscribe(sensorTopic, (topic, payload) => {
                                handleSensorMessage(topic, payload);
                            });

                            // Device connectivity status
                            client.subscribe(`users/${userId}/devices/${deviceCode}/status`, (topic, payload) => {
                                handleStatusMessage(payload);
                            });
                        };
                    })
                    .fail(function(xhr) {
                        console.error('❌ Failed to fetch MQTT credentials:', xhr.status);
                    });

                // NOTE: onMessage intentionally NOT set here to avoid double-firing.
                // The subscribe() callbacks above already handle all incoming messages.

            } else {
                console.log('⏳ Waiting for MQTT engine...');
                setTimeout(initMqtt, 200);
            }
        }

        if (true) { // Always attempt MQTT init; deviceCode will be set after creds AJAX resolves
            initMqtt();
        }

        function handleSensorMessage(topic, message) {
            const topicParts = topic.split('/');
            const topicSlug = topicParts[topicParts.length - 1]; // e.g. "temperature"
            
            let widgetKey = null;
            let type = null;

            // 1. Try case-insensitive ID match
            $('.grid-stack-item').each(function() {
                const key = $(this).attr('gs-id');
                if (key && key.toLowerCase() === topicSlug.toLowerCase()) {
                    widgetKey = key;
                    type = $(this).find('.widget-card-modern').attr('data-widget-type') || 'toggle'; 
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
                        type = $w.find('.widget-card-modern').attr('data-widget-type') || 'toggle';
                        return false;
                    }
                });
            }
            if (widgetKey) {
                updateWidgetUI(widgetKey, message, type);
                // ✅ Alert threshold check
                checkAlertThreshold(widgetKey, message);
                
                // ✅ UPDATE DATABASE SILENTLY 
                // Ensures that ESP sensor updates are saved to Laravel DB.
                // silent=1 prevents the server from re-publishing to MQTT.
                clearTimeout(dbSyncTimers[widgetKey]);
                // Only the device OWNER writes back to DB from incoming MQTT sensor data.
                // If a shared/slave user wrote back, it would re-trigger a server MQTT publish
                // causing the value to bounce back and forth (race condition).
                if (isDeviceOwner) {
                    dbSyncTimers[widgetKey] = setTimeout(() => {
                        if (!deviceCode) return;
                        $.post(`/widgets/${widgetKey}/value`, {
                            value: message,
                            silent: 1,
                            _token: '{{ csrf_token() }}'
                        });
                    }, 500);
                }

                // Also update any charts relying on this data
                if (window.updateDependentCharts) {
                    window.updateDependentCharts(widgetKey, message);
                }
            }
        }

        function handleControlMessage(topic, message) {
            handleSensorMessage(topic, message);
        }

        function handleStatusMessage(status) {
            const isOnline = status.toLowerCase() === 'online';
            const $dot = $('#device-status-dot');
            
            if (isOnline) {
                $dot.addClass('pulse-online').css('color', 'var(--primary-green-light)');
            } else {
                $dot.removeClass('pulse-online').css('color', 'var(--text-muted)');
            }
        }


        let isUpdatingUI = false;

        function updateWidgetUI(widgetKey, value, type) {
            const $widget = $(`.grid-stack-item[gs-id="${widgetKey}"]`);
            if (!$widget.length) return;

            isUpdatingUI = true;
            console.log(`Updating UI [${type}]:`, widgetKey, '=', value);

            switch (type) {
                case 'toggle':
                    const isChecked = value === '1' || value === 'true' || value === 'on';
                    $widget.find('.widget-toggle-checkbox').prop('checked', isChecked);
                    $widget.find('.status-text').text(isChecked ? 'ON' : 'OFF')
                           .toggleClass('status-on', isChecked)
                           .toggleClass('status-off', !isChecked);
                    break;
                case 'slider':
                    $widget.find('.widget-slider').val(value);
                    $widget.find('.widget-value-display').text(value);
                    const min_s = parseFloat($widget.find('.label-min').text()) || 0;
                    const max_s = parseFloat($widget.find('.label-max').text()) || 100;
                    const pct_s = ((value - min_s) / (max_s - min_s)) * 100;
                    $widget.find('.slider-progress').css('width', Math.max(0, Math.min(100, pct_s)) + '%');
                    break;
                case 'gauge':
                    $widget.find('.gauge-value').text(value);
                    const min_g = parseFloat($widget.find('.label-min').text()) || 0;
                    const max_g = parseFloat($widget.find('.label-max').text()) || 100;
                    if (window.updateGaugeModern) window.updateGaugeModern(widgetKey, value, min_g, max_g);
                    break;
                case 'text':
                    $widget.find('.value-large').text(value);
                    break;
                case 'chart':
                    updateChart(widgetKey, value);
                    break;
            }
            isUpdatingUI = false;
        }

        // ═══════════════════════════════════════════════════════
        // ALERT THRESHOLD — Gradient state: normal, warn, critical
        // ═══════════════════════════════════════════════════════
        const alertTriggeredWidgets = new Set();

        function checkAlertThreshold(widgetKey, value) {
            const $card = $(`[data-widget-key="${widgetKey}"]`);
            if (!$card.length) return;

            const alertEnabled = $card.attr('data-alert-enabled') === '1';
            if (!alertEnabled) return;

            const rawMin = $card.attr('data-alert-min');
            const rawMax = $card.attr('data-alert-max');
            const numVal  = parseFloat(value);

            // Skip non-numeric values (e.g. toggle strings)
            if (isNaN(numVal)) return;

            const hasMin = rawMin !== '' && rawMin !== undefined && !isNaN(parseFloat(rawMin));
            const hasMax = rawMax !== '' && rawMax !== undefined && !isNaN(parseFloat(rawMax));

            const minVal = hasMin ? parseFloat(rawMin) : null;
            const maxVal = hasMax ? parseFloat(rawMax) : null;

            // Warn zone = 20% of range (or 15% of single bound if only one side defined)
            let warnMargin = 0;
            if (hasMin && hasMax) {
                warnMargin = (maxVal - minVal) * 0.20;
            } else if (hasMax) {
                warnMargin = Math.abs(maxVal) * 0.15;
            } else if (hasMin) {
                warnMargin = Math.abs(minVal) * 0.15;
            }

            let state = 'normal';

            if ((hasMin && numVal <= minVal) || (hasMax && numVal >= maxVal)) {
                state = 'critical';
            } else if (hasMin && numVal < minVal + warnMargin) {
                state = 'warn';
            } else if (hasMax && numVal > maxVal - warnMargin) {
                state = 'warn';
            }

            const $badge = $(`#alert-badge-${widgetKey}`);

            $card.removeClass('widget-state-normal widget-state-warn widget-state-critical widget-alert-active');

            if (state === 'critical') {
                $card.addClass('widget-state-critical');
                if ($badge.length) {
                    $badge.show()
                          .attr('data-state', 'critical')
                          .html('<i class="fas fa-exclamation-triangle" style="font-size:0.7em;"></i> ALERT');
                }

                if (!alertTriggeredWidgets.has(widgetKey)) {
                    alertTriggeredWidgets.add(widgetKey);
                    const widgetName = $card.find('.widget-title span').first().text().trim();
                    const minTxt = hasMin ? ` Min: ${minVal}` : '';
                    const maxTxt = hasMax ? ` Max: ${maxVal}` : '';
                    Swal.fire({
                        icon: 'warning',
                        title: `⚠ Alert: ${widgetName}`,
                        html: `Nilai <strong>${numVal}</strong> di luar batas threshold!<br><small style="color:#94a3b8">${minTxt}${maxTxt}</small>`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        background: 'rgba(30,8,8,0.95)',
                        color: '#fca5a5'
                    });
                }
            } else if (state === 'warn') {
                $card.addClass('widget-state-warn');
                if ($badge.length) {
                    $badge.show()
                          .attr('data-state', 'warn')
                          .html('<i class="fas fa-exclamation-circle" style="font-size:0.7em;"></i> WARN');
                }
                alertTriggeredWidgets.delete(widgetKey);
            } else {
                $card.addClass('widget-state-normal');
                if ($badge.length) $badge.hide().removeAttr('data-state');
                alertTriggeredWidgets.delete(widgetKey);
            }

            // --- Update gauge arc color to match alert state ---
            const gaugeCircle = document.getElementById(`gauge-circle-${widgetKey}`);
            const gaugeDef    = document.getElementById(`gradient-${widgetKey}`);
            if (gaugeCircle && gaugeDef) {
                const stops = gaugeDef.querySelectorAll('stop');
                const colors = {
                    normal:   ['#10b981', '#34d399'],
                    warn:     ['#d97706', '#fbbf24'],
                    critical: ['#dc2626', '#f87171'],
                };
                const [c1, c2] = colors[state];
                if (stops[0]) stops[0].style.stopColor = c1;
                if (stops[1]) stops[1].style.stopColor = c2;
            }
        }

        // On page load: check existing widget values against thresholds
        document.addEventListener('DOMContentLoaded', function() {
            @foreach($widgets as $w)
                @if(!empty($w->config['alert_enabled']) && !empty($w->value))
                    checkAlertThreshold('{{ $w->key }}', '{{ $w->value }}');
                @endif
            @endforeach
        });



        const lastPublish = {};

        const dbSyncTimers = {};

        function updateWidgetValue(widgetKey, value, force = false) {
            const now = Date.now();
            if (!force && lastPublish[widgetKey] && (now - lastPublish[widgetKey] < 40)) {
                return;
            }

            const topic = `users/${userId}/devices/${deviceCode}/control/${widgetKey}`;

            if (window.mqttClient && window.mqttClient.isConnected()) {
                lastPublish[widgetKey] = now;
                console.log(`📡 Publishing to: ${topic} = ${value}`);
                
                // (1) IMMEDIATE MQTT PUBLISH (WebSocket)
                window.mqttClient.publish(topic, value.toString(), 0, false); 

                // (2) DEFERRED DATABASE SYNC via numeric device ID (no device_code in URL)
                clearTimeout(dbSyncTimers[widgetKey]);
                dbSyncTimers[widgetKey] = setTimeout(() => {
                    if (!currentDeviceId) return;
                    $.post(`/widgets/${widgetKey}/value`, {
                        value: value,
                        silent: 1,
                        _token: '{{ csrf_token() }}'
                    });
                }, 2000);
            } else {
                console.warn('⚠️ MQTT Disconnected! Falling back to DB update...');
                if (!currentDeviceId) return;
                $.post(`/devices/${currentDeviceId}/widgets/${widgetKey}/value`, {
                    value: value,
                    _token: '{{ csrf_token() }}'
                });
            }
        }

        // Notification Helper (Using SweetAlert2)
        function showNotification(message, type) {
            Swal.fire({
                icon: type === 'error' ? 'error' : 'success',
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: 'rgba(15, 21, 49, 0.9)',
                color: '#fff'
            });
        }

        // Event Handlers
        $(document).on('change', '.widget-toggle-checkbox', function() {
            if (isUpdatingUI) return;
            const $item = $(this).closest('.grid-stack-item');
            const widgetKey = $item.attr('gs-id');
            const val = $(this).is(':checked') ? '1' : '0';
            
            updateWidgetUI(widgetKey, val, 'toggle');
            updateWidgetValue(widgetKey, val, true); // force = true (No latency)
        });

        // Use 'input' for real-time slider updates in CMD
        $(document).on('input', '.widget-slider', function() {
            if (isUpdatingUI) return;
            const $item = $(this).closest('.grid-stack-item');
            const widgetKey = $item.attr('gs-id');
            const val = $(this).val();

            updateWidgetUI(widgetKey, val, 'slider');
            updateWidgetValue(widgetKey, val); // Throttled for speed
        });


        // Handle Add Widget Type Change
        $('#widgetType').on('change', function() {
            const type = $(this).val();
            if (type === 'slider' || type === 'gauge') {
                $('.range-field').slideDown(200);
            } else {
                $('.range-field').slideUp(200);
            }
        });

        // Handle Add Widget Form Submission
        $('#addWidgetForm').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $('#submitWidgetBtn');
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Booting Module...');

            const formData = new FormData(this);

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Module Initialized',
                        text: response.message || 'New interactive module initialized',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    $('#addWidgetModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                },
                error: (xhr) => {
                    console.error('❌ Boot failure:', xhr);
                    const errorMsg = xhr.responseJSON?.message || 'Protocol mismatch or node unreachable';
                    Swal.fire({
                        icon: 'error',
                        title: 'Initialization Failed',
                        text: errorMsg,
                        confirmButtonColor: '#ef4444'
                    });
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Widget Actions
        function deleteWidget(widgetKey) {
            Swal.fire({
                title: 'Purge Module?',
                text: "This will permanently remove the module and its sequential key mapping.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: 'rgba(255,255,255,0.05)',
                confirmButtonText: 'Yes, Purge',
                cancelButtonText: 'Abort',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/widgets/${widgetKey}`,
                        method: 'POST',
                        data: {
                            _method: 'DELETE',
                            _token: '{{ csrf_token() }}'
                        },
                        success: (response) => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Purged',
                                text: 'Module successfully purged from node',
                                timer: 1000,
                                showConfirmButton: false
                            });
                            setTimeout(() => location.reload(), 1000);
                        },
                        error: (xhr) => {
                            console.error('❌ Deletion failure:', xhr);
                            Swal.fire({
                                icon: 'error',
                                title: 'Purge Aborted',
                                text: 'Access denied or node busy',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    });
                }
            });
        }

        function editWidget(widgetKey) {
            const widget = widgetRegistry[widgetKey];
            if (!widget) {
                showNotification('Module identity lost. Please reload.', 'error');
                return;
            }

            // Set Form Action using numeric device ID (no device_code in URL)
            $('#editWidgetForm').attr('action', `/widgets/${widgetKey}`);

            // Populate Modal
            $('#editWidgetKey').val(widgetKey);
            $('#editWidgetName').val(widget.name);
            $('#editWidgetType').val(widget.type);
            $('#editWidgetMin').val(widget.min);
            $('#editWidgetMax').val(widget.max);
            $('#editWidgetWidth').val(widget.width || 4);
            $('#editWidgetHeight').val(widget.height || 2);
            $('#editWidgetUnit').val(widget.config?.unit || '');
            $('#editWidgetIcon').val(widget.config?.icon || 'cube');
            $('#editWidgetDescription').val(widget.config?.description || '');
            $('#editWidgetYAxisStep').val(widget.config?.y_axis_step || '');

            // Handle Chart Source (single select)
            const $sourceSelect = $('#editWidgetSource');
            $sourceSelect.empty();
            $sourceSelect.append(new Option('-- Select Variable --', '', false, false));
            
            // Populate sources (All widgets except self and charts)
            Object.values(widgetRegistry).forEach(w => {
                if (w.key !== widgetKey && w.type !== 'chart') {
                    const isSelected = widget.config?.source_key === w.key;
                    const label = `${w.name} (${w.key}) - [${w.type}]`;
                    $sourceSelect.append(new Option(label, w.key, false, isSelected));
                }
            });

            // Toggle Visibility based on Type
            toggleEditFields(widget.type);

            $('#editWidgetType').off('change').on('change', function() {
                toggleEditFields($(this).val());
            });

            function toggleEditFields(type) {
                if (type === 'gauge' || type === 'slider' || type === 'chart') {
                    $('.edit-range-field').show();
                } else {
                    $('.edit-range-field').hide();
                }

                if (type === 'chart') {
                    $('.chart-source-field').show();
                    $('.chart-y-axis-step-field').show();
                } else {
                    $('.chart-source-field').hide();
                    $('.chart-y-axis-step-field').hide();
                }
            }
            

            // Schedules
            $('#scheduleContainer').empty();
            const schedules = widget.config?.schedules || [];
            if (schedules.length > 0) {
                $('#noScheduleMsg').hide();
                schedules.forEach(s => addScheduleRow(s));
            } else {
                $('#noScheduleMsg').show();
            }

            // ── Alert Threshold ──────────────────────────────────
            const alertEnabled = widget.config?.alert_enabled == 1 || widget.config?.alert_enabled === true;
            $('#editAlertEnabled').prop('checked', alertEnabled);
            $('#editAlertMin').val(widget.config?.alert_min ?? '');
            $('#editAlertMax').val(widget.config?.alert_max ?? '');
            $('#alertThresholdFields').toggle(alertEnabled);

            $('#editWidgetModal').modal('show');
        }

        function addScheduleRow(data = null) {
            const container = $('#scheduleContainer');
            const noMsg = $('#noScheduleMsg');
            noMsg.hide();
            
            const widgetType = $('#editWidgetType').val();
            const index = container.children().length;
            
            let valueInput = '';
            if (widgetType === 'toggle') {
                const selectedOn = (data && (data.value === '1' || data.value === 1)) ? 'selected' : '';
                const selectedOff = (data && (data.value === '0' || data.value === 0)) ? 'selected' : '';
                valueInput = `
                    <select name="config[schedules][${index}][value]" class="form-control glass-input">
                        <option value="1" ${selectedOn}>ON</option>
                        <option value="0" ${selectedOff}>OFF</option>
                    </select>
                `;
            } else {
                valueInput = `
                    <input type="text" name="config[schedules][${index}][value]" 
                           class="form-control glass-input" 
                           value="${data ? data.value : '0'}" placeholder="Value">
                `;
            }
            
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            let dayCheckboxes = '';
            days.forEach((day, i) => {
                const checked = !data || (data.days && data.days.includes(i.toString())) ? 'checked' : '';
                dayCheckboxes += `
                    <div class="form-check form-check-inline m-0 mr-2">
                        <input class="form-check-input" type="checkbox" name="config[schedules][${index}][days][]" 
                               value="${i}" id="day-${index}-${i}" ${checked} style="width: 12px; height: 12px; margin-top: 5px;">
                        <label class="form-check-label text-xs ml-1" for="day-${index}-${i}" style="font-size: 10px; color: var(--text-muted); cursor: pointer;">${day}</label>
                    </div>
                `;
            });

            const row = $(`
                <div class="schedule-row p-3 rounded position-relative" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                    <button type="button" class="btn btn-sm text-danger position-absolute" style="top: 5px; right: 5px; z-index: 10;" onclick="$(this).closest('.schedule-row').remove(); if($('#scheduleContainer').children().length === 0) $('#noScheduleMsg').show();">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label class="text-xs text-muted d-block mb-1" style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Execution Time</label>
                            <input type="time" name="config[schedules][${index}][time]" class="form-control glass-input" value="${data ? data.time : '12:00'}" required>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label class="text-xs text-muted d-block mb-1" style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Trigger Action</label>
                            ${valueInput}
                        </div>
                        <div class="col-md-4 d-flex align-items-center mb-1">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config[schedules][${index}][enabled]" value="1" id="en-${index}" ${(!data || data.enabled == '1' || data.enabled === true) ? 'checked' : ''}>
                                <label class="custom-control-label text-xs" for="en-${index}" style="font-size: 11px; color: #60a5fa; cursor: pointer;">Enabled</label>
                            </div>
                            <button type="button" class="btn btn-xs ml-3" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light); border: 1px solid rgba(16, 185, 129, 0.2); font-size: 9px; padding: 2px 6px;" 
                                    onclick="const val = $(this).closest('.schedule-row').find('.glass-input').last().val(); updateWidgetValue($('#editWidgetKey').val(), val, true); showNotification('Manual trigger executed', 'success');">
                                <i class="fas fa-play mr-1"></i>Test
                            </button>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="d-flex flex-wrap gap-2">
                                ${dayCheckboxes}
                            </div>
                        </div>
                    </div>
                </div>
            `);
            container.append(row);
        }

        $('#editWidgetType').on('change', function() {
            const type = $(this).val();
            if (type === 'slider' || type === 'gauge') {
                $('.edit-range-field').slideDown(200);
            } else {
                $('.edit-range-field').slideUp(200);
            }
            // Clear and hide schedules if changed to chart/gauge maybe? 
            // Actually keep them, but refresh the action input types
            const currentSchedules = [];
            $('#scheduleContainer .schedule-row').each(function() {
                const row = $(this);
                currentSchedules.push({
                    time: row.find('input[type="time"]').val(),
                    value: row.find('.glass-input').last().val(),
                    enabled: row.find('.custom-control-input').is(':checked'),
                    days: row.find('input[type="checkbox"]:not(.custom-control-input):checked').map(function() { return $(this).val(); }).get()
                });
            });
            $('#scheduleContainer').empty();
            if (currentSchedules.length > 0) {
                currentSchedules.forEach(s => addScheduleRow(s));
            } else {
                $('#noScheduleMsg').show();
            }
        });

        // Handle Edit Form Submission
        $('#editWidgetForm').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $('#submitEditWidgetBtn');
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...');

            const formData = new FormData(this);
            formData.append('_method', 'PUT');

            // ── Fix duplicate hidden+checkbox values for alert_enabled ──────
            // FormData contains both hidden(0) AND checkbox(1) when checked.
            // We delete all entries for config[alert_enabled] and re-append
            // the correct single value so Laravel gets exactly one value.
            const alertChecked = document.getElementById('editAlertEnabled')?.checked;
            formData.delete('config[alert_enabled]');
            formData.append('config[alert_enabled]', alertChecked ? '1' : '0');

            // Debug: Log form data
            console.log('📝 Submitting widget update:');
            console.log('   - Action:', $form.attr('action'));
            console.log('   - alert_enabled:', alertChecked ? '1' : '0');
            for (let [key, value] of formData.entries()) {
                console.log(`   - ${key}: ${value}`);
            }

            const currentWidgetKey = $('#editWidgetKey').val();

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    // ── Update card data-alert-* attributes immediately ─────
                    // So blink stops/starts without waiting for full page reload
                    const $card = $(`[data-widget-key="${currentWidgetKey}"]`);
                    if ($card.length) {
                        const newAlertEnabled = alertChecked ? '1' : '0';
                        const newMin = $('#editAlertMin').val();
                        const newMax = $('#editAlertMax').val();

                        $card.attr('data-alert-enabled', newAlertEnabled);
                        $card.attr('data-alert-min', newMin);
                        $card.attr('data-alert-max', newMax);

                        // If alert disabled → immediately stop blink
                        if (!alertChecked) {
                            $card.removeClass('widget-alert-active');
                            $(`#alert-badge-${currentWidgetKey}`).hide();
                            alertTriggeredWidgets.delete(currentWidgetKey);
                        }
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Synced',
                        text: 'Module parameters updated and synced',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => location.reload(), 1500);
                },
                error: (xhr) => {
                    console.error('❌ Sync failure:', xhr);
                    Swal.fire({
                        icon: 'error',
                        title: 'Sync Interrupted',
                        text: 'Configuration mismatch or node busy',
                        confirmButtonColor: '#ef4444'
                    });
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        window.deleteWidget = deleteWidget;
        window.editWidget = editWidget;
        window.addScheduleRow = addScheduleRow;
        // LSTM Toggle Logic — uses numeric device ID (not device_code)
        function toggleLstm(deviceId) {
            const btn = document.getElementById('toggleLstmBtn');
            const icon = btn.querySelector('i');
            const text = document.getElementById('lstmBtnText');
            
            // Optimistic UI Update
            const wasEnabled = btn.classList.contains('btn-ai-active');
            const newState = !wasEnabled;
            
            // Visual loading state
            icon.className = 'fas fa-brain mr-2 fa-spin';
            btn.style.opacity = '0.7';
            
            $.post(`/device/lstm/toggle`, {
                _token: '{{ csrf_token() }}',
                enabled: newState ? 1 : 0
            })
            .done(function(response) {
                if(response.success) {
                    // Update UI based on server response
                    if(response.lstm_enabled) {
                        btn.classList.add('btn-ai-active');
                        btn.style.background = 'rgba(139, 92, 246, 0.2)';
                        btn.style.borderColor = 'rgba(139, 92, 246, 0.5)';
                        btn.style.color = '#a78bfa';
                        icon.className = 'fas fa-brain mr-2 fa-pulse';
                        text.innerText = 'AI Active';
                        
                        // Add glow effect if not present
                        if(!btn.querySelector('.ai-glow')) {
                            const glow = document.createElement('div');
                            glow.className = 'ai-glow';
                            btn.appendChild(glow);
                        }
                        
                        showNotification('AI Control Activated', 'success');
                    } else {
                        btn.classList.remove('btn-ai-active');
                        btn.style.background = 'rgba(255, 255, 255, 0.05)';
                        btn.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                        btn.style.color = 'var(--text-muted)';
                        icon.className = 'fas fa-brain mr-2';
                        text.innerText = 'AI Control';
                        
                        const glow = btn.querySelector('.ai-glow');
                        if(glow) glow.remove();
                        
                        showNotification('AI Control Deactivated', 'info');
                    }
                }
            })
            .fail(function(xhr) {
                console.error(xhr);
                showNotification('Failed to toggle AI Control', 'error');
                // Revert icon
                icon.className = wasEnabled ? 'fas fa-brain mr-2 fa-pulse' : 'fas fa-brain mr-2';
            })
            .always(function() {
                btn.style.opacity = '1';
            });
        }
        // Attach LSTM button click via event listener (not inline onclick)
        // Reads device ID from data-device-id attribute — only numeric ID, no device_code exposed
        const lstmBtn = document.getElementById('toggleLstmBtn');
        if (lstmBtn) {
            lstmBtn.addEventListener('click', function() {
                const deviceId = this.getAttribute('data-device-id');
                if (deviceId) toggleLstm(deviceId);
            });
        }
    </script>
@endpush