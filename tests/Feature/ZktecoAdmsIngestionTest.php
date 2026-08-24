<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\ZkDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZktecoAdmsIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ingests_and_deduplicates_an_adms_attendance_log(): void
    {
        $device = ZkDevice::create(['serial_number' => 'AC123456', 'name' => 'Main Gate', 'timezone' => 'Asia/Dhaka']);
        $employee = Employee::create(['employee_code' => 'EMP-001', 'biometric_user_id' => '42', 'name' => 'Amina Rahman']);
        $payload = "42\t2026-08-24 09:00:01\t0\t1\t0\n";

        $this->call('POST', '/api/iclock/cdata?SN=AC123456&table=ATTLOG', [], [], [], [], $payload)
            ->assertOk()->assertSee('OK');
        $this->call('POST', '/api/iclock/cdata?SN=AC123456&table=ATTLOG', [], [], [], [], $payload)
            ->assertOk();

        $this->assertDatabaseCount('attendance_events', 1);
        $this->assertDatabaseHas('attendance_events', ['zk_device_id' => $device->id, 'employee_id' => $employee->id, 'biometric_user_id' => '42']);
    }

    public function test_it_rejects_unknown_devices(): void
    {
        $this->call('POST', '/api/iclock/cdata?SN=UNKNOWN&table=ATTLOG', [], [], [], [], "1\t2026-08-24 09:00:01\t0\t1\t0")
            ->assertForbidden();
    }
}
