<div class="card h-100 widget-card-modern border-0" 
     data-widget-key="{{ $widget->key }}"
     data-widget-type="{{ $widget->type }}"
     data-widget-width="{{ $widget->width ??  4 }}"
     data-widget-height="{{ $widget->height ??  2 }}">
    
    <div class="card-header-modern">
        <div class="d-flex justify-content-between align-items-center">
            <div class="widget-title">
                <i class="fas fa-{{ $widget->config['icon'] ?? 'cube' }} widget-icon"></i>
                <span>{{ $widget->name }}</span>
            </div>
            <div class="widget-actions">
                <button class="btn-widget-action" onclick="editWidget('{{ $widget->key }}')" title="Edit Widget">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="btn-widget-action btn-delete" onclick="deleteWidget('{{ $widget->key }}')" title="Delete Widget">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body-modern">
        @switch($widget->type)
            @case('toggle')
                <div class="widget-content-center">
                    <div class="toggle-modern-wrapper">
                        <input type="checkbox" 
                               id="toggle-{{ $widget->key }}"
                               class="toggle-modern-input widget-toggle-checkbox" 
                               {{ $widget->value == '1' ? 'checked' : '' }}
                               data-widget-key="{{ $widget->key }}">
                        <label for="toggle-{{ $widget->key }}" class="toggle-modern-label">
                            <span class="toggle-modern-button"></span>
                        </label>
                    </div>
                    <div class="widget-status-modern mt-2">
                        <span class="status-text {{ $widget->value == '1' ? 'status-on' : 'status-off' }}">
                            {{ $widget->value == '1' ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                    @if(config('app.debug'))
                        <div class="mqtt-topic-info mt-2">
                            <small><i class="fas fa-broadcast-tower"></i> {{ $widget->key }}</small>
                        </div>
                    @endif
                </div>
            @break

            @case('slider')
                <div class="widget-content-center">
                    <div class="slider-value-display mb-3">
                        <span class="value-large widget-value-display">{{ $widget->value }}</span>
                        @if(isset($widget->config['unit']) && $widget->config['unit'])
                            <span class="value-unit">{{ $widget->config['unit'] }}</span>
                        @endif
                    </div>
                    <div class="slider-modern-container">
                        <div class="slider-track">
                            @php
                                $min = $widget->min ??  0;
                                $max = $widget->max ?? 100;
                                $value = $widget->value;
                                $percentage = $max > $min ? (($value - $min) / ($max - $min)) * 100 : 0;
                                $percentage = min(100, max(0, $percentage));
                            @endphp
                            <div class="slider-progress" style="width: {{ $percentage }}%"></div>
                        </div>
                        <input type="range" 
                               class="slider-modern widget-slider" 
                               min="{{ $min }}" 
                               max="{{ $max }}" 
                               value="{{ $value }}"
                               data-widget-key="{{ $widget->key }}">
                    </div>
                    <div class="slider-labels d-flex justify-content-between mt-2" 
                         style="font-size: 11px; color: var(--text-muted);">
                        <span class="label-min">{{ $min }}</span>
                        <span class="label-max">{{ $max }}</span>
                    </div>
                    @if(config('app.debug'))
                        <div class="mqtt-topic-info mt-2">
                            <small><i class="fas fa-broadcast-tower"></i> {{ $widget->key }}</small>
                        </div>
                    @endif
                </div>
            @break

            @case('gauge')
                <div class="widget-content-center">
                    <div class="gauge-modern-container">
                        <svg class="gauge-svg" viewBox="0 0 200 200" id="gauge-svg-{{ $widget->key }}">
                            <circle class="gauge-bg" cx="100" cy="100" r="80" 
                                    fill="none" stroke="rgba(255, 255, 255, 0.1)" stroke-width="12" stroke-linecap="round" />
                            <circle class="gauge-progress" cx="100" cy="100" r="80" 
                                    fill="none" stroke="url(#gradient-{{ $widget->key }})" 
                                    stroke-width="12" stroke-linecap="round"
                                    stroke-dasharray="502.65" 
                                    stroke-dashoffset="502.65" 
                                    transform="rotate(-90 100 100)"
                                    id="gauge-circle-{{ $widget->key }}" />
                            <defs>
                                <linearGradient id="gradient-{{ $widget->key }}" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#10b981;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#34d399;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="gauge-value-overlay">
                            <span class="gauge-value widget-value-display">{{ $widget->value }}</span>
                            @if(isset($widget->config['unit']) && $widget->config['unit'])
                                <span class="gauge-unit">{{ $widget->config['unit'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="gauge-labels d-flex justify-content-between mt-2" 
                         style="font-size: 11px; color: var(--text-muted);">
                        <span class="label-min">{{ $widget->min ??  0 }}</span>
                        <span class="label-max">{{ $widget->max ?? 100 }}</span>
                    </div>
                    @if(config('app.debug'))
                        <div class="mqtt-topic-info mt-2">
                            <small><i class="fas fa-broadcast-tower"></i> {{ $widget->key }}</small>
                        </div>
                    @endif
                </div>
            @break

            @case('text')
                <div class="widget-content-center">
                    <div class="text-widget-icon mb-3">
                        <i class="fas fa-{{ $widget->config['icon'] ??  'info-circle' }}"></i>
                    </div>
                    <div class="text-value-display">
                        <span class="value-large widget-value-display">{{ $widget->value }}</span>
                        @if(isset($widget->config['unit']) && $widget->config['unit'])
                            <span class="value-unit">{{ $widget->config['unit'] }}</span>
                        @endif
                    </div>
                    @if(config('app.debug'))
                        <div class="mqtt-topic-info mt-2">
                            <small><i class="fas fa-broadcast-tower"></i> {{ $widget->key }}</small>
                        </div>
                    @endif
                </div>
            @break

            @case('chart')
                <div class="widget-content-center" style="width: 100%; height: 100%; padding: 1rem;">
                    <canvas id="chart-{{ $widget->key }}" style="max-width: 100%; max-height: 200px;"></canvas>
                </div>
                @if(config('app.debug'))
                    <div class="mqtt-topic-info mt-2">
                        <small><i class="fas fa-broadcast-tower"></i> {{ $widget->key }}</small>
                    </div>
                @endif
            @break


            @default
                <div class="widget-content-center">
                    <div style="color: var(--text-muted); text-align: center;">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3" style="opacity: 0.3;"></i>
                        <p>Unknown widget type: <strong>{{ $widget->type }}</strong></p>
                    </div>
                </div>
        @endswitch
    </div>

    <!--<div class="card-footer-modern">-->
    <!--    <div class="footer-content">-->
    <!--        <div class="d-flex align-items-center gap-2">-->
    <!--            <span class="widget-type-badge">{{ $widget->type }}</span>-->
    <!--            @if(config('app.debug'))-->
    <!--                <span class="text-xs opacity-50">{{ Str::limit($widget->key, 8) }}</span>-->
    <!--            @endif-->
    <!--        </div>-->
    <!--        <div class="update-time">-->
    <!--            <i class="far fa-clock"></i>-->
    <!--            <span class="time-text">Just now</span>-->
    <!--        </div>-->
    <!--    </div>-->
    <!--</div>-->
</div>

<style>
    /* Widget size badge */
    .widget-size-badge {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 9px;
        border: 1px solid rgba(59, 130, 246, 0.3);
        font-family: monospace;
    }
</style>