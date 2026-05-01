@extends('layouts.app')

@section('title', 'Data Logs - '. $device->name)

@section('content')
<div class="row">
    <div class="col-12 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0 text-white font-weight-bold">
                    <i class="fas fa-list-alt mr-2"></i>Data Logs
                </h1>
                <p class="text-muted small mb-0">{{ $device->name }} ({{ $device->device_code }})</p>
            </div>
            <div>
                <a href="{{ route('dashboard') }}" class="btn glass-button btn-secondary mr-2">
                    <i class="fas fa-arrow-left mr-2"></i>Dashboard
                </a>
                <button type="button" class="btn glass-button btn-primary mr-2" data-toggle="modal" data-target="#addLogModal">
                    <i class="fas fa-plus mr-2"></i>Add Log
                </button>
                <form action="{{ route('logs.clear', $device->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Clear logs{{ request()->filled('start_date') ? ' from ' . request('start_date') . ' to ' . request('end_date') : ' for ALL time' }}?\n\nThis will also delete aggregated chart data (5min / hourly / daily) for the same period. This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                    <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                    <button type="submit" class="btn glass-button btn-danger mr-2"
                            style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);">
                        <i class="fas fa-trash mr-2"></i>
                        {{ request()->filled('start_date') ? 'Clear Filtered Logs' : 'Clear ALL Logs' }}
                    </button>
                </form>
                <button type="button" class="btn glass-button btn-success" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);" data-toggle="modal" data-target="#exportModal">
                    <i class="fas fa-file-csv mr-2"></i>Export CSV
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="col-12 mb-4">
        <div class="glass-card p-4">
            <form method="GET" action="{{ route('logs.index', $device->id) }}">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label class="glass-label text-xs">Start Date</label>
                        <input type="date" name="start_date" class="form-control glass-input" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label class="glass-label text-xs">End Date</label>
                        <input type="date" name="end_date" class="form-control glass-input" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label class="glass-label text-xs">Variable (Widget)</label>
                        <select name="widget_key" class="form-control glass-input">
                            <option value="all">All Variables</option>
                            @foreach($widgets as $key => $name)
                                <option value="{{ $key }}" {{ request('widget_key') == $key ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn glass-button btn-primary w-100">
                            <i class="fas fa-filter mr-2"></i>Filter Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Chart Visualization -->
    <div class="col-12 mb-4">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0 font-weight-bold">
                    <i class="fas fa-chart-line mr-2"></i>Visualization
                </h5>
                @if(request('widget_key') && request('widget_key') != 'all')
                <div class="d-flex align-items-center">
                    <!-- Resolution Selector -->
                    <select id="chartResolution" class="form-control glass-input mr-3 form-control-sm" style="width: auto;">
                        <option value="">Auto Resolution</option>
                        <option value="raw">Raw (Realtime)</option>
                        <option value="5min">5 Minutes</option>
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily</option>
                    </select>

                    <!-- Realtime Toggle -->
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="realtimeToggle">
                        <label class="custom-control-label text-white small" for="realtimeToggle">Live Updates</label>
                    </div>
                </div>
                @endif
            </div>
            
            @if(request('widget_key') && request('widget_key') != 'all')
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="logsChart"></canvas>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-chart-area mb-3 d-block" style="font-size: 3rem; opacity: 0.2;"></i>
                    <p>Select a specific variable to view the visualization chart.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Data Table -->
    <div class="col-12">
        <div class="glass-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover text-white mb-0" style="background: transparent;">
                    <thead>
                        <tr style="background: rgba(255, 255, 255, 0.05);">
                            <th class="py-3 px-4 border-bottom border-light-10">Timestamp</th>
                            <th class="py-3 px-4 border-bottom border-light-10">Widget Name</th>
                            <th class="py-3 px-4 border-bottom border-light-10">Variable / Key</th>
                            <th class="py-3 px-4 border-bottom border-light-10">Value</th>
                            <th class="py-3 px-4 border-bottom border-light-10">Event Type</th>
                            <th class="py-3 px-4 border-bottom border-light-10">Publish By</th>
                            <th class="py-3 px-4 border-bottom border-light-10 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="py-3 px-4 border-bottom border-light-5">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                    <small class="d-block text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="py-3 px-4 border-bottom border-light-5">
                                    <span class="font-weight-bold text-white">
                                        {{ $widgetNames[$log->widget_key] ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 border-bottom border-light-5">
                                    <span class="badge badge-primary px-3 py-2" style="background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3);">
                                        {{ $log->widget_key ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 border-bottom border-light-5 font-weight-bold" style="font-family: monospace; font-size: 1.1em; color: #34d399;">
                                    {{ $log->new_value }}
                                </td>
                                <td class="py-3 px-4 border-bottom border-light-5 text-uppercase text-xs tracking-wider opacity-7">
                                    {{ $log->event_type }}
                                </td>
                                <td class="py-3 px-4 border-bottom border-light-5">
                                    <span class="badge" style="background: rgba(255,255,255,0.1); color: #e2e8f0;">
                                        {{ $log->source ?? 'System' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 border-bottom border-light-5 text-right">
                                    <form action="{{ route('logs.destroy', ['device' => $device->id, 'log' => $log->id]) }}" method="POST" onsubmit="return confirm('Delete this log entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fas fa-search mb-3 d-block" style="font-size: 2rem; opacity: 0.3;"></i>
                                    No logs found matching criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="p-4 border-top border-light-10">
                {{ $logs->withQueryString()->links() }} <!-- Uses bootstrap-4 pagination by default -->
            </div>
        </div>
    </div>
</div>

<!-- Add Log Modal -->
<div class="modal fade" id="addLogModal" tabindex="-1" role="dialog" aria-labelledby="addLogModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header border-bottom border-light-10">
                <h5 class="modal-title text-white" id="addLogModalLabel">Add Manual Log Entry</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('logs.store', $device->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="glass-label">Variable (Widget)</label>
                        <select name="widget_key" class="form-control glass-input" required>
                            @foreach($widgets as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                            <option value="custom">-- Custom / Other --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="glass-label">Value</label>
                        <input type="text" name="value" class="form-control glass-input" placeholder="e.g. 25.5 or ON" required>
                    </div>
                    <div class="form-group">
                        <label class="glass-label">Event Type</label>
                        <input type="text" name="event_type" class="form-control glass-input" value="MANUAL">
                    </div>
                </div>
                <div class="modal-footer border-top border-light-10">
                    <button type="button" class="btn glass-button btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn glass-button btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export CSV Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header border-bottom border-light-10">
                <h5 class="modal-title text-white" id="exportModalLabel">
                    <i class="fas fa-file-csv mr-2"></i>Export CSV Options
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('logs.export', $device->id) }}" method="GET">
                @if(request('start_date'))
                    <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                @endif
                @if(request('end_date'))
                    <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                @endif
                
                <div class="modal-body">
                    <!-- Widget Selection -->
                    <div class="form-group">
                        <label class="glass-label">
                            <i class="fas fa-columns mr-2"></i>Select Variables to Export
                        </label>
                        <div class="border rounded p-3" style="background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1) !important; max-height: 250px; overflow-y: auto;">
                            <!-- Select All -->
                            <div class="custom-control custom-checkbox mb-2 pb-2 border-bottom" style="border-color: rgba(255,255,255,0.1) !important;">
                                <input type="checkbox" class="custom-control-input" id="selectAllWidgets" checked>
                                <label class="custom-control-label text-white font-weight-bold" for="selectAllWidgets">
                                    <i class="fas fa-check-double mr-1"></i>Select All
                                </label>
                            </div>
                            
                            @foreach($widgets as $key => $name)
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input widget-checkbox" id="widget_{{ $key }}" name="widgets[]" value="{{ $key }}" checked>
                                    <label class="custom-control-label text-white" for="widget_{{ $key }}">
                                        {{ $name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Data Cleaning -->
                    <div class="form-group mb-0 mt-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="cleanupData" name="cleanup_data" value="1">
                            <label class="custom-control-label text-white" for="cleanupData">
                                <strong><i class="fas fa-broom mr-1"></i>Enable Data Cleaning</strong>
                                <small class="d-block text-muted mt-1">
                                    Remove rows with any missing widget values
                                </small>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-light-10">
                    <button type="button" class="btn glass-button btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn glass-button btn-success" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);">
                        <i class="fas fa-download mr-2"></i>Download CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllWidgets');
    const checkboxes = document.querySelectorAll('.widget-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = !allChecked && Array.from(checkboxes).some(c => c.checked);
            });
        });
    }
});
</script>

<!-- Extra Styles for Pagination in Glass Mode -->
<style>
   .page-link {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: white !important;
    }
   .page-item.active.page-link {
        background: rgba(59, 130, 246, 0.4) !important;
        border-color: rgba(59, 130, 246, 0.5) !important;
    }
   .page-item.disabled.page-link {
        opacity: 0.5;
        background: transparent !important;
    }
</style>

@if(request('widget_key') && request('widget_key') != 'all')
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-zoom/2.0.1/chartjs-plugin-zoom.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Logs Chart: Initializing...');
        const canvas = document.getElementById('logsChart');
        if (!canvas) {
            console.error('Logs Chart: Canvas element not found!');
            return;
        }

        const ctx = canvas.getContext('2d');
        const widgetKey = "{{ request('widget_key') }}";
        const startDate = "{{ request('start_date') }}";
        const endDate = "{{ request('end_date') }}";
        const deviceCode = "{{ $device->device_code }}";

        let chartInstance = null;
        let refreshInterval = null;

        const resolutionSelect = document.getElementById('chartResolution');
        const realtimeToggle = document.getElementById('realtimeToggle');

        // Main Fetch Function
        function fetchAndRenderChart() {
            const resolution = resolutionSelect ? resolutionSelect.value : '';
            const isRealtime = realtimeToggle ? realtimeToggle.checked : false;

            // Prepare params
            const params = new URLSearchParams({
                keys: widgetKey,
                start_date: startDate,
                end_date: endDate
            });

            if (resolution) {
                params.append('resolution', resolution);
            }
            
            // Realtime Override
            if (isRealtime) {
                 params.delete('start_date');
                 params.delete('end_date');
                 params.append('period', '1h'); // Default to 1h window for live view
                 if (!resolution) params.append('resolution', 'raw');
            } else if (startDate && endDate) {
                params.append('period', 'custom');
            } else {
                params.append('period', '24h');
            }

            console.log(`Logs Chart: Fetching data [Res: ${resolution}, Live: ${isRealtime}]`);

            fetch(`{{ route('devices.history', $device->id) }}?${params.toString()}`)
               .then(response => response.json())
               .then(data => {
                    if (!data.success) {
                        console.error('Logs Chart: API Fetch Failed', data);
                        return;
                    }

                    if (Array.isArray(data.data) && data.data.length === 0) {
                         console.warn('Logs Chart: No data available.');
                         return; 
                    }

                    // Key check
                    const points = data.data[widgetKey];
                    if (!points || points.length === 0) {
                        console.warn('Logs Chart: Empty points array for key:', widgetKey);
                        return;
                    }

                    const labels = points.map(p => {
                        const date = new Date(p.timestamp);
                        return date.toLocaleString();
                    });
                    const values = points.map(p => parseFloat(p.value));

                    if (chartInstance) {
                        chartInstance.data.labels = labels;
                        chartInstance.data.datasets[0].data = values;
                        chartInstance.update('none'); // Update without full animation for performance
                    } else {
                        chartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: widgetKey,
                                    data: values,
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: values.length > 50 ? 0 : 3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: {
                                    duration: isRealtime ? 0 : 1000 
                                },
                                plugins: {
                                    legend: { labels: { color: 'white' } },
                                    zoom: {
                                        pan: {
                                            enabled: true,
                                            mode: 'x', // Pan only on X axis
                                            modifierKey: null,
                                        },
                                        zoom: {
                                            wheel: {
                                                enabled: true,
                                            },
                                            pinch: {
                                                enabled: true
                                            },
                                            mode: 'x', // Zoom only on X axis
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        display: false,
                                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                                    },
                                    y: {
                                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                        ticks: { color: 'rgba(255, 255, 255, 0.5)' }
                                    }
                                },
                                interaction: {
                                    intersect: false,
                                    mode: 'index',
                                },
                            }
                        });
                    }
                })
               .catch(err => console.error('Logs Chart load error:', err));
        }

        // Event Listeners
        if (resolutionSelect) {
            resolutionSelect.addEventListener('change', fetchAndRenderChart);
        }

        if (realtimeToggle) {
            realtimeToggle.addEventListener('change', function() {
                if (this.checked) {
                    if (resolutionSelect && resolutionSelect.value === '') {
                        resolutionSelect.value = 'raw'; // Default to Raw for live
                    }
                    
                    fetchAndRenderChart(); // Immediate
                    refreshInterval = setInterval(fetchAndRenderChart, 5000); // 5s loop
                } else {
                    if (refreshInterval) clearInterval(refreshInterval);
                }
                fetchAndRenderChart(); // Update view state
            });
        }

        // Initial Load
        fetchAndRenderChart();
    });
</script>
@endpush
@endif
@endsection
