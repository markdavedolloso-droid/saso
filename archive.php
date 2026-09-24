<?php
// Archive page for restoring or permanently deleting archived records by role.
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
    $type = $_POST['type'] ?? '';
    $archiveId = $_POST['archive_id'] ?? '';
    try {
        if (!in_array($type, ['students', 'faculty'], true) || $archiveId === '') {
            throw new RuntimeException('Invalid archive record.');
        }
        if ($type === 'students' && $user['role'] !== 'registrar') {
            throw new RuntimeException('Only Registrar Staff can manage archived student records.');
        }
        if ($type === 'faculty' && $user['role'] !== 'admin') {
            throw new RuntimeException('Only SASO Administrator can manage archived faculty records.');
        }
        $table = $type . '_archive';
        $mainTable = $type;
        $q = $pdo->prepare("SELECT * FROM {$table} WHERE archive_id=? LIMIT 1");
        $q->execute([$archiveId]);
        $record = $q->fetch();
        if (!$record) {
            throw new RuntimeException('Archived record not found.');
        }

        if (($_POST['action'] ?? '') === 'restore') {
            $pdo->beginTransaction();
            if ($type === 'students') {
                $stmt = $pdo->prepare('INSERT INTO students(id,profile_id,student_number,first_name,last_name,middle_name,course,year_level,section,email,phone,gender,birth_date,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$record['original_id'],$record['profile_id'],$record['student_number'],$record['first_name'],$record['last_name'],$record['middle_name'],$record['course'],$record['year_level'],$record['section'],$record['email'],$record['phone'],$record['gender'],$record['birth_date'],'active',$record['created_at']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO faculty(id,profile_id,employee_number,first_name,last_name,middle_name,department,position,email,phone,gender,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$record['original_id'],$record['profile_id'],$record['employee_number'],$record['first_name'],$record['last_name'],$record['middle_name'],$record['department'],$record['position'],$record['email'],$record['phone'],$record['gender'],'active',$record['created_at']]);
            }
            $pdo->prepare("DELETE FROM {$table} WHERE archive_id=?")->execute([$archiveId]);
            $pdo->commit();
            log_action($pdo, 'restored', ucfirst($type) . ' Records', $record['original_id'], 'Archived record was restored.');
            $message = 'Record restored successfully.';
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM {$mainTable} WHERE id=?")->execute([$record['original_id']]);
            $pdo->prepare("DELETE FROM {$table} WHERE archive_id=?")->execute([$archiveId]);
            $pdo->commit();
            log_action($pdo, 'deleted', ucfirst($type) . ' Archive', $record['original_id'], 'Archived record was permanently deleted.');
            $message = 'Archived record permanently deleted.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$students = $user['role'] === 'registrar' ? $pdo->query("SELECT * FROM students_archive ORDER BY archived_at DESC")->fetchAll() : [];
$faculty = $user['role'] === 'admin' ? $pdo->query("SELECT * FROM faculty_archive ORDER BY archived_at DESC")->fetchAll() : [];
$audit = $user['role'] === 'admin' ? $pdo->query("SELECT a.*,COALESCE(CONCAT(p.first_name,' ',p.last_name),'System') user_name FROM audit_logs_archive a LEFT JOIN profiles p ON p.id=a.profile_id ORDER BY a.created_at DESC, a.archive_id DESC LIMIT 100")->fetchAll() : [];
include 'includes/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="page-actions"><p class="muted"><?php if ($user['role'] === 'registrar'): ?>Manage archived student records. Restore returns a student record to active status.<?php else: ?>Manage archived faculty and audit records. Restore returns a faculty record to active status.<?php endif; ?></p></div>
<?php if ($user['role'] === 'registrar'): ?>
<section class="card"><div class="card-head"><h3>Archived Students</h3><span class="badge gold"><?= count($students) ?></span></div>
<div class="search" style="margin-top:12px;"><input type="text" id="searchStudents" placeholder="Search archived students by name, student no., or course…" oninput="filterTable('searchStudents','tblStudents')"></div>
<table id="tblStudents"><thead><tr><th>Student No.</th><th>Name</th><th>Course</th><th>Archived</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($students as $record): ?><tr><td><?= htmlspecialchars($record['student_number']) ?></td><td><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']) ?></td><td><?= htmlspecialchars($record['course']) ?></td><td><?= htmlspecialchars($record['archived_at']) ?></td><td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="type" value="students"><input type="hidden" name="archive_id" value="<?= htmlspecialchars($record['archive_id']) ?>"><input type="hidden" name="action" value="restore"><button class="btn secondary">Restore</button></form> <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this archived student?')"><?= csrf_field() ?><input type="hidden" name="type" value="students"><input type="hidden" name="archive_id" value="<?= htmlspecialchars($record['archive_id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn secondary">Delete</button></form></td></tr><?php endforeach; if (!$students): ?><tr><td colspan="5">No archived students.</td></tr><?php endif; ?></tbody></table></section>
<?php endif; ?>
<?php if ($user['role'] === 'admin'): ?>
<section class="card"><div class="card-head"><h3>Archived Faculty</h3><span class="badge gold"><?= count($faculty) ?></span></div>
<div class="search" style="margin-top:12px;"><input type="text" id="searchFaculty" placeholder="Search archived faculty by name, employee no., or department…" oninput="filterTable('searchFaculty','tblFaculty')"></div>
<table id="tblFaculty"><thead><tr><th>Employee No.</th><th>Name</th><th>Department</th><th>Archived</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($faculty as $record): ?><tr><td><?= htmlspecialchars($record['employee_number']) ?></td><td><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']) ?></td><td><?= htmlspecialchars($record['department']) ?></td><td><?= htmlspecialchars($record['archived_at']) ?></td><td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="type" value="faculty"><input type="hidden" name="archive_id" value="<?= htmlspecialchars($record['archive_id']) ?>"><input type="hidden" name="action" value="restore"><button class="btn secondary">Restore</button></form> <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this archived faculty member?')"><?= csrf_field() ?><input type="hidden" name="type" value="faculty"><input type="hidden" name="archive_id" value="<?= htmlspecialchars($record['archive_id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn secondary">Delete</button></form></td></tr><?php endforeach; if (!$faculty): ?><tr><td colspan="5">No archived faculty.</td></tr><?php endif; ?></tbody></table></section>
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
