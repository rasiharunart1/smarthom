<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Device;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'pro_users' => User::where('subscription_plan', 'pro')->count(),
            'enterprise_users' => User::where('subscription_plan', 'enterprise')->count(),
            'total_devices' => Device::count(),
            'online_devices' => Device::where('status', 'online')->count(),
        ];

        $recentUsers = User::withCount('devices')->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentUsers'));
    }

    public function stats()
    {
        return response()->json([
            'total_users' => User::count(),
            'total_subscriptions' => User::whereIn('subscription_plan', ['pro', 'enterprise'])->count(),
            'total_devices' => Device::count(),
            'online_devices' => Device::where('status', 'online')->count(),
        ]);
    }
}
