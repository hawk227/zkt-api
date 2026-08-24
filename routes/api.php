<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\ZktecoAdmsController;
use Illuminate\Support\Facades\Route;

// ZKTeco ADMS / iClock protocol endpoints. Configure each terminal's server URL as:
// https://your-domain.example/iclock/cdata (normally port 80/443 through a reverse proxy).
Route::match(['get', 'post'], '/iclock/cdata', [ZktecoAdmsController::class, 'cdata']);
Route::match(['get', 'post'], '/iclock/getrequest', [ZktecoAdmsController::class, 'getRequest']);
Route::match(['get', 'post'], '/iclock/devicecmd', [ZktecoAdmsController::class, 'deviceCommand']);

Route::middleware('attendance.api')->group(function (): void {
    Route::get('/v1/attendance', [AttendanceController::class, 'index']);
});
