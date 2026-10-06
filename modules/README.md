# Role-Based PHP Modules

Use this folder to find each area's page source. The PHP files in the project root are compatibility entrypoints, so existing links and bookmarks continue to work.

## SASO

- `saso/accounts.php` - User account management
- `saso/analytics.php` - AI-assisted analytics
- `saso/audit_logs.php` - Audit log
- `saso/reports.php` - SASO reports

## Student

- `student/profile.php` - Student profile and password settings

## Registrar

- `registrar/students.php` - Student records
- `registrar/good_moral_certificate.php` - Certificate view and download

## Shared by roles

- `shared/dashboard.php` - Selects the SASO, Registrar, or Student dashboard by signed-in role
- `shared/archive.php` - SASO archive/audit tools and Registrar student archive
- `shared/good_moral.php` - Student request, SASO verification, and Registrar processing
- `shared/think_sheets.php` - SASO repository and Student-owned views
- `shared/violations.php` - SASO discipline management and Student-owned views
- `shared/view_think_sheet_record.php` - Protected Think Sheet details and scan
- `shared/download_parent_id.php` - Protected parent ID download
- `shared/notifications.php` - Notifications for signed-in roles
