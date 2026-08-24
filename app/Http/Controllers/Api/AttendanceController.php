<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start'], 'device_id' => ['nullable', 'integer']]);
        $events = AttendanceEvent::query()->with(['device:id,serial_number,name', 'employee:id,employee_code,name'])
            ->when($data['device_id'] ?? null, fn ($q, $id) => $q->where('zk_device_id', $id))
            ->whereBetween('occurred_at', [$data['start'], $data['end']])->orderBy('occurred_at')->get();
        return response()->json(['data' => $events, 'meta' => ['count' => $events->count()]]);
    }
}
