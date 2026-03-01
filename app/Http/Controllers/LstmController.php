<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class LstmController extends Controller
{
    public function toggle(Request $request, Device $device)
    {
        // 1. Check Permission (Hardcoded for now as requested)
        if (!auth()->user()->canUseLstm()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to use AI features.'
            ], 403);
        }

        // 2. Validate Input
        $request->validate([
            'enabled' => 'required|boolean'
        ]);

        // 3. Update Device
        $device->update([
            'lstm_enabled' => $request->enabled
        ]);

        $status = $request->enabled ? 'enabled' : 'disabled';
        
        return response()->json([
            'success' => true,
            'message' => "AI Control {$status} for this device.",
            'lstm_enabled' => $device->lstm_enabled
        ]);
    }
}
