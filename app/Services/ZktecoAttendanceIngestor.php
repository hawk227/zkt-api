<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\ZkDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ZktecoAttendanceIngestor
{
    /** @return array{accepted:int,duplicates:int,invalid:int} */
    public function ingest(ZkDevice $device, string $payload): array
    {
        $result = ['accepted' => 0, 'duplicates' => 0, 'invalid' => 0];
        foreach (preg_split('/\r\n|\r|\n/', trim($payload)) ?: [] as $line) {
            if (trim($line) === '') continue;
            $parts = preg_split('/\t+/', trim($line));
            if (count($parts) < 2) { $result['invalid']++; continue; }
            [$userId, $timestamp] = $parts;
            try { $occurredAt = CarbonImmutable::parse($timestamp, $device->timezone ?: config('app.timezone')); }
            catch (\Throwable) { $result['invalid']++; continue; }
            $fingerprint = hash('sha256', implode('|', [$device->id, $userId, $occurredAt->format('Y-m-d H:i:s'), $parts[2] ?? '', $parts[3] ?? '', $parts[4] ?? '']));
            $employeeId = Employee::query()->where('biometric_user_id', $userId)->where('is_active', true)->value('id');
            $event = AttendanceEvent::query()->firstOrCreate(['fingerprint' => $fingerprint], [
                'zk_device_id' => $device->id, 'employee_id' => $employeeId, 'biometric_user_id' => $userId,
                'occurred_at' => $occurredAt, 'punch_state' => $parts[2] ?? null, 'verify_type' => $parts[3] ?? null,
                'work_code' => $parts[4] ?? null, 'raw_record' => $line, 'received_at' => now(),
            ]);
            $event->wasRecentlyCreated ? $result['accepted']++ : $result['duplicates']++;
        }
        $device->forceFill(['last_payload_at' => now()])->save();
        return $result;
    }
}
