<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class AdminRegisterController extends Controller
{
    /**
     * Display the admin registration view.
     */
    public function create(): View
    {
        return view('auth.register-admin');
    }

    /**
     * Handle an incoming admin registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'auth_code' => ['required', 'string'],
        ]);

        // [SECURITY FIX C-5/L-4] Use config() not env() — env() returns null when config is cached
        if ($request->auth_code !== config('app.admin_registration_code')) {
            return back()->withErrors([
                'auth_code' => 'The provided authentication code is invalid.',
            ])->withInput($request->except('auth_code')); // Don't flash valid auth code if possible, or just all except password
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
