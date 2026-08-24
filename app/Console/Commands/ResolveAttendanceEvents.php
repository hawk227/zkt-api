<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use Illuminate\Console\Command;

class ResolveAttendanceEvents extends Command
{
    protected $signature = 'attendance:resolve-unmatched';
    protected $description = 'Attach raw biometric attendance events to newly matched employees';

    public function handle(): int
    {
        $updated = 0;
        AttendanceEvent::query()->whereNull('employee_id')->orderBy('id')->eachById(function (AttendanceEvent $event) use (&$updated): void {
            $employeeId = Employee::query()->where('biometric_user_id', $event->biometric_user_id)->where('is_active', true)->value('id');
            if ($employeeId) { $event->update(['employee_id' => $employeeId]); $updated++; }
        });
        $this->info("Resolved {$updated} attendance event(s).");
        return self::SUCCESS;
    }
}
