type AttendanceMessage = { eventId: number };
type Device = { id: number; serial_number: string; timezone: string };
type AttendanceRecord = {
  userId: string; timestamp: string; punchState: string | null; verifyType: string | null; workCode: string | null; raw: string;
};

const TEXT_HEADERS = { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'no-store' };
const JSON_HEADERS = { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' };
const MAX_ADMS_BYTES = 1_000_000;

export default {
  async fetch(request, env, ctx): Promise<Response> {
    try {
      const url = new URL(request.url);
      if (url.pathname === '/health') return json({ ok: true });
      if (url.pathname === '/iclock/cdata') return await handleClockData(request, env, ctx);
      if (url.pathname === '/iclock/getrequest' || url.pathname === '/iclock/devicecmd') return await handleDeviceCommand(request, env);
      if (url.pathname === '/api/v1/attendance' && request.method === 'GET') return await listAttendance(request, env);
      if (url.pathname === '/api/v1/devices' && request.method === 'POST') return await createDevice(request, env);
      if (url.pathname === '/api/v1/employees' && request.method === 'POST') return await createEmployee(request, env);
      return json({ error: 'Not found' }, 404);
    } catch (error) {
      console.error(JSON.stringify({ level: 'error', message: error instanceof Error ? error.message : 'Unknown error' }));
      return json({ error: 'Internal server error' }, 500);
    }
  },

  async queue(batch, env): Promise<void> {
    for (const message of batch.messages) {
      try {
        const event = await env.DB.prepare(
          'SELECT id, biometric_user_id FROM attendance_events WHERE id = ?'
        ).bind(message.body.eventId).first<{ id: number; biometric_user_id: string }>();
        if (!event) { message.ack(); continue; }
        const employee = await env.DB.prepare(
          'SELECT id FROM employees WHERE biometric_user_id = ? AND is_active = 1'
        ).bind(event.biometric_user_id).first<{ id: number }>();
        await env.DB.prepare('UPDATE attendance_events SET employee_id = ?, processed_at = ? WHERE id = ?')
          .bind(employee?.id ?? null, new Date().toISOString(), event.id).run();
        message.ack();
      } catch (error) {
        console.error(JSON.stringify({ level: 'error', message: 'Queue processing failed', error: error instanceof Error ? error.message : String(error), eventId: message.body.eventId }));
        message.retry();
      }
    }
  },
} satisfies ExportedHandler<Env, AttendanceMessage>;

async function handleClockData(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
  const device = await findDevice(request, env);
  if (!device) return new Response('UNKNOWN DEVICE', { status: 403, headers: TEXT_HEADERS });
  const now = new Date().toISOString();
  await env.DB.prepare('UPDATE devices SET last_seen_at = ?, last_ip = ?, updated_at = ? WHERE id = ?')
    .bind(now, request.headers.get('CF-Connecting-IP'), now, device.id).run();
  if (request.method !== 'POST' || new URL(request.url).searchParams.get('table')?.toUpperCase() !== 'ATTLOG') {
    return new Response('OK', { headers: TEXT_HEADERS });
  }
  const length = Number(request.headers.get('content-length') ?? '0');
  if (Number.isFinite(length) && length > MAX_ADMS_BYTES) return new Response('PAYLOAD TOO LARGE', { status: 413, headers: TEXT_HEADERS });
  const payload = await request.text();
  if (payload.length > MAX_ADMS_BYTES) return new Response('PAYLOAD TOO LARGE', { status: 413, headers: TEXT_HEADERS });
  const records = parseAttendance(payload);
  if (records.invalid > 0) console.warn(JSON.stringify({ level: 'warn', message: 'Ignored invalid attendance lines', serial: device.serial_number, invalid: records.invalid }));
  const messages: AttendanceMessage[] = [];
  for (const record of records.valid) {
    const fingerprint = await sha256([String(device.id), record.userId, record.timestamp, record.punchState ?? '', record.verifyType ?? '', record.workCode ?? ''].join('|'));
    const result = await env.DB.prepare(
      'INSERT OR IGNORE INTO attendance_events (device_id, biometric_user_id, device_timestamp, punch_state, verify_type, work_code, raw_record, fingerprint, received_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    ).bind(device.id, record.userId, record.timestamp, record.punchState, record.verifyType, record.workCode, record.raw, fingerprint, now).run();
    if ((result.meta.changes ?? 0) === 1) messages.push({ eventId: Number(result.meta.last_row_id) });
  }
  await env.DB.prepare('UPDATE devices SET last_payload_at = ?, updated_at = ? WHERE id = ?').bind(now, now, device.id).run();
  if (messages.length > 0) ctx.waitUntil(env.ATTENDANCE_QUEUE.sendBatch(messages.map((body) => ({ body }))));
  return new Response('OK', { headers: TEXT_HEADERS });
}

async function handleDeviceCommand(request: Request, env: Env): Promise<Response> {
  return (await findDevice(request, env))
    ? new Response('OK', { headers: TEXT_HEADERS })
    : new Response('UNKNOWN DEVICE', { status: 403, headers: TEXT_HEADERS });
}

async function listAttendance(request: Request, env: Env): Promise<Response> {
  if (!await isAdmin(request, env)) return json({ error: 'Unauthorized' }, 401);
  const url = new URL(request.url);
  const start = url.searchParams.get('start');
  const end = url.searchParams.get('end');
  if (!isTimestamp(start) || !isTimestamp(end) || start > end) return json({ error: 'start and end must be valid YYYY-MM-DD HH:mm:ss values' }, 422);
  const rows = await env.DB.prepare(
    `SELECT a.id, a.biometric_user_id, a.device_timestamp, a.punch_state, a.verify_type, a.work_code, a.received_at,
            d.serial_number AS device_serial_number, d.name AS device_name, e.employee_code, e.name AS employee_name
     FROM attendance_events a JOIN devices d ON d.id = a.device_id LEFT JOIN employees e ON e.id = a.employee_id
     WHERE a.device_timestamp BETWEEN ? AND ? ORDER BY a.device_timestamp ASC LIMIT 10000`
  ).bind(start, end).all();
  return json({ data: rows.results, meta: { count: rows.results.length } });
}

async function createDevice(request: Request, env: Env): Promise<Response> {
  if (!await isAdmin(request, env)) return json({ error: 'Unauthorized' }, 401);
  const body = await requestJson(request);
  if (!body || !isText(body.serial_number) || !isText(body.name)) return json({ error: 'serial_number and name are required' }, 422);
  const now = new Date().toISOString();
  const result = await env.DB.prepare('INSERT INTO devices (serial_number, name, location, timezone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
    .bind(body.serial_number, body.name, isText(body.location) ? body.location : null, isText(body.timezone) ? body.timezone : 'Asia/Dhaka', now, now).run();
  return json({ id: result.meta.last_row_id }, 201);
}

async function createEmployee(request: Request, env: Env): Promise<Response> {
  if (!await isAdmin(request, env)) return json({ error: 'Unauthorized' }, 401);
  const body = await requestJson(request);
  if (!body || !isText(body.employee_code) || !isText(body.biometric_user_id) || !isText(body.name)) return json({ error: 'employee_code, biometric_user_id and name are required' }, 422);
  const now = new Date().toISOString();
  const result = await env.DB.prepare('INSERT INTO employees (employee_code, biometric_user_id, name, email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
    .bind(body.employee_code, body.biometric_user_id, body.name, isText(body.email) ? body.email : null, now, now).run();
  return json({ id: result.meta.last_row_id }, 201);
}

async function findDevice(request: Request, env: Env): Promise<Device | null> {
  const serial = new URL(request.url).searchParams.get('SN');
  if (!serial || serial.length > 100) return null;
  return await env.DB.prepare('SELECT id, serial_number, timezone FROM devices WHERE serial_number = ? AND is_active = 1').bind(serial).first<Device>();
}

function parseAttendance(payload: string): { valid: AttendanceRecord[]; invalid: number } {
  const valid: AttendanceRecord[] = []; let invalid = 0;
  for (const raw of payload.split(/\r?\n/)) {
    if (!raw.trim()) continue;
    const fields = raw.split('\t');
    if (!isText(fields[0]) || !isTimestamp(fields[1])) { invalid++; continue; }
    valid.push({ userId: fields[0].trim(), timestamp: fields[1].trim(), punchState: valueOrNull(fields[2]), verifyType: valueOrNull(fields[3]), workCode: valueOrNull(fields[4]), raw });
  }
  return { valid, invalid };
}

async function isAdmin(request: Request, env: Env): Promise<boolean> {
  const received = request.headers.get('X-Attendance-Key') ?? new URL(request.url).searchParams.get('api_key') ?? '';
  const configured = env.ADMIN_API_KEY ?? '';
  return received !== '' && !configured.startsWith('__MUST_') && await secureEqual(received, configured);
}

async function secureEqual(left: string, right: string): Promise<boolean> {
  const [a, b] = await Promise.all([sha256(left), sha256(right)]);
  let diff = 0; for (let index = 0; index < a.length; index++) diff |= a.charCodeAt(index) ^ b.charCodeAt(index);
  return diff === 0;
}

async function sha256(value: string): Promise<string> {
  const bytes = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value));
  return [...new Uint8Array(bytes)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

function isTimestamp(value: string | null): value is string { return value !== null && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value); }
function isText(value: unknown): value is string { return typeof value === 'string' && value.trim().length > 0 && value.length <= 255; }
function valueOrNull(value: string | undefined): string | null { return isText(value) ? value.trim() : null; }
async function requestJson(request: Request): Promise<Record<string, unknown> | null> { try { const value: unknown = await request.json(); return typeof value === 'object' && value !== null && !Array.isArray(value) ? value as Record<string, unknown> : null; } catch { return null; } }
function json(body: unknown, status = 200): Response { return new Response(JSON.stringify(body), { status, headers: JSON_HEADERS }); }
