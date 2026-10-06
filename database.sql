-- Complete database schema for a new CRMC SASO installation.
CREATE DATABASE IF NOT EXISTS crmc_saso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crmc_saso;

CREATE TABLE profiles (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','registrar','student') NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 first_name VARCHAR(100) NOT NULL DEFAULT '',
 last_name VARCHAR(100) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 profile_id CHAR(36) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE CASCADE
);

CREATE INDEX idx_password_resets_expiry ON password_resets(expires_at, used_at);

CREATE TABLE students (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 profile_id CHAR(36) NULL UNIQUE,
 student_number VARCHAR(50) NOT NULL UNIQUE,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 middle_name VARCHAR(100) DEFAULT '',
 course VARCHAR(150) NOT NULL DEFAULT '',
 department VARCHAR(150) NOT NULL DEFAULT '',
 year_level VARCHAR(30) NOT NULL DEFAULT '1st Year',
 section VARCHAR(50) DEFAULT '',
 email VARCHAR(190) DEFAULT '',
 phone VARCHAR(50) DEFAULT '',
 gender VARCHAR(30) DEFAULT '',
 birth_date DATE NULL,
 status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL
);


CREATE TABLE students_archive (
 archive_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 original_id CHAR(36) NOT NULL UNIQUE,
 profile_id CHAR(36) NULL,
 student_number VARCHAR(50) NOT NULL,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 middle_name VARCHAR(100) DEFAULT '',
 course VARCHAR(150) NOT NULL DEFAULT '',
 department VARCHAR(150) NOT NULL DEFAULT '',
 year_level VARCHAR(30) NOT NULL DEFAULT '1st Year',
 section VARCHAR(50) DEFAULT '',
 email VARCHAR(190) DEFAULT '',
 phone VARCHAR(50) DEFAULT '',
 gender VARCHAR(30) DEFAULT '',
 birth_date DATE NULL,
 status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'inactive',
 created_at TIMESTAMP NOT NULL,
 archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 archived_by CHAR(36) NULL,
 FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL,
 FOREIGN KEY(archived_by) REFERENCES profiles(id) ON DELETE SET NULL
);


CREATE TABLE violations (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 student_id CHAR(36) NOT NULL,
 violation_type ENUM('minor','major') NOT NULL,
 category VARCHAR(100) NOT NULL DEFAULT 'Other',
 description TEXT NOT NULL,
 incident_date DATE NOT NULL,
 location VARCHAR(255) DEFAULT '',
 reported_by VARCHAR(150) DEFAULT '',
 parent_contact_number VARCHAR(50) DEFAULT '',
 sanction VARCHAR(255) DEFAULT '',
 remarks TEXT,
 status ENUM('pending','cleared','dismissed') NOT NULL DEFAULT 'pending',
 resolution_notes TEXT,
 resolved_date DATETIME NULL,
 released_date DATE NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE think_sheets (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 student_id CHAR(36) NOT NULL,
 violation_id CHAR(36) NULL,
 assigned_by VARCHAR(150) NOT NULL DEFAULT '',
 assigned_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 due_date DATETIME NULL,
 status ENUM('assigned','submitted','reviewed') NOT NULL DEFAULT 'assigned',
 parent_guardian_name VARCHAR(200) DEFAULT '',
 parent_contact_number VARCHAR(50) DEFAULT '',
 parent_id_file VARCHAR(255) DEFAULT NULL,
 responses JSON,
 submitted_date DATETIME NULL,
 reviewed_by VARCHAR(150) DEFAULT '',
 reviewed_date DATETIME NULL,
 review_notes TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 FOREIGN KEY(violation_id) REFERENCES violations(id) ON DELETE SET NULL
);

CREATE TABLE think_sheet_records (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 student_id CHAR(36) NOT NULL,
 violation_id CHAR(36) NULL,
 recorded_by CHAR(36) NULL,
 reported_by VARCHAR(150) NOT NULL DEFAULT '',
 recorded_on DATE NOT NULL,
 document_file VARCHAR(255) NOT NULL,
 status ENUM('assigned','submitted','reviewed') NOT NULL DEFAULT 'submitted',
 notes TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_think_sheet_records_student_date (student_id, recorded_on),
 INDEX idx_think_sheet_records_date (recorded_on),
 INDEX idx_think_sheet_records_status (status),
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE RESTRICT,
 FOREIGN KEY(violation_id) REFERENCES violations(id) ON DELETE SET NULL,
 FOREIGN KEY(recorded_by) REFERENCES profiles(id) ON DELETE SET NULL
);

CREATE TABLE good_moral_requests (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 student_id CHAR(36) NOT NULL,
 requested_by CHAR(36) NULL,
 requester_name VARCHAR(200) NOT NULL DEFAULT '',
 purpose TEXT NOT NULL,
 status ENUM('pending_saso','saso_verified','registrar_processing','completed','rejected','on_hold') NOT NULL DEFAULT 'pending_saso',
 registrar_received_by VARCHAR(150) DEFAULT '',
 registrar_received_at DATETIME NULL,
 saso_verified_by VARCHAR(150) DEFAULT '',
 saso_verified_at DATETIME NULL,
 saso_notes TEXT,
 registrar_processed_by VARCHAR(150) DEFAULT '',
 registrar_processed_at DATETIME NULL,
 review_notes TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 FOREIGN KEY(requested_by) REFERENCES profiles(id) ON DELETE SET NULL
);




CREATE TABLE audit_logs (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 profile_id CHAR(36) NULL,
 action VARCHAR(100) NOT NULL,
 module VARCHAR(100) NOT NULL,
 record_id CHAR(36) NULL,
 details TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL
);

CREATE TABLE audit_logs_archive (
 archive_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 original_id CHAR(36) NOT NULL UNIQUE,
 profile_id CHAR(36) NULL,
 action VARCHAR(100) NOT NULL,
 module VARCHAR(100) NOT NULL,
 record_id CHAR(36) NULL,
 details TEXT,
 created_at TIMESTAMP NOT NULL,
 archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 profile_id CHAR(36) NOT NULL,
 title VARCHAR(200) NOT NULL,
 message TEXT NOT NULL,
 link VARCHAR(255) DEFAULT '',
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE CASCADE
);
CREATE INDEX idx_notifications_profile ON notifications(profile_id, is_read, created_at);


-- Demo accounts for local testing.
-- Passwords: Admin@123, Registrar@123, Student@123
INSERT IGNORE INTO profiles(email,password,role,first_name,last_name) VALUES
('admin@crmc.edu.ph', '$2y$12$VdGwYS.KaWEThzLUFGVv9uIwdTab3vJVGS/d3OOy3UoBicq1ADY2K', 'admin', 'SASO', 'Administrator'),
('registrar@crmc.edu.ph', '$2y$12$XgNcQjR1K/cV6wTKvXr8OewvusB0z5..So//bZtNO9R6TwTAYyn6m', 'registrar', 'Demo', 'Registrar'),
('student@crmc.edu.ph', '$2y$12$NnIqIouVNZz1HR.V7SSzMu7WkaxZSbCqMawvSxDbr4PM3yr5sxbMi', 'student', 'Demo', 'Student');

INSERT IGNORE INTO students(profile_id,student_number,first_name,last_name,middle_name,course,year_level,section,email,phone,status)
SELECT id,'DEMO-2026-001','Demo','Student','', 'BS Information Technology','3rd Year','A',email,'09123456789','active'
FROM profiles WHERE email='student@crmc.edu.ph' LIMIT 1;
