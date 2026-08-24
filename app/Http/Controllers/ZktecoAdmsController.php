<?php

namespace App\Http\Controllers;

use App\Models\ZkDevice;
use App\Services\ZktecoAttendanceIngestor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ZktecoAdmsController extends Controller
{
    public function cdata(Request $request, ZktecoAttendanceIngestor $ingestor): Response
    {
        $device = $this->device($request);
        if (!$device) return response('UNKNOWN DEVICE', 403)->header('Content-Type', 'text/plain');
        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();
        if (strtoupper((string) $request->query('table')) === 'ATTLOG' && trim($request->getContent()) !== '') {
            $ingestor->ingest($device, $request->getContent());
        }
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    public function getRequest(Request $request): Response
    {
        if (!$this->device($request)) return response('UNKNOWN DEVICE', 403)->header('Content-Type', 'text/plain');
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    public function deviceCommand(Request $request): Response
    {
        return $this->getRequest($request);
    }

    private function device(Request $request): ?ZkDevice
    {
        $serial = $request->query('SN') ?? $request->input('SN');
        return $serial ? ZkDevice::query()->where('serial_number', $serial)->where('is_active', true)->first() : null;
    }
}
