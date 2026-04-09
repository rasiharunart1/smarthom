{{-- Telemetry Log Chart Modal (Gold/Enterprise users only) --}}
{{-- Include in dashboard.blade.php via @include('partials.telemetry-log-modal') --}}

<div class="modal fade" id="telemetryModal" tabindex="-1" role="dialog" aria-labelledby="telemetryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content glass-modal-content" style="border-radius:16px;">

            <div class="modal-header" style="border-bottom: 1px solid #2a3044; padding: 1.25rem 1.5rem;">
                <div class="d-flex align-items-center gap-3" style="gap:.75rem; display:flex; align-items:center;">
                    <div style="width:36px;height:36px;background:linear-gradient(135deg,#10b981,#059669);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-chart-area" style="color:white;font-size:.9rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="telemetryModalLabel" style="font-weight:700;color:#fff;">Telemetry Logs</h5>
                        <small style="color:#718096;">Aggregated sensor history</small>
                    </div>
                </div>
                <div class="d-flex align-items-center" style="display:flex;align-items:center;gap:.75rem;margin-left:auto;">
                    {{-- Widget selector --}}
                    <select id="telemetryWidgetSelect" class="form-control form-control-sm"
                        style="background:#1e2436;border:1px solid #2a3044;color:#fff;border-radius:8px;min-width:140px;font-size:.85rem;">
                        @foreach($widgets ?? [] as $widget)
                            @if(in_array($widget->type ?? '', ['gauge','slider','text']))
                                <option value="{{ $widget->key ?? '' }}">{{ $widget->name ?? $widget->key }}</option>
                            @endif
                        @endforeach
                    </select>

                    {{-- Resolution selector --}}
                    <select id="telemetryResolution" class="form-control form-control-sm"
                        style="background:#1e2436;border:1px solid #2a3044;color:#fff;border-radius:8px;font-size:.85rem;">
                        <option value="5min">5 Menit (24j)</option>
                        <option value="1h">1 Jam (7h)</option>
                        <option value="1d">1 Hari (90h)</option>
                    </select>

                    <button type="button" class="btn btn-sm" id="telemetryRefreshBtn"
                        style="background:#1e2436;border:1px solid #2a3044;color:#a0aec0;border-radius:8px;">
                        <i class="fas fa-sync-alt"></i>
                    </button>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                        style="color:#a0aec0;background:none;border:none;font-size:1.4rem;line-height:1;cursor:pointer;">&times;</button>
                </div>
            </div>

            <div class="modal-body" style="padding:1.25rem 1.5rem;">
                {{-- Chart container --}}
                <div id="telemetryChart" style="width:100%;height:360px;border-radius:10px;overflow:hidden;background:#161b27;"></div>

                {{-- Stats row --}}
                <div class="row mt-3" id="telemetryStats" style="display:none;">
                    <div class="col-3">
                        <div style="background:#1a1f2e;border:1px solid #2a3044;border-radius:10px;padding:.75rem 1rem;text-align:center;">
                            <div style="font-size:.7rem;color:#718096;text-transform:uppercase;letter-spacing:1px;">Min</div>
                            <div id="statMin" style="font-size:1.3rem;font-weight:700;color:#f87171;">—</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="background:#1a1f2e;border:1px solid #2a3044;border-radius:10px;padding:.75rem 1rem;text-align:center;">
                            <div style="font-size:.7rem;color:#718096;text-transform:uppercase;letter-spacing:1px;">Max</div>
                            <div id="statMax" style="font-size:1.3rem;font-weight:700;color:#34d399;">—</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="background:#1a1f2e;border:1px solid #2a3044;border-radius:10px;padding:.75rem 1rem;text-align:center;">
                            <div style="font-size:.7rem;color:#718096;text-transform:uppercase;letter-spacing:1px;">Rata-rata</div>
                            <div id="statAvg" style="font-size:1.3rem;font-weight:700;color:#60a5fa;">—</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="background:#1a1f2e;border:1px solid #2a3044;border-radius:10px;padding:.75rem 1rem;text-align:center;">
                            <div style="font-size:.7rem;color:#718096;text-transform:uppercase;letter-spacing:1px;">Data Points</div>
                            <div id="statCount" style="font-size:1.3rem;font-weight:700;color:#a78bfa;">—</div>
                        </div>
                    </div>
                </div>

                {{-- Loading & empty states --}}
                <div id="telemetryLoading" class="text-center py-5" style="display:none;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#10b981;"></i>
                    <p style="color:#718096;margin-top:.75rem;font-size:.9rem;">Loading telemetry data...</p>
                </div>
                <div id="telemetryEmpty" class="text-center py-5" style="display:none;">
                    <i class="fas fa-database" style="font-size:2rem;color:#4a5568;"></i>
                    <p style="color:#718096;margin-top:.75rem;font-size:.9rem;">No data available for this period.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>
<script>
(function() {
    const deviceCode = '{{ $selectedDevice->device_code ?? "" }}';
    const deviceId   = '{{ $selectedDevice->id ?? "" }}';

    let chart = null;
    let candleSeries = null;
    let lineSeries   = null;
    let autoRefreshTimer = null;

    function initChart() {
        if (chart) { chart.remove(); }

        chart = LightweightCharts.createChart(document.getElementById('telemetryChart'), {
            width:  document.getElementById('telemetryChart').clientWidth,
            height: 360,
            layout: {
                background: { type: 'solid', color: '#161b27' },
                textColor:  '#a0aec0',
            },
            grid: {
                vertLines:  { color: '#1e2436' },
                horzLines:  { color: '#1e2436' },
            },
            crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
            rightPriceScale: { borderColor: '#2a3044' },
            timeScale: {
                borderColor:     '#2a3044',
                timeVisible:     true,
                secondsVisible:  false,
            },
        });

        candleSeries = chart.addCandlestickSeries({
            upColor:        '#34d399',
            downColor:      '#f87171',
            borderVisible:  false,
            wickUpColor:    '#34d399',
            wickDownColor:  '#f87171',
        });

        // Avg line overlay
        lineSeries = chart.addLineSeries({
            color:       'rgba(96, 165, 250, 0.6)',
            lineWidth:   1,
            lineStyle:   LightweightCharts.LineStyle.Dashed,
            crosshairMarkerVisible: false,
        });
    }

    function loadTelemetry() {
        const widgetKey  = $('#telemetryWidgetSelect').val();
        const resolution = $('#telemetryResolution').val();

        if (!deviceId || !widgetKey) return;

        $('#telemetryChart').hide();
        $('#telemetryStats').hide();
        $('#telemetryEmpty').hide();
        $('#telemetryLoading').show();

        $.getJSON(`/devices/${deviceId}/telemetry/${widgetKey}?resolution=${resolution}`)
        .done(function(res) {
            $('#telemetryLoading').hide();

            if (!res.data || res.data.length === 0) {
                $('#telemetryEmpty').show();
                return;
            }

            initChart();

            const candles = res.data.map(d => ({
                time:  d.time,
                open:  d.open,
                high:  d.high,
                low:   d.low,
                close: d.close,
            }));
            const lines = res.data.map(d => ({ time: d.time, value: d.avg }));

            candleSeries.setData(candles);
            lineSeries.setData(lines);
            chart.timeScale().fitContent();

            $('#telemetryChart').show();

            // Stats
            const allLow  = res.data.map(d => d.low);
            const allHigh = res.data.map(d => d.high);
            const allAvg  = res.data.map(d => d.avg);
            const avg     = (allAvg.reduce((a, b) => a + b, 0) / allAvg.length).toFixed(2);

            $('#statMin').text(Math.min(...allLow).toFixed(2));
            $('#statMax').text(Math.max(...allHigh).toFixed(2));
            $('#statAvg').text(avg);
            $('#statCount').text(res.count);
            $('#telemetryStats').show();
        })
        .fail(function(xhr) {
            $('#telemetryLoading').hide();
            if (xhr.status === 403 && xhr.responseJSON?.error === 'upgrade_required') {
                $('#telemetryChart').html(
                    '<div class="text-center py-5"><i class="fas fa-lock" style="font-size:2rem;color:#f59e0b;"></i>' +
                    '<p style="color:#a0aec0;margin-top:.75rem;">Fitur ini tersedia untuk plan <strong style="color:#f59e0b;">Gold</strong> ke atas.</p></div>'
                ).show();
            } else {
                $('#telemetryEmpty').show();
            }
        });
    }

    // Open modal → load data
    $('#telemetryModal').on('shown.bs.modal', function() {
        loadTelemetry();
        autoRefreshTimer = setInterval(loadTelemetry, 30000);
    });
    $('#telemetryModal').on('hidden.bs.modal', function() {
        clearInterval(autoRefreshTimer);
    });

    // Controls
    $('#telemetryWidgetSelect, #telemetryResolution').on('change', loadTelemetry);
    $('#telemetryRefreshBtn').on('click', loadTelemetry);

    // Resize chart when window resizes
    $(window).on('resize', function() {
        if (chart) {
            chart.applyOptions({ width: document.getElementById('telemetryChart').clientWidth });
        }
    });
})();
</script>
@endpush
