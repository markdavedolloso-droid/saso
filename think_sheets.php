<?php

// Handles Think Sheet assignment, student submission, and staff review workflows.

$page_title = 'Think Sheets';
$page_heading = 'Digital Think Sheets';

require_once 'config/database.php';
require_once 'includes/auth.php';

require_login();

$user = current_user();
$student = get_student_for_user($pdo);
$message = '';
$error = '';

// Uploaded parent/guardian IDs are kept under this application directory.
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'think_sheets';
$maxUploadBytes = 5 * 1024 * 1024;
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf'
];

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
    $error = 'The parent ID upload directory could not be created. Ask the system administrator to check folder permissions.';
}

function save_parent_id_upload(array $file, string $uploadDir, int $maxBytes, array $allowedMimeTypes): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Please upload a clear JPG, PNG, or PDF copy of the parent/guardian ID with signature.');
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        throw new Exception('The parent/guardian ID must be smaller than 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimeTypes[$mime])) {
        throw new Exception('Invalid parent/guardian ID file type. Only JPG, PNG, and PDF files are allowed.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid file upload. Please try again.');
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mime];
    $target = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('The parent/guardian ID could not be saved. Please try again.');
    }

    @chmod($target, 0640);
    return $safeName;
}


if ($user['role'] !== 'student' && $user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'assign' && $user['role'] === 'admin') {
            $stmt = $pdo->prepare(
                "INSERT INTO think_sheets
                (student_id, violation_id, assigned_by, due_date)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $_POST['student_id'],
                $_POST['violation_id'] ?: null,
                $user['first_name'] . ' ' . $user['last_name'],
                $_POST['due_date'] ?: null
            ]);

            log_action(
                $pdo,
                'assigned',
                'Think Sheet',
                null,
                'Assigned a digital Think Sheet.'
            );

            $message = 'Think Sheet assigned successfully.';
        } elseif ($action === 'submit' && $user['role'] === 'student') {
            if (!$student) {
                throw new Exception(
                    'Your student profile is not linked to your account. Ask SASO to link it.'
                );
            }

            $parentName = trim($_POST['parent_guardian_name'] ?? '');
            $parentContact = trim($_POST['parent_contact_number'] ?? '');
            $reflection = trim($_POST['reflection'] ?? '');
            $why = trim($_POST['why_break_rule'] ?? '');
            $consequences = trim($_POST['consequences'] ?? '');
            $different = trim($_POST['what_differently'] ?? '');

            if ($parentName === '' || $parentContact === '' || $reflection === '' || $why === '' || $consequences === '' || $different === '') {
                throw new Exception('Please complete all Think Sheet fields.');
            }

            $check = $pdo->prepare("SELECT id, parent_id_file FROM think_sheets WHERE id = ? AND student_id = ? AND status = 'assigned' LIMIT 1");
            $check->execute([$_POST['id'], $student['id']]);
            $existing = $check->fetch();
            if (!$existing) {
                throw new Exception('This Think Sheet is no longer available for submission.');
            }

            $uploadedFile = save_parent_id_upload($_FILES['parent_id_file'] ?? [], $uploadDir, $maxUploadBytes, $allowedMimeTypes);

            $stmt = $pdo->prepare(
                "UPDATE think_sheets
                 SET parent_guardian_name = ?,
                     parent_contact_number = ?,
                     parent_id_file = ?,
                     responses = ?,
                     status = 'submitted',
                     submitted_date = NOW()
                 WHERE id = ?
                   AND student_id = ?
                   AND status = 'assigned'"
            );

            $stmt->execute([
                $parentName,
                $parentContact,
                $uploadedFile,
                json_encode([
                    'reflection' => $reflection,
                    'why_break_rule' => $why,
                    'consequences' => $consequences,
                    'what_differently' => $different
                ], JSON_UNESCAPED_UNICODE),
                $_POST['id'],
                $student['id']
            ]);

            if ($stmt->rowCount() !== 1) {
                @unlink($uploadDir . DIRECTORY_SEPARATOR . $uploadedFile);
                throw new Exception('This Think Sheet is no longer available for submission.');
            }

            if ($stmt->rowCount() !== 1) {
                throw new Exception(
                    'This Think Sheet is no longer available for submission.'
                );
            }

            log_action(
                $pdo,
                'submitted',
                'Think Sheet',
                $_POST['id'],
                'Student submitted Think Sheet.'
            );

            $message = 'Think Sheet submitted for SASO review.';
        } elseif ($action === 'review' && $user['role'] === 'admin') {
            $status = $_POST['decision'] === 'reviewed'
                ? 'reviewed'
                : 'submitted';

            $stmt = $pdo->prepare(
                "UPDATE think_sheets
                 SET status = ?,
                     reviewed_by = ?,
                     reviewed_date = IF(? = 'reviewed', NOW(), NULL),
                     review_notes = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $status,
                $user['first_name'] . ' ' . $user['last_name'],
                $status,
                trim($_POST['review_notes'] ?? ''),
                $_POST['id']
            ]);

            log_action(
                $pdo,
                'reviewed',
                'Think Sheet',
                $_POST['id'],
                'SASO reviewed Think Sheet.'
            );

            $message = 'Think Sheet review saved.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($user['role'] === 'student') {
    $rows = [];

    if ($student) {
        $stmt = $pdo->prepare(
            "SELECT
                t.*,
                v.category,
                v.description violation_desc
             FROM think_sheets t
             LEFT JOIN violations v ON v.id = t.violation_id
             WHERE t.student_id = ?
             ORDER BY t.created_at DESC"
        );

        $stmt->execute([$student['id']]);
        $rows = $stmt->fetchAll();
    }
} else {
    $rows = $pdo->query(
        "SELECT
            t.*,
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) student_name,
            v.category
         FROM think_sheets t
         JOIN students s ON s.id = t.student_id
         LEFT JOIN violations v ON v.id = t.violation_id
         ORDER BY t.created_at DESC"
    )->fetchAll();

    $students = $pdo->query(
        "SELECT
            id,
            student_number,
            first_name,
            last_name
         FROM students
         WHERE status = 'active'
         ORDER BY last_name, first_name"
    )->fetchAll();

    $violations = $pdo->query(
        "SELECT
            v.id,
            v.category,
            v.incident_date,
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) student_name
         FROM violations v
         JOIN students s ON s.id = v.student_id
         WHERE v.status <> 'dismissed'
         ORDER BY v.incident_date DESC"
    )->fetchAll();
}

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'admin'): ?>
    <div class="page-actions">
        <p class="muted">
            Assign, monitor, and review digital Think Sheets.
        </p>

        <button
            class="btn primary"
            onclick="openModal('assignModal')"
        >
            + Assign Think Sheet
        </button>
    </div>
<?php else: ?>
    <div class="page-actions">
        <p class="muted">
            Complete only the Think Sheets assigned to your account.
        </p>
    </div>
<?php endif; ?>

<section class="card">
    <table id="dataTable">
        <thead>
            <tr>
                <?php if ($user['role'] !== 'student'): ?>
                    <th>Student</th>
                <?php endif; ?>

                <th>Related Case</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php if ($user['role'] !== 'student'): ?>
                        <td>
                            <?= htmlspecialchars(
                                $r['student_number'] . ' - ' . $r['student_name']
                            ) ?>
                        </td>
                    <?php endif; ?>

                    <td>
                        <?= htmlspecialchars($r['category'] ?? 'General') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($r['due_date'] ?? '—') ?>
                    </td>

                    <td>
                        <span class="badge <?= $r['status'] === 'reviewed' ? 'green' : ($r['status'] === 'submitted' ? 'gold' : 'gold') ?>">
                            <?= htmlspecialchars($r['status']) ?>
                        </span>
                    </td>

                    <td>
                        <?php if (
                            $user['role'] === 'student' &&
                            $r['status'] === 'assigned'
                        ): ?>
                            <button
                                class="btn primary"
                                onclick='openThink(<?= json_encode($r) ?>)'
                            >
                                Complete
                            </button>

                        <?php elseif (
                            $user['role'] === 'admin' &&
                            $r['status'] === 'submitted'
                        ): ?>
                            <button
                                class="btn secondary"
                                onclick='openReview(<?= json_encode($r) ?>)'
                            >
                                Review
                            </button>

                        <?php elseif (
                            $user['role'] === 'admin' &&
                            $r['status'] === 'reviewed'
                        ): ?>
                            <button
                                class="btn secondary"
                                onclick='openReview(<?= json_encode($r) ?>)'
                            >
                                View
                            </button>

                        <?php else: ?>
                            <span class="muted">No action</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6">No Think Sheets found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php if ($user['role'] === 'admin'): ?>
    <div class="modal" id="assignModal">
        <div class="modal-box">
            <button
                class="close"
                onclick="closeModal('assignModal')"
            >
                ×
            </button>

            <h2>Assign Digital Think Sheet</h2>

            <form method="post" class="form-grid">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="assign"
                >

                <label class="wide">
                    Search Student

                    <span class="input-action-row">
                        <input
                            type="search"
                            id="studentSearch"
                            placeholder="Student number or name"
                            onkeydown="if (event.key === 'Enter') { event.preventDefault(); filterStudentOptions(); }"
                        >
                        <button
                            type="button"
                            class="btn secondary"
                            onclick="filterStudentOptions()"
                        >
                            Search
                        </button>
                    </span>

                    <span id="studentSearchStatus" class="hint"></span>
                </label>

                <label>
                    Student

                    <select name="student_id" id="assignStudent" required>
                        <option value="">Select student</option>

                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars(
                                    $s['student_number'] . ' - ' .
                                    $s['last_name'] . ', ' .
                                    $s['first_name']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Related Violation

                    <select name="violation_id">
                        <option value="">No specific case</option>

                        <?php foreach ($violations as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= htmlspecialchars(
                                    $v['student_number'] . ' - ' .
                                    $v['category'] . ' - ' .
                                    $v['incident_date']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Due Date

                    <input
                        type="datetime-local"
                        name="due_date"
                    >
                </label>

                <div class="form-end">
                    <button class="btn primary">
                        Assign
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'student'): ?>
    <div class="modal" id="thinkModal">
        <div class="modal-box">
            <button
                class="close"
                onclick="closeModal('thinkModal')"
            >
                ×
            </button>

            <h2>Think Sheet Reflection</h2>

            <p class="hint">
                Answer honestly and respectfully. Your responses will be reviewed by SASO.
            </p>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="submit"
                >

                <input
                    type="hidden"
                    name="id"
                    id="thinkId"
                >

                <label>
                    What rule(s) did I violate?
                    <textarea name="reflection" rows="4" required></textarea>
                </label>

                <label>
                    Why did I break the rule(s)?
                    <textarea name="why_break_rule" rows="4" required></textarea>
                </label>

                <label>
                    What are the consequences of my actions?
                    <textarea name="consequences" rows="4" required></textarea>
                </label>

                <label>
                    What could I have done differently?
                    <textarea name="what_differently" rows="4" required></textarea>
                </label>

                <label>
                    Parents/Guardian's Name
                    <input type="text" name="parent_guardian_name" required maxlength="200">
                </label>

                <label>
                    Parent's Contact Number
                    <input type="text" name="parent_contact_number" required maxlength="50">
                </label>

                <label class="wide">
                    Upload Photocopy of Parent/Guardian ID with Signature
                    <input type="file" name="parent_id_file" accept="image/jpeg,image/png,application/pdf" required>
                    <span class="hint">JPG, PNG, or PDF only. Maximum file size: 5 MB.</span>
                </label>

                <button class="btn primary">
                    Submit Think Sheet
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'admin'): ?>
    <div class="modal" id="reviewModal">
        <div class="modal-box">
            <button
                class="close"
                onclick="closeModal('reviewModal')"
            >
                ×
            </button>

            <h2>Review Think Sheet</h2>

            <div id="reviewText" class="hint"></div>

            <form method="post">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="review"
                >

                <input
                    type="hidden"
                    name="id"
                    id="reviewId"
                >

                <label>
                    Review Notes

                    <textarea
                        name="review_notes"
                        rows="4"
                    ></textarea>
                </label>

                <label>
                    Decision

                    <select name="decision">
                        <option value="reviewed">
                            Mark as Reviewed
                        </option>

                        <option value="submitted">
                            Needs Follow-up
                        </option>
                    </select>
                </label>

                <button class="btn primary">
                    Save Review
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function filterStudentOptions() {
        const search = document.getElementById('studentSearch');
        const select = document.getElementById('assignStudent');
        const status = document.getElementById('studentSearchStatus');

        if (!search || !select || !status) {
            return;
        }

        const query = search.value.trim().toLowerCase();
        let matches = 0;

        Array.from(select.options).forEach((option, index) => {
            if (index === 0) {
                return;
            }

            const matchesQuery = !query || option.textContent.toLowerCase().includes(query);
            option.hidden = !matchesQuery;
            if (matchesQuery) {
                matches++;
            }
        });

        if (select.value && select.options[select.selectedIndex].hidden) {
            select.value = '';
        }

        status.textContent = query
            ? `${matches} student${matches === 1 ? '' : 's'} found.`
            : '';
    }

    function openThink(r) {
        document.getElementById('thinkId').value = r.id;
        openModal('thinkModal');
    }

    function openReview(r) {
        document.getElementById('reviewId').value = r.id;

        let d = {};

        try {
            d = JSON.parse(r.responses || '{}');
        } catch (e) {}

        let parentIdLink = r.parent_id_file
            ? '<br><br><b>Parent/Guardian ID:</b> <a href="download_parent_id.php?id=' + encodeURIComponent(r.id) + '" target="_blank" rel="noopener">View uploaded ID</a>'
            : '<br><br><b>Parent/Guardian ID:</b> —';

        document.getElementById('reviewText').innerHTML =
            '<b>Parent/Guardian:</b> ' + escapeHtml(r.parent_guardian_name || '—') +
            '<br><b>Contact Number:</b> ' + escapeHtml(r.parent_contact_number || '—') +
            '<br><br><b>What rule(s) did I violate?</b> ' + escapeHtml(d.reflection || '—') +
            '<br><br><b>Why did I break the rule(s)?</b> ' + escapeHtml(d.why_break_rule || '—') +
            '<br><br><b>What are the consequences of my actions?</b> ' + escapeHtml(d.consequences || '—') +
            '<br><br><b>What could I have done differently?</b> ' + escapeHtml(d.what_differently || '—') +
            parentIdLink;

        openModal('reviewModal');
    }

    function escapeHtml(s) {
        return String(s).replace(
            /[&<>'"]/g,
            c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            }[c])
        );
    }
</script>

<?php include 'includes/footer.php'; ?>