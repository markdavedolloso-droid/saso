<?php
// Registrar CRUD page for student records, including archive handling.
$page_title = 'Student Records';
$page_heading = 'Student Records';
require_once 'config/database.php';
require_once 'includes/auth.php';
require_role(['registrar']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = $_POST['action'] ?? 'create';
        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO students(student_number,first_name,last_name,middle_name,course,year_level,section,email,phone,gender,birth_date,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                trim($_POST['student_number'] ?? ''), trim($_POST['first_name'] ?? ''), trim($_POST['last_name'] ?? ''),
                trim($_POST['middle_name'] ?? ''), trim($_POST['course'] ?? ''), $_POST['year_level'] ?? '1st Year',
                trim($_POST['section'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), $_POST['gender'] ?? '',
                ($_POST['birth_date'] ?? '') ?: null, 'active'
            ]);
            $id = $pdo->lastInsertId();
            $q = $pdo->prepare('SELECT id FROM students WHERE student_number=? LIMIT 1'); $q->execute([trim($_POST['student_number'] ?? '')]); $id = $q->fetchColumn();
            log_action($pdo, 'created', 'Student Records', $id, 'Created student record.');
            $message = 'Student record created successfully.';
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare('UPDATE students SET student_number=?,first_name=?,last_name=?,middle_name=?,course=?,year_level=?,section=?,email=?,phone=?,gender=?,birth_date=?,status=? WHERE id=?');
            $stmt->execute([
                trim($_POST['student_number'] ?? ''), trim($_POST['first_name'] ?? ''), trim($_POST['last_name'] ?? ''),
                trim($_POST['middle_name'] ?? ''), trim($_POST['course'] ?? ''), $_POST['year_level'] ?? '1st Year', trim($_POST['section'] ?? ''),
                trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), $_POST['gender'] ?? '', ($_POST['birth_date'] ?? '') ?: null,
                $_POST['status'] ?? 'active', $_POST['id'] ?? ''
            ]);
            log_action($pdo, 'updated', 'Student Records', $_POST['id'] ?? null, 'Updated student record.');
            $message = 'Student record updated successfully.';
        } elseif ($action === 'archive') {
            $id = $_POST['id'] ?? '';
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT * FROM students WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $student = $q->fetch();
            if (!$student) {
                throw new RuntimeException('Student record not found.');
            }
            $pdo->prepare('DELETE FROM students_archive WHERE original_id=?')->execute([$id]);
            $archive = $pdo->prepare('INSERT INTO students_archive(original_id,profile_id,student_number,first_name,last_name,middle_name,course,year_level,section,email,phone,gender,birth_date,status,created_at,archived_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $archive->execute([$student['id'],$student['profile_id'],$student['student_number'],$student['first_name'],$student['last_name'],$student['middle_name'],$student['course'],$student['year_level'],$student['section'],$student['email'],$student['phone'],$student['gender'],$student['birth_date'],$student['status'],$student['created_at'],$_SESSION['user']['id'] ?? null]);
            $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$id]);
            $pdo->commit();
            log_action($pdo, 'archived', 'Student Records', $id, 'Student record was archived.');
            $message = 'Student record archived.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e instanceof PDOException && $e->getCode() === '23000' ? 'Student number or another unique value already exists.' : $e->getMessage();
    }
}
$rows = $pdo->query('SELECT * FROM students ORDER BY created_at DESC')->fetchAll();
include 'includes/header.php';
?>
<?php if ($message): ?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="page-actions"><div><p class="muted">Create, update, search, and archive student profiles. Records are retained instead of permanently deleted.</p></div><button class="btn primary" onclick="openModal('studentModal')">+ Add Student</button></div>
<section class="card"><div class="search"><input id="tableSearch" placeholder="Search students..."></div>
<table id="dataTable"><thead><tr><th>Student No.</th><th>Name</th><th>Course</th><th>Year</th><th>Section</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $student): ?>
<tr><td><?=htmlspecialchars($student['student_number'])?></td><td><?=htmlspecialchars($student['last_name'].', '.$student['first_name'].' '.$student['middle_name'])?></td><td><?=htmlspecialchars($student['course'])?></td><td><?=htmlspecialchars($student['year_level'])?></td><td><?=htmlspecialchars($student['section'])?></td><td><span class="badge <?=$student['status']==='active'?'green':'gold'?>"><?=htmlspecialchars($student['status'])?></span></td><td><button class="btn secondary" type="button" onclick='editStudent(<?=json_encode($student)?>)'>Edit</button> <?php if($student['status']==='active'): ?><form method="post" style="display:inline" onsubmit="return confirm('Archive this student record?')"><?=csrf_field()?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?=htmlspecialchars($student['id'])?>"><button class="btn secondary" type="submit">Archive</button></form><?php endif; ?></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="7">No student records found.</td></tr><?php endif; ?></tbody></table></section>
<div class="modal" id="studentModal"><div class="modal-box"><button class="close" onclick="closeModal('studentModal')">×</button><h2 id="studentModalTitle">Add Student</h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>"><input type="hidden" name="action" id="studentAction" value="create"><input type="hidden" name="id" id="studentId">
<label>Student Number<input id="student_number" name="student_number" required></label><label>First Name<input id="first_name" name="first_name" required></label><label>Last Name<input id="last_name" name="last_name" required></label><label>Middle Name<input id="middle_name" name="middle_name"></label><label>Course<input id="course" name="course" required></label><label>Year Level<select id="year_level" name="year_level"><option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option></select></label><label>Section<input id="section" name="section"></label><label>Email<input type="email" id="email" name="email"></label><label>Phone<input id="phone" name="phone"></label><label>Gender<select id="gender" name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></label><label>Birth Date<input type="date" id="birth_date" name="birth_date"></label><label>Status<select id="status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></label><div class="form-end"><button class="btn primary">Save Student</button></div></form></div></div>
<script>
function editStudent(s){document.getElementById('studentModalTitle').textContent='Edit Student';document.getElementById('studentAction').value='update';document.getElementById('studentId').value=s.id;['student_number','first_name','last_name','middle_name','course','year_level','section','email','phone','gender','birth_date','status'].forEach(k=>{const e=document.getElementById(k);if(e)e.value=s[k]??''});openModal('studentModal')}
</script>
<?php include 'includes/footer.php'; ?>
