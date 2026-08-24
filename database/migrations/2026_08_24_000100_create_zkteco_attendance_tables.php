<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zk_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_payload_at')->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('biometric_user_id')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zk_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('biometric_user_id');
            $table->dateTime('occurred_at');
            $table->string('punch_state')->nullable();
            $table->string('verify_type')->nullable();
            $table->string('work_code')->nullable();
            $table->text('raw_record');
            $table->char('fingerprint', 64)->unique();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['zk_device_id', 'occurred_at']);
            $table->index(['employee_id', 'occurred_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('attendance_events'); Schema::dropIfExists('employees'); Schema::dropIfExists('zk_devices'); }
};
