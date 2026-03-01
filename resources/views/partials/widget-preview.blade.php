<div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #4e73df !important; border-radius: 8px;">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-{{ $widget->config['icon'] ?? 'cube' }} me-2"></i>
                {{ $widget->name }}
            </h6>
            <span class="badge bg-{{ $widget->config['color'] ?? 'info' }}">
                {{ ucfirst($widget->type) }}
            </span>
        </div>

        @switch($widget->type)
            @case('toggle')
                <div class="text-center py-2">
                    <i class="fas fa-toggle-{{ $widget->value == '1' ? 'on text-success' : 'off text-secondary' }} fa-3x"></i>
                    <p class="mb-0 mt-2 fw-bold">{{ $widget->value == '1' ? 'ON' : 'OFF' }}</p>
                </div>
            @break

            @case('slider')
                <div class="mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">{{ $widget->min }}</span>
                        <h4 class="mb-0 text-primary fw-bold">{{ $widget->value }}{{ $widget->config['unit'] ?? '' }}</h4>
                        <span class="text-muted small">{{ $widget->max }}</span>
                    </div>
                    <div class="progress" style="height: 8px; border-radius: 10px;">
                        <div class="progress-bar bg-primary" role="progressbar"
                            style="width: {{ ($widget->value / $widget->max) * 100 }}%; border-radius: 10px;">
                        </div>
                    </div>
                </div>
            @break

            @case('gauge')
            @case('text')
                <div class="text-center py-2">
                    <h2 class="text-primary fw-bold mb-0">{{ $widget->value }}</h2>
                    <small class="text-muted">{{ $widget->config['unit'] ?? '' }}</small>
                </div>
            @break

            @case('chart')
                <div class="text-center py-2">
                    <i class="fas fa-chart-line fa-3x text-info"></i>
                    <p class="mb-0 mt-2 small text-muted">Historical Data</p>
                </div>
            @break
        @endswitch

        <small class="text-muted d-block mt-2 text-center">
            <i class="fas fa-clock"></i> {{ $widget->updated_at->diffForHumans() }}
        </small>
    </div>
</div>
