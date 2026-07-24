<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectionController extends Controller
{
    public function checkConnection(){
        try{
            DB::connection()->getPdo();
            return response()->json([
                'status' => 'success',
                'message' => 'Database Connected Successfully'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => 'Database Connection FAILED',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
