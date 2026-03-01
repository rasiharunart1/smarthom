<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Protocol;

class AdminProtocolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $protocols = Protocol::latest()->paginate(10);
        return view('admin.protocols.index', compact('protocols'));
    }

    public function create()
    {
        return view('admin.protocols.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'default_port' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        Protocol::create($validated);

        return redirect()->route('admin.protocols.index')
            ->with('success', 'Protocol created successfully.');
    }

    public function edit(Protocol $protocol)
    {
        return view('admin.protocols.edit', compact('protocol'));
    }

    public function update(Request $request, Protocol $protocol)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'default_port' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $protocol->update($validated);

        return redirect()->route('admin.protocols.index')
            ->with('success', 'Protocol updated successfully.');
    }

    public function destroy(Protocol $protocol)
    {
        $protocol->delete();

        return redirect()->route('admin.protocols.index')
            ->with('success', 'Protocol deleted successfully.');
    }
}
