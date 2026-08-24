# ZKTeco Attendance ERP Gateway

Laravel service for accepting real-time ZKTeco ADMS/iClock attendance pushes and exposing normalized attendance data to an ERP.

## Quick start

```bash
cp .env.example .env
php artisan key:generate
# Set DB_* and ATTENDANCE_API_KEY in .env
php artisan migrate
php artisan serve
```

Create a device and employee from Tinker or an admin integration:

```php
ZkDevice::create(['serial_number' => 'DEVICE_SERIAL', 'name' => 'Head Office', 'timezone' => 'Asia/Dhaka']);
Employee::create(['employee_code' => 'EMP-001', 'biometric_user_id' => '1', 'name' => 'Jane Doe']);
```

Configure a compatible terminal's ADMS/cloud server to the publicly reachable URL:

`https://your-domain.example/api/iclock/cdata`

The terminal must include its serial number as `SN`. This project accepts ATTLOG pushes, stores every valid device record in `attendance_events`, and deduplicates retry traffic using a SHA-256 fingerprint.

Retrieve events:

```bash
curl -H 'X-Attendance-Key: YOUR_KEY' \
  'https://your-domain.example/api/v1/attendance?start=2026-08-24%2000:00:00&end=2026-08-24%2023:59:59'
```

## Production requirements

- Run behind HTTPS with a reverse proxy and use a stable public URL/IP.
- Restrict inbound ADMS routes by device-network IP allowlist at the firewall/reverse proxy when possible.
- Keep device records; do not delete attendance data after receipt.
- Run `php artisan attendance:resolve-unmatched` after importing/updating employees, or schedule it every few minutes.
- Some older ZKTeco models only support LAN ZK-protocol polling; add a pyzk/Python worker for those devices rather than ADMS.
