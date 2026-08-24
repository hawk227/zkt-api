<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    use HasFactory;

    protected $fillable = ['zk_device_id', 'employee_id', 'biometric_user_id', 'occurred_at', 'punch_state', 'verify_type', 'work_code', 'raw_record', 'fingerprint', 'received_at', 'processed_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function device(): BelongsTo { return $this->belongsTo(ZkDevice::class, 'zk_device_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
