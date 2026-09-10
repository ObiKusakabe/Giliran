<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class DevModeController extends Controller
{
    /**
     * Set dev mode time in session
     */
    public function setTime(Request $request)
    {
        $time = $request->input('time');

        if ($time) {
            Session::put('dev_mode_time', $time);

            return response()->json([
                'success' => true,
                'message' => 'Dev mode time set to: '.$time,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid time provided',
        ], 400);
    }

    /**
     * Reset dev mode time (back to real time)
     */
    public function resetTime()
    {
        Session::forget('dev_mode_time');

        return response()->json([
            'success' => true,
            'message' => 'Dev mode time reset. Using real system time.',
        ]);
    }

    /**
     * Get current dev mode time
     */
    public function getTime()
    {
        $devTime = Session::get('dev_mode_time');

        return response()->json([
            'dev_mode' => $devTime !== null,
            'time' => $devTime,
        ]);
    }
}
