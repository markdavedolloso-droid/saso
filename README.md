# CRMC SASO Management System — PHP + MySQL

This version is a working PHP/MySQL prototype for the **CRMC Student Affairs and Services Office (SASO) Management System with AI-Assisted Analytics**.

## Implemented in this version

- Role-based login for **SASO Administrator, Registrar Staff, and Student**
- Separate dashboards for each role
- Student self-service account creation with Student Number and linked student profile
- Student records
- Department, course, and year-level student details for department-based reports
- Student discipline records
- SASO-managed Think Sheet repository for scanned paper records, with Filed/Reviewed status, protected previews, and student-only views
- Good Moral request workflow:
  - Student submits request
  - SASO verifies the request and reviews relevant disciplinary records
  - SASO forwards the verified request to the Registrar for processing
  - SASO verifies, places on hold, or rejects the request
  - Registrar processes SASO-verified requests and may continue processing or mark them completed
  - Student tracks the status
- AI-assisted disciplinary analytics for summaries, patterns, and trends
- Audit log table for important actions
- Student and audit-log archive with admin restore/delete actions
- Existing reports and user account management retained
- Maroon CRMC-style interface

## Good Moral workflow

The system is designed around this workflow:

**Student → SASO Verification → Registrar Processing → Completed**

Alternative outcomes are **On Hold** or **Rejected** during SASO verification, and **Rejected** during Registrar processing. The exact CRMC procedure should still be confirmed with the Registrar/SASO head before final thesis deployment.

## AI-assisted analytics

The included `analytics.php` provides AI-assisted summaries of aggregate discipline records, Think Sheet repository totals, and Good Moral request workflow trends. AI output does not make final disciplinary decisions.

The analysis sends aggregated data to the configured AI service and should support SASO review rather than automatically make disciplinary decisions.

## Setup

1. Install XAMPP.
2. Start Apache and MySQL.
3. Extract this folder into `C:\xampp\htdocs\crmc_saso`.
4. Open phpMyAdmin or MySQL Workbench.
5. Import/execute `database.sql`.
6. For an existing installation, execute `database_update.sql` to add the archive tables, Think Sheet repository, and other current schema updates.
7. Check `config/database.php` if your MySQL username/password differs.
8. Open `http://localhost/crmc_saso/login.php`.

### Demo Accounts

These accounts are inserted automatically when `database.sql` is imported:

- SASO Administrator — `admin@crmc.edu.ph` / `Admin@123`
- Registrar Staff — `registrar@crmc.edu.ph` / `Registrar@123`
- Student — `student@crmc.edu.ph` / `Student@123`

The login page displays these demo accounts with a **Use** button that fills in the credentials automatically. The student account is linked to demo student number `DEMO-2026-001`.

### Student

Create a Student account from Sign Up. Enter a Student Number, course, year level, and section. The account is linked automatically to a student record.

Then the student can:

- view their own disciplinary record
- view only their own Think Sheet scan and its Filed/Reviewed status
- request a Good Moral Certificate
- track Good Moral status

### SASO Administrator

Log in as the default admin to:

- record discipline cases
- add and retrieve scanned Think Sheet records in the repository
- verify Good Moral requests
- review AI-assisted SASO analytics
- manage accounts and reports
### Registrar

Create a Registrar Staff account. The Registrar can:

- manage official student records
- receive and verify Good Moral requests
- forward verified requests to the Registrar
- see which requests have been SASO-verified
- process SASO-verified requests
- keep requests in processing
- mark requests completed

## Security note

Student registration requires an existing student number and automatically links the account to that row. Student-facing queries derive the student ID from the authenticated profile, while Registrar access includes official student records and Good Moral requests. Login, logout, account linking, and module actions are recorded in `audit_logs`.

CSRF protection and secure session cookie settings are included in this prototype. Before production deployment, add login rate limiting, stronger account/password recovery controls, backups, HTTPS, and file/attachment validation if attachments are added.
