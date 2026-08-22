<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConnectionController extends Controller
{
    public function checkConnection()
    {
        try {
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'success',
                'message' => 'Database Connected Successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Connection check failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Database Connection FAILED',
            ], 500);
        }
    }
}
