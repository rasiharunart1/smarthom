@extends('layouts.auth')

@section('content')
    <div style="text-align: center;">
        <div style="font-size: 5rem; font-weight: 800; line-height: 1; margin-bottom: 1rem; background: linear-gradient(135deg, #fff 0%, var(--primary-green) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            404
        </div>
        <div class="form-title">Page Not Found</div>
        <div class="form-subtitle" style="margin-bottom: 2rem;">
            The page you are looking for might have been removed, had its name changed, or is temporarily unavailable. 
            <br><br>
            If you are attempting to access restricted resources, please note that all activities are logged.
        </div>

        <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.1); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
             <i class="fas fa-shield-alt" style="color: var(--primary-green-light); font-size: 2rem; margin-bottom: 1rem;"></i>
             <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0;">
                Our security systems are monitoring this request. Access to invalid or malicious paths is strictly prohibited.
             </p>
        </div>

        <a href="{{ url('/') }}" class="glass-button" style="display: block; text-decoration: none; text-align: center;">
            <i class="fas fa-home mr-2"></i> Return to Safety
        </a>
    </div>
@endsection
