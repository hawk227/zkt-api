<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZkDevice extends Model
{
    use HasFactory;

    protected $fillable = ['serial_number', 'name', 'location', 'timezone', 'is_active', 'last_seen_at', 'last_payload_at', 'last_ip'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime', 'last_payload_at' => 'datetime'];
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'zk_device_id');
    }
}
