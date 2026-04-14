<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::withCount('devices')->latest()->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $plans = Plan::all();
        return view('admin.users.edit', compact('user', 'plans'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'email'                  => 'required|email|unique:users,email,' . $user->id,
            'role'                   => 'required|in:user,admin',
            'subscription_plan'      => 'required|exists:plans,slug',
            'subscription_expires_at'=> 'nullable|date',
            'lstm_allowed'           => 'nullable|boolean',
            'log_enabled'            => 'nullable|boolean',
            'log_interval'           => 'nullable|integer|min:0|max:3600',
        ]);

        // Checkbox fields: unchecked = absent from request → default false
        $validated['lstm_allowed'] = $request->has('lstm_allowed');
        $validated['log_enabled']  = $request->has('log_enabled');

        // Numeric field default
        $validated['log_interval'] = (int) $request->input('log_interval', 0);

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'User identification and access level updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot terminate current active session.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User account and associated telemetry purged.');
    }
}
