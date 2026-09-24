-- Complete database schema for a new CRMC SASO installation.
CREATE DATABASE IF NOT EXISTS crmc_saso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crmc_saso;

CREATE TABLE profiles (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','registrar','student') NOT NULL,
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

CREATE TABLE faculty (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 profile_id CHAR(36) NULL,
 employee_number VARCHAR(50) NOT NULL UNIQUE,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 middle_name VARCHAR(100) DEFAULT '',
 department VARCHAR(150) NOT NULL DEFAULT '',
 position VARCHAR(100) NOT NULL DEFAULT 'Instructor',
 email VARCHAR(190) DEFAULT '',
 phone VARCHAR(50) DEFAULT '',
 gender VARCHAR(30) DEFAULT '',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
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

CREATE TABLE faculty_archive (
 archive_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 original_id CHAR(36) NOT NULL UNIQUE,
 profile_id CHAR(36) NULL,
 employee_number VARCHAR(50) NOT NULL,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 middle_name VARCHAR(100) DEFAULT '',
 department VARCHAR(150) NOT NULL DEFAULT '',
 position VARCHAR(100) NOT NULL DEFAULT 'Instructor',
 email VARCHAR(190) DEFAULT '',
 phone VARCHAR(50) DEFAULT '',
 gender VARCHAR(30) DEFAULT '',
 status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
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
 sanction VARCHAR(255) DEFAULT '',
 status ENUM('pending','under_review','resolved','dismissed') NOT NULL DEFAULT 'pending',
 resolution_notes TEXT,
 resolved_date DATETIME NULL,
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

CREATE TABLE good_moral_requests (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 student_id CHAR(36) NOT NULL,
 requested_by CHAR(36) NULL,
 requester_name VARCHAR(200) NOT NULL DEFAULT '',
 purpose TEXT NOT NULL,
 status ENUM('pending_registrar','pending_saso','saso_verified','registrar_processing','completed','rejected','on_hold') NOT NULL DEFAULT 'pending_registrar',
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

CREATE TABLE evaluation_criteria (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 name VARCHAR(200) NOT NULL,
 description TEXT,
 category VARCHAR(100) NOT NULL DEFAULT 'Teaching Performance',
 weight DECIMAL(6,2) NOT NULL DEFAULT 1.00,
 max_score INT NOT NULL DEFAULT 5,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teacher_evaluations (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 faculty_id CHAR(36) NOT NULL,
 student_id CHAR(36) NULL,
 evaluator_profile_id CHAR(36) NULL,
 academic_year VARCHAR(30) NOT NULL DEFAULT '',
 semester VARCHAR(30) NOT NULL DEFAULT '',
 overall_score DECIMAL(8,2) DEFAULT 0,
 general_comment TEXT,
 status ENUM('draft','submitted') NOT NULL DEFAULT 'submitted',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE SET NULL,
 FOREIGN KEY(evaluator_profile_id) REFERENCES profiles(id) ON DELETE SET NULL
);

CREATE TABLE evaluation_scores (
 id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
 evaluation_id CHAR(36) NOT NULL,
 criteria_id CHAR(36) NOT NULL,
 score INT NOT NULL DEFAULT 0,
 comment TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(evaluation_id) REFERENCES teacher_evaluations(id) ON DELETE CASCADE,
 FOREIGN KEY(criteria_id) REFERENCES evaluation_criteria(id) ON DELETE CASCADE
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

INSERT INTO profiles(email,password,role,first_name,last_name)
VALUES ('admin@crmc.edu.ph', '$2y$10$QFonA6Kukz9MV1ql6ZbNbu1qI9qqbkjswKWFyw3PXtqjpPit10zCG', 'admin', 'SASO', 'Administrator');

INSERT INTO evaluation_criteria(name,description,category,weight,max_score,sort_order) VALUES
('Punctuality and Attendance','The teacher comes to class on time and is regularly present.','Professionalism',1,5,1),
('Knowledge of Subject Matter','The teacher demonstrates mastery and up-to-date knowledge.','Teaching Performance',1,5,2),
('Teaching Methodology','The teacher uses effective teaching strategies.','Teaching Performance',1,5,3),
('Classroom Management','The teacher maintains a conducive learning environment.','Teaching Performance',1,5,4),
('Communication Skills','The teacher communicates ideas clearly and effectively.','Teaching Performance',1,5,5),
('Fairness and Objectivity','The teacher treats students fairly and grades objectively.','Professionalism',1,5,6),
('Approachability and Support','The teacher is approachable and supports students.','Interpersonal Skills',1,5,7),
('Availability for Consultation','The teacher is available for consultation outside class.','Interpersonal Skills',1,5,8);
