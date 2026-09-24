# CRMC SASO Management System — PHP + MySQL

This version is a working PHP/MySQL prototype for the **CRMC Student Affairs and Services Office (SASO) Management System with AI-Assisted Analytics**.

## Implemented in this version

- Role-based login for **SASO Administrator, Registrar Staff, and Student**
- Separate dashboards for each role
- Student self-service account creation with Student Number and linked student profile
- Student records and faculty records
- Student discipline records
- Digital Think Sheet assignment, student submission, and SASO review workflow
- Good Moral request workflow:
  - Student submits request
  - Registrar receives and verifies the student record
  - Registrar forwards the request to SASO for disciplinary verification
  - SASO verifies, places on hold, or rejects the request
  - Registrar processes SASO-verified requests and may continue processing or mark them completed
  - Student tracks the status
- Teacher Performance Evaluation with 1–5 ratings
- Written student feedback/comments after teacher evaluation
- SASO AI-assisted feedback analysis prototype that identifies recurring strengths and improvement themes
- Evaluation overview by teacher
- Audit log table for important actions
- Student, faculty, and audit-log archive with admin restore/delete actions
- Existing reports and user account management retained
- Maroon CRMC-style interface

## Good Moral workflow

The system is designed around this workflow:

**Student → Registrar Verification → SASO Verification → Registrar Processing → Completed**

Alternative outcomes are **On Hold** or **Rejected** during SASO verification, and **Rejected** during Registrar processing. The exact CRMC procedure should still be confirmed with the Registrar/SASO head before final thesis deployment.

## AI feedback analysis

The included `analytics.php` provides a **local AI-assisted prototype** that groups recurring words/phrases from student comments into strengths and areas for improvement. It does not call an external AI service yet. This makes the prototype runnable without an API key while keeping the interface ready for a later approved AI integration.

For a production/thesis version, the analysis should be connected to an approved AI service and should summarize comments without inventing conclusions. AI output should support SASO review rather than automatically judge or penalize teachers.

## Setup

1. Install XAMPP.
2. Start Apache and MySQL.
3. Extract this folder into `C:\xampp\htdocs\crmc_saso`.
4. Open phpMyAdmin or MySQL Workbench.
5. Import/execute `database.sql`.
6. For an existing installation, execute `database_update.sql` to add the archive tables and move older audit logs out of the live 15-record view.
7. Check `config/database.php` if your MySQL username/password differs.
8. Open `http://localhost/crmc_saso/login.php`.

### Default SASO Administrator

- Email: `admin@crmc.edu.ph`
- Password: `password`

**Change the default password before real use.**

## Demo workflow

### Student

Create a Student account from Sign Up. Enter a Student Number, course, year level, and section. The account is linked automatically to a student record.

Then the student can:

- view assigned Think Sheets
- submit Think Sheet reflections
- request a Good Moral Certificate
- track Good Moral status
- submit teacher evaluations and written feedback

### SASO Administrator

Log in as the default admin to:

- record discipline cases
- assign/review Think Sheets
- verify Good Moral requests
- view teacher evaluations
- review AI-assisted feedback themes
- manage faculty, accounts, and reports

### Registrar

Create a Registrar Staff account. The Registrar can:

- manage official student records
- receive and verify Good Moral requests
- forward verified requests to SASO
- see which requests have been SASO-verified
- process SASO-verified requests
- keep requests in processing
- mark requests completed

## Security note

Student registration requires an existing student number and automatically links the account to that row. Student-facing queries derive the student ID from the authenticated profile, while Registrar access includes official student records and Good Moral requests. Login, logout, account linking, and module actions are recorded in `audit_logs`.

CSRF protection and secure session cookie settings are included in this prototype. Before production deployment, add login rate limiting, stronger account/password recovery controls, backups, HTTPS, and file/attachment validation if attachments are added.
