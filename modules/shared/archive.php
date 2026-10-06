<?php
// Archive page for restoring or permanently deleting archived student and audit records.
$page_title = 'Archive';
$page_heading = 'Archive';
require_once 'config/database.php';
require_once 'includes/auth.php';
require_role(['admin', 'registrar']);
$user = current_user();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $archiveId = $_POST['archive_id'] ?? '';
    try {
        if ($user['role'] !== 'registrar' || $archiveId === '') {
            throw new RuntimeException('Invalid archive record.');
        }
        $q = $pdo->prepare('SELECT * FROM students_archive WHERE archive_id=? LIMIT 1');
        $q->execute([$archiveId]);
        $record = $q->fetch();
        if (!$record) {
            throw new RuntimeException('Archived student record not found.');
        }
        if (($_POST['action'] ?? '') === 'restore') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO students(id,profile_id,student_number,first_name,last_name,middle_name,course,department,year_level,section,email,phone,gender,birth_date,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$record['original_id'],$record['profile_id'],$record['student_number'],$record['first_name'],$record['last_name'],$record['middle_name'],$record['course'],$record['department'],$record['year_level'],$record['section'],$record['email'],$record['phone'],$record['gender'],$record['birth_date'],'active',$record['created_at']]);
            $pdo->prepare('DELETE FROM students_archive WHERE archive_id=?')->execute([$archiveId]);
            $pdo->commit();
            log_action($pdo, 'restored', 'Student Records', $record['original_id'], 'Archived student record was restored.');
            $message = 'Student record restored successfully.';
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$record['original_id']]);
            $pdo->prepare('DELETE FROM students_archive WHERE archive_id=?')->execute([$archiveId]);
            $pdo->commit();
            log_action($pdo, 'deleted', 'Student Archive', $record['original_id'], 'Archived student record was permanently deleted.');
            $message = 'Archived student record permanently deleted.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$students = $user['role'] === 'registrar' ? $pdo->query('SELECT * FROM students_archive ORDER BY archived_at DESC')->fetchAll() : [];
$audit = $user['role'] === 'admin' ? $pdo->query("SELECT a.*,COALESCE(CONCAT(p.first_name,' ',p.last_name),'System') user_name FROM audit_logs_archive a LEFT JOIN profiles p ON p.id=a.profile_id ORDER BY a.created_at DESC, a.archive_id DESC LIMIT 100")->fetchAll() : [];
include 'includes/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="page-actions"><p class="muted">Manage archived student records and audit logs.</p></div>
<?php if ($user['role'] === 'registrar'): ?>
<section class="card"><div class="card-head"><h3>Archived Students</h3><span class="badge gold"><?= count($students) ?></span></div>
<div class="search" style="margin-top:12px;"><input type="text" id="searchStudents" placeholder="Search archived students by name, student no., or course…" oninput="filterTable('searchStudents','tblStudents')"></div>
<table id="tblStudents"><thead><tr><th>Student No.</th><th>Name</th><th>Department</th><th>Course</th><th>Archived</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($students as $record): ?><tr><td><?= htmlspecialchars($record['student_number']) ?></td><td><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']) ?></td><td><?= htmlspecialchars($record['department'] ?? '') ?></td><td><?= htmlspecialchars($record['course']) ?></td><td><?= htmlspecialchars($record['archived_at']) ?></td><td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="archive_id" value="<?= htmlspecialchars($record['archive_id']) ?>"><input type="hidden" name="action" value="restore"><button class="btn secondary">Restore</button></form> <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this archived student?')"><?= csrf_field() ?><input type="hidden" name="archive_id" value="<?= htmlspecialchars($record['archive_id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn secondary">Delete</button></form></td></tr><?php endforeach; if (!$students): ?><tr><td colspan="6">No archived students.</td></tr><?php endif; ?></tbody></table></section>
<?php endif; ?>
<?php if ($user['role'] === 'admin'): ?>
<section class="card"><div class="card-head"><h3>Archived Audit Logs</h3><span class="badge gold"><?= count($audit) ?></span></div><table><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Details</th></tr></thead><tbody><?php foreach ($audit as $record): ?><tr><td><?= htmlspecialchars($record['created_at']) ?></td><td><?= htmlspecialchars($record['user_name']) ?></td><td><?= htmlspecialchars($record['action']) ?></td><td><?= htmlspecialchars($record['module']) ?></td><td><?= htmlspecialchars($record['details'] ?? '') ?></td></tr><?php endforeach; if (!$audit): ?><tr><td colspan="5">No archived audit logs.</td></tr><?php endif; ?></tbody></table></section>
<?php endif; ?>
<script>
function filterTable(inputId, tableId) {
    var q = document.getElementById(inputId).value.toLowerCase();
    var rows = document.getElementById(tableId).tBodies[0].rows;
    for (var i = 0; i < rows.length; i++) {
        var text = rows[i].textContent.toLowerCase();
        rows[i].style.display = q === '' || text.indexOf(q) > -1 ? '' : 'none';
    }
}
</script>
<?php include 'includes/footer.php'; ?>
