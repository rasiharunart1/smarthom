@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card glass-card border-0">
                <div class="card-header border-0 bg-transparent pt-4 pb-0">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-clock mr-2 text-primary-light"></i>Create New Schedule
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('schedules.store') }}" method="POST">
                        @csrf
                        
                        <div class="form-group mb-4">
                            <label class="glass-label">Select Device</label>
                            <select class="form-control glass-input" name="device_id" id="deviceSelect" required>
                                <option value="">Choose Device...</option>
                                @foreach($devicesWithWidgets as $device)
                                    <option value="{{ $device['id'] }}" data-widgets="{{ json_encode($device['widgets']) }}">
                                        {{ $device['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <label class="glass-label">Select Widget</label>
                            <select class="form-control glass-input" name="widget_key" id="widgetSelect" required disabled>
                                <option value="">Select Device First...</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Execution Time</label>
                                <input type="time" class="form-control glass-input" name="time" required value="{{ now()->format('H:i') }}">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Target Value</label>
                                <div id="valueInputContainer">
                                    <input type="text" class="form-control glass-input" name="value" placeholder="Enter value (e.g. 1)" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="glass-label d-block mb-3">Repeats On</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $index => $day)
                                    <div class="custom-control custom-checkbox custom-control-inline mr-3">
                                        <input type="checkbox" class="custom-control-input" id="day-{{ $index }}" name="days[]" value="{{ $index }}">
                                        <label class="custom-control-label text-white" for="day-{{ $index }}">{{ $day }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enabledSwitch" name="enabled" checked>
                                <label class="custom-control-label text-white" for="enabledSwitch">Enable this schedule</label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3">
                            <a href="{{ route('schedules.index') }}" class="btn glass-button btn-secondary mr-3">Cancel</a>
                            <button type="submit" class="btn glass-button glass-button-primary px-4">Create Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('deviceSelect').addEventListener('change', function() {
        const widgetSelect = document.getElementById('widgetSelect');
        const selectedOption = this.options[this.selectedIndex];
        
        widgetSelect.innerHTML = '<option value="">Choose Widget...</option>';
        widgetSelect.disabled = true;

        if (this.value) {
            const widgets = JSON.parse(selectedOption.dataset.widgets);
            if (widgets.length > 0) {
                widgets.forEach(w => {
                    const opt = document.createElement('option');
                    opt.value = w.key;
                    opt.textContent = `${w.name} (${w.type})`;
                    opt.dataset.type = w.type;
                    widgetSelect.appendChild(opt);
                });
                widgetSelect.disabled = false;
            } else {
                widgetSelect.innerHTML = '<option value="">No widgets found on this device</option>';
            }
        }
    });

    document.getElementById('widgetSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const container = document.getElementById('valueInputContainer');
        const type = selectedOption.dataset.type;

        if (type === 'toggle') {
            container.innerHTML = `
                <select class="form-control glass-input" name="value">
                    <option value="1">ON</option>
                    <option value="0">OFF</option>
                </select>
            `;
        } else {
            container.innerHTML = `<input type="text" class="form-control glass-input" name="value" placeholder="Enter value" required>`;
        }
    });

    // Styles for Select options
    const style = document.createElement('style');
    style.textContent = `
        select.glass-input option {
            background: #0a0e27;
            color: white;
        }
    `;
    document.head.appendChild(style);
</script>
@endsection
