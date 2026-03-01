@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">System Settings</h1>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="glass-card">
                <div class="card-body">
                    <form action="{{ route('admin.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-4">
                            <label class="text-white mb-2">Admin WhatsApp Number</label>
                            <input type="text" name="admin_whatsapp" 
                                   class="form-control glass-input" 
                                   value="{{ $settings['admin_whatsapp'] ?? '' }}"
                                   placeholder="e.g. 628123456789">
                            <small class="text-white-50">Enter number in international format without '+' (e.g., 628...)</small>
                        </div>

                        <button type="submit" class="btn glass-button btn-primary btn-block">
                            Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
