@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card glass-card border-0">
                <div class="card-header border-0 bg-transparent pt-4 pb-0">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-edit mr-2 text-primary-light"></i>Edit Schedule
                    </h5>
                    <p class="text-muted small mt-1 mb-0">{{ $widgetsData[$widgetKey]['name'] }} on {{ $device->name }}</p>
                </div>
                <div class="card-body">
                    <form action="{{ route('schedules.update', ['schedule' => 'ref']) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <input type="hidden" name="device_id" value="{{ $device->id }}">
                        <input type="hidden" name="widget_key" value="{{ $widgetKey }}">
                        <input type="hidden" name="schedule_index" value="{{ $index }}">

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Execution Time</label>
                                <input type="time" class="form-control glass-input" name="time" required value="{{ $schedule['time'] }}">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Target Value</label>
                                @if($widgetsData[$widgetKey]['type'] === 'toggle')
                                    <select class="form-control glass-input" name="value">
                                        <option value="1" {{ $schedule['value'] == '1' ? 'selected' : '' }}>ON</option>
                                        <option value="0" {{ $schedule['value'] == '0' ? 'selected' : '' }}>OFF</option>
                                    </select>
                                @else
                                    <input type="text" class="form-control glass-input" name="value" value="{{ $schedule['value'] }}" required>
                                @endif
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="glass-label d-block mb-3">Repeats On</label>
                            <div class="d-flex flex-wrap gap-3">
                                @php $days = $schedule['days'] ?? []; @endphp
                                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $day)
                                    <div class="custom-control custom-checkbox custom-control-inline mr-3">
                                        <input type="checkbox" class="custom-control-input" id="day-{{ $i }}" name="days[]" value="{{ $i }}" 
                                            {{ in_array((string)$i, $days) ? 'checked' : '' }}>
                                        <label class="custom-control-label text-white" for="day-{{ $i }}">{{ $day }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enabledSwitch" name="enabled" {{ ($schedule['enabled'] ?? false) ? 'checked' : '' }}>
                                <label class="custom-control-label text-white" for="enabledSwitch">Enable this schedule</label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3">
                            <a href="{{ route('schedules.index') }}" class="btn glass-button btn-secondary mr-3">Cancel</a>
                            <button type="submit" class="btn glass-button glass-button-primary px-4">Update Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #editWidgetForm select option, select.glass-input option {
        background: #0a0e27;
        color: white;
    }
</style>
@endsection
