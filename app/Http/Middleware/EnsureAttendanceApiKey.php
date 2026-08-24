<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAttendanceApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = (string) $request->header('X-Attendance-Key', $request->query('api_key', ''));
        abort_unless($key !== '' && hash_equals((string) config('services.attendance.api_key'), $key), 401, 'Invalid API key');
        return $next($request);
    }
}
