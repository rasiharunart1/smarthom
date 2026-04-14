@extends('layouts.app')

@section('title', 'Override User Account')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-user-edit mr-2 text-primary"></i>Override Identification: {{ $user->name }}
        </h1>
        <a href="{{ route('admin.users.index') }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to Directory
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Permission & Access</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Identified Name</label>
                                <input type="text" name="name" class="form-control glass-input" value="{{ $user->name }}" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Email Access</label>
                                <input type="email" name="email" class="form-control glass-input" value="{{ $user->email }}" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Access Level (Role)</label>
                                <select name="role" class="form-control glass-input">
                                    <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>USER (Standard)</option>
                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>ADMIN (Full Governance)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Subscription Protocol</label>
                                <select name="subscription_plan" class="form-control glass-input">
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->slug }}" {{ $user->subscription_plan === $plan->slug ? 'selected' : '' }}>
                                            {{ strtoupper($plan->name) }} ({{ $plan->max_devices }} Nodes)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="glass-label">Contract Expiration (Nulllable)</label>
                            <input type="datetime-local" name="subscription_expires_at" class="form-control glass-input" 
                                   value="{{ $user->subscription_expires_at ? $user->subscription_expires_at->format('Y-m-d\TH:i') : '' }}">
                        </div>

                        <div class="mb-4">
                             <label class="glass-label">Advanced Features</label>
                             <div class="custom-control custom-switch">
                                 <input type="checkbox" class="custom-control-input" id="lstmAllowed" name="lstm_allowed" value="1" {{ $user->lstm_allowed ? 'checked' : '' }}>
                                 <label class="custom-control-label text-white" for="lstmAllowed">
                                     Enable AI / LSTM Control Features
                                     <small class="d-block text-muted">Allows this user to access advanced predictive control features.</small>
                                 </label>
                             </div>
                        </div>

                        {{-- ====== TELEMETRY LOG CONTROL ====== --}}
                        <div class="mb-4 p-3 rounded" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                            <label class="glass-label mb-3">
                                <i class="fas fa-database mr-1" style="color: var(--primary-green-light);"></i>
                                Telemetry Log Control
                            </label>

                            {{-- Enable / Disable Logging --}}
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="logEnabled" name="log_enabled" value="1"
                                       {{ ($user->log_enabled ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label text-white" for="logEnabled">
                                    Enable Data Logging
                                    <small class="d-block text-muted">Record sensor telemetry from this user's devices into the database.</small>
                                </label>
                            </div>

                            {{-- Log Interval --}}
                            <div id="logIntervalGroup">
                                <label class="glass-label" for="logInterval">
                                    Log Interval
                                    <small class="text-muted ml-1">(seconds)</small>
                                </label>
                                <div class="input-group">
                                    <input type="number" id="logInterval" name="log_interval"
                                           class="form-control glass-input"
                                           min="0" max="3600" step="1"
                                           value="{{ $user->log_interval ?? 0 }}"
                                           placeholder="0">
                                    <div class="input-group-append">
                                        <span class="input-group-text" style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); color: #aaa;">sec</span>
                                    </div>
                                </div>
                                <small class="text-muted mt-1 d-block">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>0</strong> = log on every value change &nbsp;|&nbsp;
                                    <strong>60</strong> = max 1 log/minute per widget &nbsp;|&nbsp;
                                    max <strong>3600</strong> (1 hour)
                                </small>

                                {{-- Quick presets --}}
                                <div class="mt-2 d-flex flex-wrap" style="gap: 0.4rem;">
                                    @foreach([['0','Every change'],['10','10s'],['30','30s'],['60','1 min'],['300','5 min'],['600','10 min'],['3600','1 hour']] as [$val,$label])
                                    <button type="button" class="btn btn-sm log-preset-btn"
                                            data-value="{{ $val }}"
                                            style="padding: 2px 10px; font-size: 0.75rem;
                                                   background: {{ ($user->log_interval ?? 0) == $val ? 'rgba(0,200,120,0.25)' : 'rgba(255,255,255,0.07)' }};
                                                   border: 1px solid {{ ($user->log_interval ?? 0) == $val ? 'rgba(0,200,120,0.6)' : 'rgba(255,255,255,0.15)' }};
                                                   color: #ddd; border-radius: 6px; transition: all 0.2s;">
                                        {{ $label }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                            <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                                Apply Global Override
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card text-center p-4 h-100">
                <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h4 class="text-white">{{ $user->name }}</h4>
                <p class="text-muted mb-4">{{ $user->email }}</p>
                
                <div class="text-left border-top pt-3" style="border-color: rgba(255,255,255,0.1) !important;">
                    <div class="glass-label">Current Configuration</div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Managed Items:</span>
                        <span class="text-white small font-weight-bold">{{ $user->devices_count ?? $user->devices->count() }} / {{ $user->getLimit('max_devices') }} Nodes</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Module Capacity:</span>
                        <span class="text-white small font-weight-bold">{{ $user->getLimit('max_widgets_per_device') }} per Node</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Account Creation:</span>
                        <span class="text-white small">{{ $user->created_at->format('M d, Y') }}</span>
                    </div>

                    {{-- Log Status Summary --}}
                    <div class="border-top mt-2 pt-2" style="border-color: rgba(255,255,255,0.08) !important;">
                        <div class="glass-label mb-2">Log Status</div>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Data Logging:</span>
                            @if($user->isLogEnabled())
                                <span class="badge" style="background:rgba(0,200,120,0.2);color:#00c878;border:1px solid rgba(0,200,120,0.4);font-size:0.7rem;">
                                    <i class="fas fa-circle mr-1" style="font-size:0.5rem;"></i>ACTIVE
                                </span>
                            @else
                                <span class="badge" style="background:rgba(255,80,80,0.15);color:#ff6060;border:1px solid rgba(255,80,80,0.3);font-size:0.7rem;">
                                    <i class="fas fa-circle mr-1" style="font-size:0.5rem;"></i>DISABLED
                                </span>
                            @endif
                        </div>
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted small">Log Interval:</span>
                            <span class="text-white small font-weight-bold">
                                @if(($user->log_interval ?? 0) == 0)
                                    Every change
                                @elseif($user->log_interval < 60)
                                    {{ $user->log_interval }}s
                                @elseif($user->log_interval < 3600)
                                    {{ round($user->log_interval / 60, 1) }} min
                                @else
                                    {{ round($user->log_interval / 3600, 1) }} hr
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---- Preset buttons for log interval ----
    const intervalInput = document.getElementById('logInterval');
    const presetBtns   = document.querySelectorAll('.log-preset-btn');

    function updatePresets(activeVal) {
        presetBtns.forEach(btn => {
            const isActive = String(btn.dataset.value) === String(activeVal);
            btn.style.background = isActive ? 'rgba(0,200,120,0.25)' : 'rgba(255,255,255,0.07)';
            btn.style.borderColor = isActive ? 'rgba(0,200,120,0.6)' : 'rgba(255,255,255,0.15)';
        });
    }

    presetBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            intervalInput.value = this.dataset.value;
            updatePresets(this.dataset.value);
        });
    });

    // Sync active state when user types manually
    intervalInput.addEventListener('input', function () {
        updatePresets(this.value);
    });

    // ---- Dim log interval section when logging disabled ----
    const logToggle       = document.getElementById('logEnabled');
    const logIntervalGroup = document.getElementById('logIntervalGroup');

    function syncLogToggle() {
        if (logToggle.checked) {
            logIntervalGroup.style.opacity = '1';
            logIntervalGroup.style.pointerEvents = 'auto';
        } else {
            logIntervalGroup.style.opacity = '0.35';
            logIntervalGroup.style.pointerEvents = 'none';
        }
    }

    logToggle.addEventListener('change', syncLogToggle);
    syncLogToggle(); // run on page load
});
</script>
@endpush
