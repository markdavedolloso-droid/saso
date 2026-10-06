<?php
// Admin CRUD page for faculty records, including archive handling.
$page_title = 'Faculty Records';
$page_heading = 'Faculty Records';

require_once 'config/database.php';
require_once 'includes/auth.php';
require_role(['admin']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO faculty(employee_number,first_name,last_name,middle_name,department,position,email,phone,gender,status) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                trim($_POST['employee_number'] ?? ''),
                trim($_POST['first_name'] ?? ''),
                trim($_POST['last_name'] ?? ''),
                trim($_POST['middle_name'] ?? ''),
                trim($_POST['department'] ?? ''),
                trim($_POST['position'] ?? 'Instructor'),
                trim($_POST['email'] ?? ''),
                trim($_POST['phone'] ?? ''),
                $_POST['gender'] ?? '',
                'active',
            ]);

            $q = $pdo->prepare('SELECT id FROM faculty WHERE employee_number=? LIMIT 1');
            $q->execute([trim($_POST['employee_number'] ?? '')]);
            $id = $q->fetchColumn();
            log_action($pdo, 'created', 'Faculty Records', $id, 'Created faculty record.');
            $message = 'Faculty record created successfully.';
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare('UPDATE faculty SET employee_number=?,first_name=?,last_name=?,middle_name=?,department=?,position=?,email=?,phone=?,gender=?,status=? WHERE id=?');
            $stmt->execute([
                trim($_POST['employee_number'] ?? ''),
                trim($_POST['first_name'] ?? ''),
                trim($_POST['last_name'] ?? ''),
                trim($_POST['middle_name'] ?? ''),
                trim($_POST['department'] ?? ''),
                trim($_POST['position'] ?? 'Instructor'),
                trim($_POST['email'] ?? ''),
                trim($_POST['phone'] ?? ''),
                $_POST['gender'] ?? '',
                $_POST['status'] ?? 'active',
                $_POST['id'] ?? '',
            ]);
            log_action($pdo, 'updated', 'Faculty Records', $_POST['id'] ?? null, 'Updated faculty record.');
            $message = 'Faculty record updated successfully.';
        } elseif ($action === 'archive') {
            $id = $_POST['id'] ?? '';
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT * FROM faculty WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $faculty = $q->fetch();
            if (!$faculty) {
                throw new RuntimeException('Faculty record not found.');
            }
            $pdo->prepare('DELETE FROM faculty_archive WHERE original_id=?')->execute([$id]);
            $archive = $pdo->prepare('INSERT INTO faculty_archive(original_id,profile_id,employee_number,first_name,last_name,middle_name,department,position,email,phone,gender,status,created_at,archived_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $archive->execute([$faculty['id'],$faculty['profile_id'],$faculty['employee_number'],$faculty['first_name'],$faculty['last_name'],$faculty['middle_name'],$faculty['department'],$faculty['position'],$faculty['email'],$faculty['phone'],$faculty['gender'],$faculty['status'],$faculty['created_at'],$_SESSION['user']['id'] ?? null]);
            $pdo->prepare('DELETE FROM faculty WHERE id=?')->execute([$id]);
            $pdo->commit();
            log_action($pdo, 'archived', 'Faculty Records', $id, 'Faculty record was archived.');
            $message = 'Faculty record archived.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e instanceof PDOException && $e->getCode() === '23000'
            ? 'Employee number or another unique value already exists.'
            : $e->getMessage();
    }
}

$rows = $pdo->query('SELECT * FROM faculty ORDER BY created_at DESC')->fetchAll();
include 'includes/header.php';
?>

<?php if ($message) : ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error) : ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="page-actions">
    <p class="muted">Maintain faculty records, update employment information, and deactivate records without deleting historical evaluations.</p>
    <button class="btn primary" onclick="openModal('facultyModal')">+ Add Faculty</button>
</div>

<section class="card">
    <div class="search">
        <input id="tableSearch" placeholder="Search faculty...">
    </div>

    <table id="dataTable">
        <thead>
            <tr>
                <th>Employee No.</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $f) : ?>
                <tr>
                    <td><?= htmlspecialchars($f['employee_number']) ?></td>
                    <td><?= htmlspecialchars($f['last_name'] . ', ' . $f['first_name'] . ' ' . $f['middle_name']) ?></td>
                    <td><?= htmlspecialchars($f['department']) ?></td>
                    <td><?= htmlspecialchars($f['position']) ?></td>
                    <td><span class="badge <?= $f['status'] === 'active' ? 'green' : 'gold' ?>"><?= htmlspecialchars($f['status']) ?></span></td>
                    <td>
                        <button class="btn secondary" type="button" onclick='editFaculty(<?= json_encode($f) ?>)'>Edit</button>
                        <?php if ($f['status'] === 'active') : ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Archive this faculty record?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($f['id']) ?>">
                                <button class="btn secondary">Archive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows) : ?><tr><td colspan="6">No faculty records found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<div class="modal" id="facultyModal">
    <div class="modal-box">
        <button class="close" onclick="closeModal('facultyModal')">×</button>
        <h2 id="facultyModalTitle">Add Faculty</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="action" id="facultyAction" value="create">
            <input type="hidden" name="id" id="facultyId">

            <label>Employee Number<input id="employee_number" name="employee_number" required></label>
            <label>First Name<input id="first_name" name="first_name" required></label>
            <label>Last Name<input id="last_name" name="last_name" required></label>
            <label>Middle Name<input id="middle_name" name="middle_name"></label>
            <label>Department<input id="department" name="department"></label>
            <label>Position<input id="position" name="position" value="Instructor"></label>
            <label>Email<input type="email" id="email" name="email"></label>
            <label>Phone<input id="phone" name="phone"></label>
            <label>Gender<select id="gender" name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></label>
            <label>Status<select id="status" name="status"><option>active</option><option>inactive</option></select></label>

            <div class="form-end">
                <button class="btn primary">Save Faculty</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editFaculty(f) {
        document.getElementById('facultyModalTitle').textContent = 'Edit Faculty';
        document.getElementById('facultyAction').value = 'update';
        document.getElementById('facultyId').value = f.id;

        ['employee_number', 'first_name', 'last_name', 'middle_name', 'department', 'position', 'email', 'phone', 'gender', 'status'].forEach(function (k) {
            const e = document.getElementById(k);
            if (e) {
                e.value = f[k] ?? '';
            }
        });

        openModal('facultyModal');
    }
</script>

<?php include 'includes/footer.php'; ?>
