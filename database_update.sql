-- Incremental migration for existing installations, including archive support.
USE crmc_saso;

ALTER TABLE profiles
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role;

CREATE TABLE IF NOT EXISTS audit_logs (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    profile_id CHAR(36) NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,
    record_id CHAR(36) NULL,
    details TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);
CREATE INDEX IF NOT EXISTS idx_students_name ON students(last_name, first_name);
CREATE INDEX IF NOT EXISTS idx_violations_student_date ON violations(student_id, incident_date);
CREATE INDEX IF NOT EXISTS idx_violations_status ON violations(status);
CREATE INDEX IF NOT EXISTS idx_think_sheets_student_status ON think_sheets(student_id, status);
CREATE INDEX IF NOT EXISTS idx_good_moral_status ON good_moral_requests(status);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs(created_at);

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS department VARCHAR(150) NOT NULL DEFAULT '' AFTER course;

ALTER TABLE violations
    ADD COLUMN IF NOT EXISTS parent_contact_number VARCHAR(50) DEFAULT '' AFTER reported_by,
    ADD COLUMN IF NOT EXISTS remarks TEXT AFTER sanction,
    ADD COLUMN IF NOT EXISTS released_date DATE NULL AFTER resolved_date;

ALTER TABLE violations
    MODIFY status ENUM('pending','under_review','resolved','cleared','dismissed') NOT NULL DEFAULT 'pending';

UPDATE violations SET status = 'cleared' WHERE status IN ('under_review','resolved');

ALTER TABLE violations
    MODIFY status ENUM('pending','cleared','dismissed') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    profile_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_password_resets_expiry ON password_resets(expires_at, used_at);

-- general_comment is already included in the current database.sql schema.

-- Align existing Good Moral requests with the current workflow before removing the old status value.
UPDATE good_moral_requests SET status='pending_saso' WHERE status='pending_registrar';

-- Keeps existing installations compatible with SASO hold and comment updates.
ALTER TABLE good_moral_requests
    MODIFY status ENUM('pending_saso','saso_verified','registrar_processing','completed','rejected','on_hold') NOT NULL DEFAULT 'pending_saso';

ALTER TABLE good_moral_requests
    ADD COLUMN IF NOT EXISTS saso_notes TEXT AFTER saso_verified_at;

ALTER TABLE good_moral_requests
    ADD COLUMN IF NOT EXISTS registrar_received_by VARCHAR(150) DEFAULT '' AFTER requested_by;

ALTER TABLE good_moral_requests
    ADD COLUMN IF NOT EXISTS registrar_received_at DATETIME NULL AFTER registrar_received_by;

CREATE TABLE IF NOT EXISTS students_archive (
    archive_id CHAR(36) PRIMARY KEY DEFAULT (UUID()), original_id CHAR(36) NOT NULL UNIQUE,
    profile_id CHAR(36) NULL, student_number VARCHAR(50) NOT NULL, first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL, middle_name VARCHAR(100) DEFAULT '', course VARCHAR(150) NOT NULL DEFAULT '',
    year_level VARCHAR(30) NOT NULL DEFAULT '1st Year', section VARCHAR(50) DEFAULT '', email VARCHAR(190) DEFAULT '',
    phone VARCHAR(50) DEFAULT '', gender VARCHAR(30) DEFAULT '', birth_date DATE NULL,
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'inactive', created_at TIMESTAMP NOT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, archived_by CHAR(36) NULL,
    FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL,
    FOREIGN KEY(archived_by) REFERENCES profiles(id) ON DELETE SET NULL
);

ALTER TABLE students_archive
    ADD COLUMN IF NOT EXISTS department VARCHAR(150) NOT NULL DEFAULT '' AFTER course;

CREATE TABLE IF NOT EXISTS audit_logs_archive (
    archive_id CHAR(36) PRIMARY KEY DEFAULT (UUID()), original_id CHAR(36) NOT NULL UNIQUE,
    profile_id CHAR(36) NULL, action VARCHAR(100) NOT NULL, module VARCHAR(100) NOT NULL,
    record_id CHAR(36) NULL, details TEXT, created_at TIMESTAMP NOT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL
);

INSERT IGNORE INTO students_archive(original_id,profile_id,student_number,first_name,last_name,middle_name,course,department,year_level,section,email,phone,gender,birth_date,status,created_at)
SELECT id,profile_id,student_number,first_name,last_name,middle_name,course,department,year_level,section,email,phone,gender,birth_date,status,created_at FROM students WHERE status <> 'active';


INSERT IGNORE INTO audit_logs_archive(original_id,profile_id,action,module,record_id,details,created_at)
SELECT old_logs.id,old_logs.profile_id,old_logs.action,old_logs.module,old_logs.record_id,old_logs.details,old_logs.created_at
FROM audit_logs old_logs
WHERE old_logs.id NOT IN (SELECT kept.id FROM (SELECT id FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT 15) kept);

DELETE FROM audit_logs
WHERE id NOT IN (SELECT kept.id FROM (SELECT id FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT 15) kept);


-- Real parent/guardian ID upload support for Think Sheets.
ALTER TABLE think_sheets
    ADD COLUMN IF NOT EXISTS parent_guardian_name VARCHAR(200) DEFAULT '' AFTER status,
    ADD COLUMN IF NOT EXISTS parent_contact_number VARCHAR(50) DEFAULT '' AFTER parent_guardian_name,
    ADD COLUMN IF NOT EXISTS parent_id_file VARCHAR(255) DEFAULT NULL AFTER parent_contact_number;

CREATE INDEX IF NOT EXISTS idx_think_sheets_parent_id_file ON think_sheets(parent_id_file);

-- Store scanned paper Think Sheets as SASO repository records, not student assignments.
CREATE TABLE IF NOT EXISTS think_sheet_records (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    student_id CHAR(36) NOT NULL,
    violation_id CHAR(36) NULL,
    recorded_by CHAR(36) NULL,
    reported_by VARCHAR(150) NOT NULL DEFAULT '',
    recorded_on DATE NOT NULL,
    document_file VARCHAR(255) NOT NULL,
    status ENUM('filed','reviewed') NOT NULL DEFAULT 'filed',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_think_sheet_records_student_date (student_id, recorded_on),
    INDEX idx_think_sheet_records_date (recorded_on),
    INDEX idx_think_sheet_records_status (status),
    FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY(violation_id) REFERENCES violations(id) ON DELETE SET NULL,
    FOREIGN KEY(recorded_by) REFERENCES profiles(id) ON DELETE SET NULL
);

ALTER TABLE think_sheet_records
    ADD COLUMN IF NOT EXISTS reported_by VARCHAR(150) NOT NULL DEFAULT '' AFTER recorded_by,
    ADD COLUMN IF NOT EXISTS status ENUM('filed','reviewed') NOT NULL DEFAULT 'filed' AFTER document_file;

CREATE INDEX IF NOT EXISTS idx_think_sheet_records_status ON think_sheet_records(status);

ALTER TABLE think_sheet_records
    MODIFY status ENUM('filed','assigned','submitted','reviewed') NOT NULL DEFAULT 'submitted';

UPDATE think_sheet_records SET status = 'submitted' WHERE status = 'filed';

ALTER TABLE think_sheet_records
    MODIFY status ENUM('assigned','submitted','reviewed') NOT NULL DEFAULT 'submitted';



