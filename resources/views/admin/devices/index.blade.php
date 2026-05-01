@extends('layouts.app')

@section('title', 'Global Hardware Inventory')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-server mr-2 text-primary"></i>Universal Hardware Governance
        </h1>
        <p class="text-muted small mb-0 mt-1">
            Manage all device nodes, approval, and telemetry log settings.
        </p>
    </div>
    @if($filteredUser)
        <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
            <div class="glass-card py-2 px-3 d-flex align-items-center"
                 style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2);">
                <i class="fas fa-user text-primary mr-2" style="font-size: 0.8rem;"></i>
                <span class="small text-white">Fleet: <strong>{{ $filteredUser->name }}</strong></span>
            </div>
            <a href="{{ route('admin.devices.index') }}" class="btn btn-sm glass-button"
               style="width: auto; padding: 6px 14px !important;">
                <i class="fas fa-times mr-1"></i> Clear Filter
            </a>
        </div>
    @endif
</div>

{{-- Flash message --}}
@if(session('success'))
    <div class="alert border-0 mb-4"
         style="background: rgba(16,185,129,0.12); color: #6ee7b7; border-radius: 10px; border-left: 3px solid #10b981 !important;">
        <i class="fas {{ session('approval_status') === 'revoked' ? 'fa-ban' : 'fa-check-circle' }} mr-2"></i>
        {{ session('success') }}
    </div>
@endif

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- FILTER + BULK CONTROLS PANEL                           --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<div class="glass-card p-4 mb-4">
    <div class="row align-items-end" style="gap: 0.5rem 0;">

        {{-- User / Owner Filter --}}
        <div class="col-md-4 mb-3 mb-md-0">
            <label class="glass-label text-xs mb-1">
                <i class="fas fa-user mr-1"></i> Filter by Owner
            </label>
            <form method="GET" action="{{ route('admin.devices.index') }}" id="filterForm">
                <select name="user_id" class="form-control glass-input"
                        onchange="document.getElementById('filterForm').submit()">
                    <option value="">— All Users —</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->email }})
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Bulk Set Log Interval (only visible when user is filtered) --}}
        @if($filteredUser)
        <div class="col-md-8">
            <div class="p-3 rounded" style="background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2);">
                <label class="glass-label text-xs mb-2">
                    <i class="fas fa-layer-group mr-1" style="color: #a78bfa;"></i>
                    Bulk Set Log Interval — ALL devices of <strong style="color: #c4b5fd;">{{ $filteredUser->name }}</strong>
                </label>
                <form action="{{ route('admin.devices.bulk-log-interval') }}" method="POST" class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;"
                      onsubmit="return confirm('Set log interval for ALL {{ $devices->total() }} device(s) of {{ $filteredUser->name }}?')">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $filteredUser->id }}">

                    <input type="number" name="log_interval" id="bulkInterval"
                           class="form-control glass-input" style="width: 100px;"
                           min="0" max="3600" placeholder="sec">

                    {{-- Quick preset chips --}}
                    <div class="d-flex flex-wrap" style="gap: 0.3rem;">
                        @foreach([['','Inherit'],['0','Every change'],['10','10s'],['30','30s'],['60','1min'],['300','5min'],['600','10min']] as [$v,$lbl])
                        <button type="button" class="bulk-preset-btn btn btn-sm"
                                data-value="{{ $v }}"
                                style="padding: 2px 9px; font-size: 0.72rem;
                                       background: rgba(255,255,255,0.07);
                                       border: 1px solid rgba(255,255,255,0.15);
                                       color: #ddd; border-radius: 6px; transition: all 0.2s;">
                            {{ $lbl }}
                        </button>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-sm glass-button glass-button-primary"
                            style="width: auto; padding: 5px 16px !important; font-size: 0.8rem;">
                        <i class="fas fa-bolt mr-1"></i> Apply to All
                    </button>
                </form>
            </div>
        </div>
        @else
        <div class="col-md-8 d-flex align-items-center">
            <div class="small text-muted">
                <i class="fas fa-info-circle mr-1"></i>
                Select an owner above to enable bulk log interval control for all their devices.
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- DEVICE TABLE                                           --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<div class="glass-card overflow-hidden">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">
            Global Fleet Overview
            <span class="badge ml-2" style="background: rgba(255,255,255,0.08); color: #94a3b8; font-size: 0.7rem; font-weight: 500;">
                {{ $devices->total() }} node(s)
            </span>
        </h5>
        <small class="text-muted">
            Log Interval Priority: <span style="color: #a78bfa;">Device Override</span>
            &rsaquo; <span style="color: #60a5fa;">User Default</span>
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="color: white;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <th class="border-0 px-4 py-3" style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Status</th>
                        <th class="border-0 py-3"       style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Hardware Node</th>
                        <th class="border-0 py-3"       style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Operator</th>
                        <th class="border-0 py-3"       style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Access Key</th>
                        <th class="border-0 py-3"       style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Log Interval</th>
                        <th class="border-0 py-3"       style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Approval</th>
                        <th class="border-0 py-3"       style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Last Seen</th>
                        <th class="border-0 px-4 py-3 text-right" style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);" id="row-{{ $device->id }}">

                        {{-- Online Status --}}
                        <td class="px-4 py-3 align-middle">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-circle mr-2 {{ $device->isOnline() ? 'pulse-online' : '' }}"
                                   style="font-size:0.55rem; color:{{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};"></i>
                                <span style="font-size:0.72rem; font-weight:600; color:{{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};">
                                    {{ strtoupper($device->status) }}
                                </span>
                            </div>
                        </td>

                        {{-- Device Name --}}
                        <td class="py-3 align-middle">
                            <div style="font-weight:600; font-size:0.9rem;">{{ $device->name }}</div>
                            <div class="small text-muted">ID #{{ $device->id }}</div>
                        </td>

                        {{-- Owner --}}
                        <td class="py-3 align-middle">
                            <a href="{{ route('admin.devices.index', ['user_id' => $device->user_id]) }}"
                               class="d-flex align-items-center text-decoration-none"
                               title="Filter by this user">
                                <div class="user-avatar mr-2" style="width:24px; height:24px; font-size:0.65rem;">
                                    {{ substr($device->user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="small text-white" style="font-weight:500;">{{ $device->user->name }}</div>
                                    <div class="small text-muted" style="font-size:0.7rem;">
                                        User interval:
                                        @php $ui = $device->user->getLogInterval(); @endphp
                                        {{ $ui === 0 ? 'Every change' : $ui . 's' }}
                                    </div>
                                </div>
                            </a>
                        </td>

                        {{-- Device Code --}}
                        <td class="py-3 align-middle">
                            <code style="background:rgba(255,255,255,0.05); color:var(--primary-green-light);
                                         padding:0.2rem 0.5rem; border-radius:4px; font-size:0.72rem;">
                                {{ $device->device_code }}
                            </code>
                        </td>

                        {{-- ═══ Log Interval Inline Editor ═══ --}}
                        <td class="py-3 align-middle" style="min-width: 220px;">
                            <form action="{{ route('admin.devices.update-log-interval', $device) }}"
                                  method="POST" class="log-interval-form">
                                @csrf
                                @method('PATCH')

                                {{-- Current effective label --}}
                                @php
                                    $hasOverride   = $device->hasLogIntervalOverride();
                                    $effectiveVal  = $device->getEffectiveLogInterval();
                                    $effectiveLabel = $effectiveVal === 0 ? 'Every change' : $effectiveVal . 's';
                                @endphp

                                <div class="mb-1">
                                    @if($hasOverride)
                                        <span class="badge" style="background:rgba(99,102,241,0.2);color:#a78bfa;border:1px solid rgba(99,102,241,0.35);font-size:0.68rem;border-radius:20px;">
                                            <i class="fas fa-microchip mr-1" style="font-size:0.6rem;"></i>Device: {{ $effectiveLabel }}
                                        </span>
                                    @else
                                        <span class="badge" style="background:rgba(59,130,246,0.12);color:#93c5fd;border:1px solid rgba(59,130,246,0.25);font-size:0.68rem;border-radius:20px;">
                                            <i class="fas fa-user mr-1" style="font-size:0.6rem;"></i>Inherited: {{ $effectiveLabel }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Input row --}}
                                <div class="d-flex align-items-center" style="gap:0.3rem;">
                                    <input type="number"
                                           name="log_interval"
                                           class="form-control glass-input device-interval-input"
                                           style="width:70px; padding:3px 7px; font-size:0.78rem; height:auto;"
                                           min="0" max="3600"
                                           value="{{ $hasOverride ? $device->log_interval : '' }}"
                                           placeholder="{{ $effectiveVal }}">
                                    <span class="text-muted small">s</span>
                                    <button type="submit" class="btn btn-sm"
                                            style="padding:2px 10px; font-size:0.72rem;
                                                   background:rgba(99,102,241,0.2); color:#a78bfa;
                                                   border:1px solid rgba(99,102,241,0.4); border-radius:6px;"
                                            title="Save device interval">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    {{-- Reset to user default --}}
                                    @if($hasOverride)
                                    <button type="button" class="btn btn-sm reset-interval-btn"
                                            data-device-id="{{ $device->id }}"
                                            style="padding:2px 8px; font-size:0.72rem;
                                                   background:rgba(239,68,68,0.1); color:#f87171;
                                                   border:1px solid rgba(239,68,68,0.25); border-radius:6px;"
                                            title="Reset to user default (remove override)">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    @endif
                                </div>

                                {{-- Preset chips --}}
                                <div class="d-flex flex-wrap mt-1" style="gap:0.25rem;">
                                    @foreach([['','↩ User'],['0','Every'],['10','10s'],['30','30s'],['60','1m'],['300','5m']] as [$v,$lbl])
                                    <button type="button"
                                            class="interval-preset-btn btn btn-sm"
                                            data-target="row-{{ $device->id }}"
                                            data-value="{{ $v }}"
                                            style="padding:1px 7px; font-size:0.65rem; line-height:1.6;
                                                   background:{{ (!$hasOverride && $v === '') || ($hasOverride && (string)$device->log_interval === $v) ? 'rgba(99,102,241,0.25)' : 'rgba(255,255,255,0.05)' }};
                                                   border:1px solid {{ (!$hasOverride && $v === '') || ($hasOverride && (string)$device->log_interval === $v) ? 'rgba(99,102,241,0.5)' : 'rgba(255,255,255,0.1)' }};
                                                   color:#ddd; border-radius:5px; transition:all 0.15s;">
                                        {{ $lbl }}
                                    </button>
                                    @endforeach
                                </div>
                            </form>
                        </td>

                        {{-- Approval Status --}}
                        <td class="py-3 align-middle">
                            @if($device->isApproved())
                                <div class="d-flex flex-column">
                                    <span class="badge" style="background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3);padding:3px 9px;border-radius:20px;font-size:0.68rem;width:fit-content;">
                                        <i class="fas fa-check-circle mr-1"></i>APPROVED
                                    </span>
                                    @if($device->approved_at)
                                    <span class="small mt-1" style="color:var(--text-muted);font-size:0.68rem;">
                                        by {{ $device->approvedBy?->name ?? 'Admin' }}
                                        &bull; {{ $device->approved_at->diffForHumans() }}
                                    </span>
                                    @endif
                                </div>
                            @else
                                <span class="badge" style="background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.3);padding:3px 9px;border-radius:20px;font-size:0.68rem;">
                                    <i class="fas fa-clock mr-1"></i>PENDING
                                </span>
                            @endif
                        </td>

                        {{-- Last Seen --}}
                        <td class="py-3 align-middle small text-muted">
                            {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 align-middle text-right">
                            <div class="d-flex justify-content-end align-items-center" style="gap:4px;">

                                {{-- View live dashboard --}}
                                <a href="{{ route('dashboard', ['device_id' => $device->id]) }}"
                                   class="btn btn-sm"
                                   style="background:rgba(59,130,246,0.1);color:#60a5fa;border:1px solid rgba(59,130,246,0.2);"
                                   title="View Live Dashboard">
                                    <i class="fas fa-eye"></i>
                                </a>

                                {{-- Toggle Approve / Revoke --}}
                                <form action="{{ route('admin.devices.toggle-approval', $device) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('{{ $device->isApproved() ? 'Revoke ' . $device->name . '?' : 'Approve ' . $device->name . '?' }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm"
                                            style="{{ $device->isApproved()
                                                ? 'background:rgba(251,191,36,0.1);color:#fbbf24;border:1px solid rgba(251,191,36,0.25);'
                                                : 'background:rgba(16,185,129,0.1);color:#6ee7b7;border:1px solid rgba(16,185,129,0.25);' }}"
                                            title="{{ $device->isApproved() ? 'Revoke Approval' : 'Approve Device' }}">
                                        <i class="fas {{ $device->isApproved() ? 'fa-ban' : 'fa-check' }}"></i>
                                    </button>
                                </form>

                                {{-- Delete --}}
                                <form action="{{ route('admin.devices.destroy', $device) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete [{{ $device->name }}] and all its widget data?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm"
                                            style="background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.2);"
                                            title="Delete Device">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fas fa-server mb-3 d-block" style="font-size:2.5rem;opacity:0.2;"></i>
                            No hardware nodes found{{ $filteredUser ? ' for ' . $filteredUser->name : '' }}.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $devices->links() }}
        </div>
    </div>
</div>

{{-- Hidden reset form (reused for all reset buttons) --}}
<form id="resetIntervalForm" action="" method="POST" style="display:none;">
    @csrf
    @method('PATCH')
    <input type="hidden" name="log_interval" value="">
</form>
@endsection

@push('styles')
<style>
    .pulse-online {
        animation: pulse-ring 2s infinite;
    }
    @keyframes pulse-ring {
        0%   { opacity: 1; text-shadow: 0 0 5px currentColor; }
        50%  { opacity: 0.4; text-shadow: none; }
        100% { opacity: 1; text-shadow: 0 0 5px currentColor; }
    }
    .glass-input:focus { box-shadow: 0 0 0 2px rgba(99,102,241,0.3); }
    .log-interval-form input[type=number]::-webkit-inner-spin-button { opacity: 0.4; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Bulk preset chips ──────────────────────────────
    const bulkInput = document.getElementById('bulkInterval');
    document.querySelectorAll('.bulk-preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            bulkInput.value = this.dataset.value;
            document.querySelectorAll('.bulk-preset-btn').forEach(b => {
                b.style.background   = 'rgba(255,255,255,0.07)';
                b.style.borderColor  = 'rgba(255,255,255,0.15)';
                b.style.color        = '#ddd';
            });
            this.style.background  = 'rgba(99,102,241,0.25)';
            this.style.borderColor = 'rgba(99,102,241,0.5)';
            this.style.color       = '#c4b5fd';
        });
    });

    // ── Per-device preset chips ────────────────────────
    document.querySelectorAll('.interval-preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const rowId = this.dataset.target;           // e.g. "row-5"
            const row   = document.getElementById(rowId);
            if (!row) return;

            const input = row.querySelector('.device-interval-input');
            if (input) input.value = this.dataset.value;

            // Highlight active chip in this row
            row.querySelectorAll('.interval-preset-btn').forEach(b => {
                b.style.background  = 'rgba(255,255,255,0.05)';
                b.style.borderColor = 'rgba(255,255,255,0.1)';
            });
            this.style.background  = 'rgba(99,102,241,0.25)';
            this.style.borderColor = 'rgba(99,102,241,0.5)';
        });
    });

    // ── Reset (remove device override) buttons ─────────
    const resetForm = document.getElementById('resetIntervalForm');
    document.querySelectorAll('.reset-interval-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const deviceId = this.dataset.deviceId;
            if (!confirm('Remove device override? It will inherit the user\'s log interval.')) return;

            // Build PATCH URL dynamically — we know the prefix from existing forms
            const existingForm = this.closest('form.log-interval-form');
            if (existingForm) {
                // Set value to empty → controller will treat as null
                const inp = existingForm.querySelector('input[name="log_interval"]');
                if (inp) inp.value = '';
                existingForm.submit();
            }
        });
    });

});
</script>
@endpush
