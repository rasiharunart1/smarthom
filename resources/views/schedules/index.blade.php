@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0 text-gray-800 text-white">
                <i class="fas fa-calendar-alt mr-2 text-primary-light"></i>Schedule Management
            </h1>
            <a href="{{ route('schedules.create') }}" class="btn glass-button glass-button-primary">
                <i class="fas fa-plus mr-2"></i>New Schedule
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 text-white" style="background: rgba(16, 185, 129, 0.2); backdrop-filter: blur(10px);">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 text-white" style="background: rgba(239, 68, 68, 0.2); backdrop-filter: blur(10px);">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card glass-card border-0">
                <div class="card-body p-0">
                    @if($schedules->isEmpty())
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-calendar-times fa-4x text-muted opacity-25"></i>
                            </div>
                            <h5 class="text-white">No Schedules Found</h5>
                            <p class="text-muted">Create your first automation rule to get started.</p>
                            <a href="{{ route('schedules.create') }}" class="btn glass-button mt-3">
                                Create Schedule
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-borderless text-white mb-0 align-middle">
                                <thead style="background: rgba(255,255,255,0.05);">
                                    <tr>
                                        <th class="pl-4">Device / Widget</th>
                                        <th>Schedule</th>
                                        <th>Action</th>
                                        <th>Status</th>
                                        <th class="text-right pr-4">Manage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schedules as $schedule)
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <td class="pl-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-circle mr-3" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-{{ $schedule->widget_type == 'toggle' ? 'toggle-on' : ($schedule->widget_type == 'slider' ? 'sliders-h' : 'cube') }} text-primary-light"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold">{{ $schedule->widget_name }}</div>
                                                        <small class="text-muted">{{ $schedule->device_name }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="font-weight-bold text-lg">{{ \Carbon\Carbon::parse($schedule->time)->format('H:i') }}</span>
                                                    <small class="text-muted">
                                                        @if(empty($schedule->days))
                                                            <span class="text-warning">One-time</span>
                                                        @elseif(count($schedule->days) == 7)
                                                            <span class="text-success">Every Day</span>
                                                        @else
                                                            @foreach($schedule->days as $day)
                                                                {{ substr(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$day], 0, 3) }}@if(!$loop->last), @endif
                                                            @endforeach
                                                        @endif
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: rgba(255,255,255,0.1); font-weight: 500; font-size: 0.9em;">
                                                    SET TO: <strong>{{ $schedule->value }}</strong>
                                                </span>
                                            </td>
                                            <td>
                                                @if($schedule->enabled)
                                                    <span class="badge badge-success-soft">Active</span>
                                                @else
                                                    <span class="badge badge-secondary-soft">Disabled</span>
                                                @endif
                                            </td>
                                            <td class="text-right pr-4">
                                                <a href="{{ route('schedules.edit', ['schedule' => 'ref', 'device_id' => $schedule->device_id, 'widget_key' => $schedule->widget_key, 'index' => $schedule->index]) }}" 
                                                   class="btn btn-icon btn-sm glass-button mr-1" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <form action="{{ route('schedules.destroy', ['schedule' => 'ref', 'device_id' => $schedule->device_id, 'widget_key' => $schedule->widget_key, 'index' => $schedule->index]) }}" 
                                                      method="POST" class="d-inline-block"
                                                      onsubmit="return confirm('Delete this schedule?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-icon btn-sm glass-button text-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .badge-success-soft {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    .badge-secondary-soft {
        background: rgba(107, 114, 128, 0.2);
        color: #9ca3af;
        border: 1px solid rgba(107, 114, 128, 0.2);
    }
    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
</style>
@endsection
