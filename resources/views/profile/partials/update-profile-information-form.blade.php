<div class="glass-card mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Profile Information</h5>
        <p class="small mb-0" style="color: var(--text-muted);">Manage your account identification and communication settings.</p>
    </div>
    <div class="card-body p-4">
        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}">
            @csrf
            @method('patch')

            <div class="form-group mb-4">
                <label class="glass-label" for="name">Display Name</label>
                <input type="text" class="form-control glass-input @error('name') is-invalid @enderror" id="name"
                    name="name" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                @error('name')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4">
                <label class="glass-label" for="email">Email Address</label>
                <input type="email" class="form-control glass-input @error('email') is-invalid @enderror" id="email"
                    name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email')
                    <div class="error-msg">{{ $message }}</div>
                @enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                    <div class="mt-3 p-3" style="background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.2); border-radius: 12px;">
                        <span class="small" style="color: #fbbf24;">
                            Your identity is not yet verified.
                            <button form="send-verification"
                                class="btn btn-link btn-sm p-0 ml-1 text-decoration-none" style="color: #fbbf24; font-weight: 700;">
                                Resend Authorization Link
                            </button>
                        </span>
                    </div>

                    @if (session('status') === 'verification-link-sent')
                        <div class="mt-2 small text-success">
                            A fresh verification link has been dispatched to your inbox.
                        </div>
                    @endif
                @endif
            </div>

            <div class="d-flex align-items-center">
                <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                    Update Profile
                </button>

                @if (session('status') === 'profile-updated')
                    <span class="ml-3 small text-success" style="font-weight: 600;">
                        Changes stored effectively
                    </span>
                @endif
            </div>
        </form>
    </div>
</div>
