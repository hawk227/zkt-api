PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS devices (
  id INTEGER PRIMARY KEY,
  serial_number TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  location TEXT,
  timezone TEXT NOT NULL DEFAULT 'Asia/Dhaka',
  is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
  last_seen_at TEXT,
  last_payload_at TEXT,
  last_ip TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
  id INTEGER PRIMARY KEY,
  employee_code TEXT NOT NULL UNIQUE,
  biometric_user_id TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  email TEXT UNIQUE,
  is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attendance_events (
  id INTEGER PRIMARY KEY,
  device_id INTEGER NOT NULL REFERENCES devices(id),
  employee_id INTEGER REFERENCES employees(id),
  biometric_user_id TEXT NOT NULL,
  device_timestamp TEXT NOT NULL,
  punch_state TEXT,
  verify_type TEXT,
  work_code TEXT,
  raw_record TEXT NOT NULL,
  fingerprint TEXT NOT NULL UNIQUE,
  received_at TEXT NOT NULL,
  processed_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS attendance_events_device_time ON attendance_events(device_id, device_timestamp);
CREATE INDEX IF NOT EXISTS attendance_events_employee_time ON attendance_events(employee_id, device_timestamp);
