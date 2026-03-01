<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function index()
    {
        $plans = Plan::all();
        return view('admin.plans.index', compact('plans'));
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_devices' => 'required|integer|min:1',
            'max_widgets_per_device' => 'required|integer|min:1',
            // 'features' => 'nullable|array' // Assuming we might add this later
        ]);

        // Auto-generate slug from name
        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);

        // Ensure slug is unique
        if (\App\Models\Plan::where('slug', $validated['slug'])->exists()) {
             $validated['slug'] .= '-' . uniqid();
        }

        $validated['features'] = ['history' => '30 Days', 'api_access' => true]; // Default features for now

        Plan::create($validated);

        return redirect()->route('admin.plans.index')->with('success', 'Subscription Plan created successfully.');
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_devices' => 'required|integer|min:1',
            'max_widgets_per_device' => 'required|integer|min:1',
        ]);

        $plan->update($validated);

        return redirect()->route('admin.plans.index')->with('success', "Plan {$plan->name} protocols updated successfully.");
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return redirect()->route('admin.plans.index')->with('success', 'Subscription Plan deleted successfully.');
    }
}
