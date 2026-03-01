@extends('layouts.app')

@section('title', 'Admin Governance')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-shield-alt mr-2 text-primary"></i>System Governance
        </h1>
        <div class="text-muted small">
            Live metrics from the global telemetry network
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="glass-card p-3 h-100 border-left-primary">
                <div class="d-flex align-items-center">
                    <div class="user-avatar mr-3" style="background: rgba(59, 130, 246, 0.1); color: #60a5fa;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="glass-label mb-0" style="font-size: 0.7rem;">Total Registered</div>
                        <div class="h4 font-weight-bold mb-0 text-white" id="stat-total-users">{{ $stats['total_users'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="glass-card p-3 h-100" style="border-left: 4px solid var(--primary-green) !important;">
                <div class="d-flex align-items-center">
                    <div class="user-avatar mr-3" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light);">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div>
                        <div class="glass-label mb-0" style="font-size: 0.7rem;">Active Subscriptions</div>
                        <div class="h4 font-weight-bold mb-0 text-white" id="stat-total-subs">{{ $stats['pro_users'] + $stats['enterprise_users'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="glass-card p-3 h-100" style="border-left: 4px solid #fbbf24 !important;">
                <div class="d-flex align-items-center">
                    <div class="user-avatar mr-3" style="background: rgba(251, 191, 36, 0.1); color: #fbbf24;">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div>
                        <div class="glass-label mb-0" style="font-size: 0.7rem;">Provisioned Nodes</div>
                        <div class="h4 font-weight-bold mb-0 text-white" id="stat-total-devices">{{ $stats['total_devices'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="glass-card p-3 h-100" style="border-left: 4px solid #f87171 !important;">
                <div class="d-flex align-items-center">
                    <div class="user-avatar mr-3" style="background: rgba(248, 113, 113, 0.1); color: #f87171;">
                        <i class="fas fa-signal"></i>
                    </div>
                    <div>
                        <div class="glass-label mb-0" style="font-size: 0.7rem;">Live Connectivity</div>
                        <div class="h4 font-weight-bold mb-0 text-white" id="stat-online-devices">{{ $stats['online_devices'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Intelligence Summary -->
        <div class="col-lg-8 mb-4">
            <div class="glass-card overflow-hidden h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">System Intelligence Overview</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-sm glass-button" style="width: auto;">
                            <i class="fas fa-users mr-1"></i> Users
                        </a>
                        <a href="{{ route('admin.devices.index') }}" class="btn btn-sm glass-button" style="width: auto;">
                            <i class="fas fa-microchip mr-1"></i> Hardware
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">Real-time oversight of the universal IoT grid. Drill down into specific operators to manage their telemetry nodes and interactive modules.</p>
                    
                    <div class="row mt-2">
                        <div class="col-md-6 mb-4">
                            <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.1);">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="user-avatar mr-3" style="width: 35px; height: 35px; background: rgba(59, 130, 246, 0.1); color: #60a5fa;">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div class="font-weight-bold" style="color: #60a5fa;">User Fleet Discovery</div>
                                </div>
                                <p class="small text-muted mb-3">Navigate the global user directory to identify active operators and manage their subscription levels.</p>
                                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-block" style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2);">
                                    Access Directory <i class="fas fa-arrow-right ml-1" style="font-size: 0.7rem;"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="p-3 rounded" style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.1);">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="user-avatar mr-3" style="width: 35px; height: 35px; background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light);">
                                        <i class="fas fa-server"></i>
                                    </div>
                                    <div class="font-weight-bold" style="color: var(--primary-green-light);">Unified Node Oversight</div>
                                </div>
                                <p class="small text-muted mb-3">Universal monitoring of all provisioned hardware nodes. Check sync status and purge inactive units.</p>
                                <a href="{{ route('admin.devices.index') }}" class="btn btn-sm btn-block" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light); border: 1px solid rgba(16, 185, 129, 0.2);">
                                    Monitor Inventory <i class="fas fa-arrow-right ml-1" style="font-size: 0.7rem;"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 p-3 rounded d-flex align-items-center justify-content-between" style="background: rgba(255,191,36,0.05); border: 1px solid rgba(255,191,36,0.1);">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle text-warning mr-3"></i>
                            <span class="small text-white">To inspect a live dashboard, select a user from the <strong>Directory</strong> or find the node in <strong>Inventory</strong>.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Controls -->
        <div class="col-lg-4 mb-4">
            <div class="glass-card p-4">
                <h5 class="font-weight-bold mb-4" style="color: var(--primary-green-light);">Governance Links</h5>
                
                <div class="list-group list-group-flush" style="background: transparent;">
                    <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action bg-transparent border-0 px-0 d-flex align-items-center mb-2" style="color: white; transition: 0.3s;">
                        <div class="user-avatar mr-3" style="width: 35px; height: 35px; background: rgba(59, 130, 246, 0.1); color: #60a5fa;">
                            <i class="fas fa-user-friends" style="font-size: 0.9rem;"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold" style="font-size: 0.9rem;">User Directory</div>
                            <div class="small text-muted">CRUD user accounts & levels</div>
                        </div>
                        <i class="fas fa-chevron-right ml-auto small text-muted"></i>
                    </a>

                    <a href="{{ route('admin.devices.index') }}" class="list-group-item list-group-item-action bg-transparent border-0 px-0 d-flex align-items-center mb-2" style="color: white; transition: 0.3s;">
                        <div class="user-avatar mr-3" style="width: 35px; height: 35px; background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light);">
                            <i class="fas fa-server" style="font-size: 0.9rem;"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold" style="font-size: 0.9rem;">Hardware Inventory</div>
                            <div class="small text-muted">Monitor global IoT nodes</div>
                        </div>
                        <i class="fas fa-chevron-right ml-auto small text-muted"></i>
                    </a>

                    <div class="mt-4 p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                        <div class="small font-weight-bold mb-2 text-warning">System Status</div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small text-muted">Server Core:</span>
                            <span class="small text-success">Optimized</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small text-muted">MQTT Bridge:</span>
                            <span class="small text-success">Balanced</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="small text-muted">DB Latency:</span>
                            <span class="small text-white">4ms</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
    </style>
@endpush

@push('scripts')
    {{-- MQTT.js --}}
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Admin Governance Live System: Initializing...');
            
            const mqttConfig = {
                host: '{{ config("mqtt.host") }}',
                port: 8884,
                path: '/mqtt',
                protocol: 'wss',
                username: {!! json_encode(config("mqtt.username")) !!},
                password: {!! json_encode(config("mqtt.password")) !!}
            };

            if (!window.mqtt) {
                console.error('❌ MQTT.js library not loaded!');
                return;
            }

            console.log('📡 Connecting to MQTT Bridge:', mqttConfig.host);

            const client = window.mqtt.connect(`wss://${mqttConfig.host}:${mqttConfig.port}${mqttConfig.path}`, {
                clientId: 'admin_dash_' + Math.random().toString(16).substr(2, 8),
                username: mqttConfig.username,
                password: mqttConfig.password,
                keepalive: 60,
                clean: true,
                rejectUnauthorized: false
            });

            client.on('connect', () => {
                console.log('✅ Admin Connected: Global Oversight Active');
                client.subscribe('users/+/devices/+/status');
                client.subscribe('users/+/devices/+/sensors/#');
            });

            client.on('message', (topic, message) => {
                console.log('📥 Telemetry:', topic, message.toString());
                const payload = message.toString();
                const parts = topic.split('/');
                if (parts.length < 5) return;
                
                const deviceCode = parts[3];
                const type = parts[4]; 
                
                const $row = $(`tr[data-device-code]`).filter(function() {
                    return $(this).data('device-code').toLowerCase() === deviceCode.toLowerCase();
                });
                
                if ($row.length) {
                    $row.find('.last-seen-text').text('Just now').css('color', 'var(--primary-green-light)');
                    
                    if (type === 'status') {
                        const isOnline = payload.toLowerCase() === 'online';
                        const $indicator = $row.find('.device-status-indicator');
                        
                        if (isOnline) {
                            if (!$indicator.hasClass('pulse-online')) {
                                const current = parseInt($('#stat-online-devices').text()) || 0;
                                $('#stat-online-devices').text(current + 1);
                            }
                            $indicator.addClass('pulse-online').css('color', 'var(--primary-green-light)');
                        } else {
                            if ($indicator.hasClass('pulse-online')) {
                                const current = parseInt($('#stat-online-devices').text()) || 0;
                                $('#stat-online-devices').text(Math.max(0, current - 1));
                            }
                            $indicator.removeClass('pulse-online').css('color', 'var(--text-muted)');
                            $row.find('.last-seen-text').text('Disconnected').css('color', 'var(--text-muted)');
                        }
                    }
                }
            });

            client.on('error', (err) => console.error('❌ MQTT Admin Error:', err));
            client.on('offline', () => console.warn('⚠️ MQTT Admin Offline'));
        });

        // Global Stats fallback
        setInterval(() => {
            $.get('{{ route("admin.stats") }}', data => {
                $('#stat-total-users').text(data.total_users);
                $('#stat-total-subs').text(data.total_subscriptions);
                $('#stat-total-devices').text(data.total_devices);
                $('#stat-online-devices').text(data.online_devices);
            });
        }, 60000);
    </script>
@endpush
@endsection
