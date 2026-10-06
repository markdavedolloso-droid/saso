<?php
// SASO-only repository for scanned paper Think Sheet records.
$page_title = 'Think Sheet Repository';
$page_heading = $page_title;

require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();
$user = current_user();
$isStudent = $user['role'] === 'student';
if (!$isStudent && $user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
$student = $isStudent ? get_student_for_user($pdo) : null;
$page_title = $isStudent ? 'My Think Sheet Records' : 'Think Sheet Repository';
$page_heading = $page_title;

$message = '';
$error = '';
$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'think_sheet_records';
$maxUploadBytes = 10 * 1024 * 1024;
$allowedMimeTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isStudent) {
        http_response_code(405);
        exit('Think Sheet records are read-only for students.');
    }
    verify_csrf();
    $action = $_POST['action'] ?? 'upload';

    if ($action === 'update_status') {
        try {
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['assigned', 'submitted', 'reviewed'], true)) {
                throw new RuntimeException('Choose a valid repository status.');
            }
            $stmt = $pdo->prepare('UPDATE think_sheet_records SET status = ? WHERE id = ?');
            $stmt->execute([$status, $_POST['id'] ?? '']);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT status FROM think_sheet_records WHERE id = ?');
                $check->execute([$_POST['id'] ?? '']);
                if ($check->fetchColumn() === false) {
                    throw new RuntimeException('Think Sheet record not found.');
                }
            }
            log_action($pdo, 'updated', 'Think Sheet Repository', $_POST['id'] ?? null, 'Updated repository status to ' . $status . '.');
            $message = 'Think Sheet status updated.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    } elseif ($action === 'upload') {
        $storedPath = null;
        try {
        $studentId = trim((string) ($_POST['student_id'] ?? ''));
        $recordedOn = trim((string) ($_POST['recorded_on'] ?? ''));
        $reportedBy = trim((string) ($_POST['reported_by'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $recordedOn);

        if (!$date || $date->format('Y-m-d') !== $recordedOn) {
            throw new RuntimeException('Enter a valid Think Sheet record date.');
        }
        if (function_exists('mb_strlen') ? mb_strlen($notes) > 3000 : strlen($notes) > 3000) {
            throw new RuntimeException('Notes must be 3,000 characters or fewer.');
        }
        if ($reportedBy === '' || (function_exists('mb_strlen') ? mb_strlen($reportedBy) > 150 : strlen($reportedBy) > 150)) {
            throw new RuntimeException('Enter who reported the Think Sheet (maximum 150 characters).');
        }

        $studentCheck = $pdo->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
        $studentCheck->execute([$studentId]);
        if (!$studentCheck->fetchColumn()) {
            throw new RuntimeException('Select a student record.');
        }

        $file = $_FILES['document_file'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose a PDF, JPG, or PNG scan of the paper Think Sheet.');
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxUploadBytes) {
            throw new RuntimeException('The scan must be smaller than 10 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('The uploaded document could not be validated. Please try again.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowedMimeTypes[$mime])) {
            throw new RuntimeException('Only PDF, JPG, or PNG scans are accepted.');
        }
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('The repository upload folder is unavailable.');
        }

        $filename = bin2hex(random_bytes(20)) . '.' . $allowedMimeTypes[$mime];
        $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            throw new RuntimeException('The scan could not be stored. Please try again.');
        }
        @chmod($storedPath, 0640);

        $recordId = $pdo->query('SELECT UUID()')->fetchColumn();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO think_sheet_records
                (id, student_id, violation_id, recorded_by, reported_by, recorded_on, document_file, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'submitted\', ?)'
        );
        $stmt->execute([
            $recordId,
            $studentId,
            null,
            $user['id'],
            $reportedBy,
            $recordedOn,
            $filename,
            $notes,
        ]);

        log_action($pdo, 'created', 'Think Sheet Repository', $recordId, 'Stored a paper Think Sheet scan.');
        $pdo->commit();
        $message = 'Think Sheet record added to the repository.';
        $storedPath = null;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($storedPath !== null && is_file($storedPath)) {
            @unlink($storedPath);
        }
        $error = $exception->getMessage();
    }
    }
}

$students = [];
if (!$isStudent) {
    $students = $pdo->query(
        "SELECT id, student_number, first_name, last_name, status
         FROM students
         ORDER BY last_name, first_name"
    )->fetchAll();
}
$recordSql = "SELECT r.id, r.recorded_on, r.document_file, r.status, r.reported_by, r.notes, r.created_at,
            s.student_number, s.first_name, s.middle_name, s.last_name, s.department, s.course, s.year_level,
            v.category AS violation_category, v.incident_date AS violation_date,
            p.first_name AS recorder_first_name, p.last_name AS recorder_last_name
     FROM think_sheet_records r
     JOIN students s ON s.id = r.student_id
     LEFT JOIN violations v ON v.id = r.violation_id
     LEFT JOIN profiles p ON p.id = r.recorded_by";
if ($isStudent) {
    $records = [];
    if ($student) {
        $stmt = $pdo->prepare($recordSql . ' WHERE r.student_id = ? ORDER BY r.recorded_on DESC, r.created_at DESC');
        $stmt->execute([$student['id']]);
        $records = $stmt->fetchAll();
    }
} else {
    $records = $pdo->query($recordSql . ' ORDER BY r.recorded_on DESC, r.created_at DESC')->fetchAll();
}

include 'includes/header.php';
?>
    <style>
        .think-sheet-record-row {
            cursor: pointer;
        }

        .think-sheet-record-row:hover td {
            background: var(--maroon3);
        }

        .think-sheet-record-row:focus {
            outline: 2px solid var(--maroon);
            outline-offset: -2px;
        }
    </style>

    <?php if ($message): ?>
    <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="page-actions">
    <p class="muted"><?= $isStudent ? 'View the Think Sheet scans and status associated with your student account.' : 'Store, review, and retrieve scans of paper Think Sheets.' ?></p>
    <?php if (!$isStudent): ?>
        <button class="btn primary" type="button" onclick="openModal('thinkSheetRecordModal')">Add Think Sheet Record</button>
    <?php endif; ?>
</div>

<section class="card">
    <div class="search"><input id="tableSearch" type="search" placeholder="Search student, date, or notes..."></div>
    <div class="table-wrap">
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Department</th>
                    <th>Course / Year Level</th>
                    <th>Reported By</th>
                    <?php if (!$isStudent): ?><th>Related Case</th><th>Notes</th><th>Stored By</th><?php endif; ?>
                    <th>Think Sheet Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr
                        class="think-sheet-record-row"
                        tabindex="0"
                        role="button"
                        aria-label="View Think Sheet scan"
                        data-preview-url="<?= htmlspecialchars('view_think_sheet_record.php?id=' . urlencode($record['id']) . '&modal=1', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td><?= htmlspecialchars($record['recorded_on'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(trim($record['first_name'] . ' ' . ($record['middle_name'] ?? '') . ' ' . $record['last_name']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($record['department'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(trim($record['course'] . ' · ' . $record['year_level']) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($record['reported_by'], ENT_QUOTES, 'UTF-8') ?></td>
                        <?php if (!$isStudent): ?>
                        <td>
                            <?php if ($record['violation_category']): ?>
                                <?= htmlspecialchars($record['violation_category'] . ' · ' . $record['violation_date'], ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="muted">Not linked</span>
                            <?php endif; ?>
                        </td>
                        <td><?= nl2br(htmlspecialchars($record['notes'] ?: '—', ENT_QUOTES, 'UTF-8')) ?></td>
                        <td><?= htmlspecialchars(trim(($record['recorder_first_name'] ?? '') . ' ' . ($record['recorder_last_name'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if (!$isStudent): ?>
                                <form method="post" class="repository-status-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($record['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <select name="status" aria-label="Think Sheet status" onchange="this.form.submit()">
                                        <option value="assigned" <?= $record['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                        <option value="submitted" <?= $record['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                        <option value="reviewed" <?= $record['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="badge <?= $record['status'] === 'reviewed' ? 'green' : 'gold' ?>"><?= htmlspecialchars(ucfirst($record['status']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr><td colspan="<?= $isStudent ? '6' : '9' ?>"><?= $isStudent ? 'No Think Sheet records are linked to your account.' : 'No Think Sheet scans have been added yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal think-sheet-preview-modal" id="thinkSheetPreviewModal" role="dialog" aria-modal="true" aria-labelledby="thinkSheetPreviewTitle">
    <div class="modal-box">
        <button class="close" type="button" onclick="closeModal('thinkSheetPreviewModal')" aria-label="Close preview">×</button>
        <h2 id="thinkSheetPreviewTitle">Think Sheet Preview</h2>
        <iframe class="think-sheet-preview-frame" id="thinkSheetPreviewFrame" data-modal-preview title="Scanned Think Sheet preview"></iframe>
    </div>
</div>

<div class="modal" id="thinkSheetRecordModal">
    <div class="modal-box">
        <button class="close" type="button" onclick="closeModal('thinkSheetRecordModal')" aria-label="Close">×</button>
        <h2>Add Paper Think Sheet to Repository</h2>
        <form method="post" enctype="multipart/form-data" class="form-grid" data-confirmation="Are you sure you want to add this Think Sheet record?">
            <?= csrf_field() ?>
            <label>Student
                <div class="student-picker" id="recordStudentPicker">
                    <input
                        type="search"
                        id="recordStudentSearch"
                        placeholder="Search student name or number..."
                        autocomplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="recordStudentSearchResults"
                        required
                    >
                    <div class="student-picker-results" id="recordStudentSearchResults" role="listbox" hidden>
                    <?php foreach ($students as $student): ?>
                        <?php $studentLabel = $student['student_number'] . ' - ' . $student['last_name'] . ', ' . $student['first_name'] . ($student['status'] === 'active' ? '' : ' (' . $student['status'] . ')'); ?>
                        <button
                            type="button"
                            class="student-picker-option"
                            role="option"
                            data-student-id="<?= htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8') ?>"
                            data-student-label="<?= htmlspecialchars($studentLabel, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <?= htmlspecialchars($studentLabel, ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                        <p class="student-picker-empty" id="recordStudentSearchEmpty" hidden>No matching students.</p>
                    </div>
                </div>
                <input type="hidden" name="student_id" id="recordStudentId">
            </label>
            <label>Record Date
                <input type="date" name="recorded_on" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Reported By
                <input type="text" name="reported_by" maxlength="150" required>
            </label>
            <label class="wide">Scanned Paper Think Sheet
                <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                <small class="muted">PDF, JPG, or PNG; maximum 10 MB.</small>
            </label>
            <label class="wide">Notes <span class="muted">(optional)</span>
                <textarea name="notes" rows="3" maxlength="3000" placeholder="Brief record notes; do not enter unnecessary sensitive details."></textarea>
            </label>
            <div class="form-end"><button class="btn primary" type="submit">Save to Repository</button></div>
        </form>
    </div>
</div>

<script>
    const recordStudentSearch = document.getElementById('recordStudentSearch');
    const recordStudentId = document.getElementById('recordStudentId');
    const recordStudentSearchResults = document.getElementById('recordStudentSearchResults');
    const recordStudentSearchEmpty = document.getElementById('recordStudentSearchEmpty');
    const recordStudentOptions = Array.from(document.querySelectorAll('#recordStudentSearchResults .student-picker-option'));

    function filterRecordStudentOptions() {
        const query = recordStudentSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        recordStudentOptions.forEach(option => {
            const matches = option.dataset.studentLabel.toLowerCase().includes(query);
            option.hidden = !matches;
            if (matches) visibleCount++;
        });

        recordStudentSearchEmpty.hidden = visibleCount > 0;
        recordStudentSearchResults.hidden = false;
        recordStudentSearch.setAttribute('aria-expanded', 'true');
    }

    function closeRecordStudentOptions() {
        recordStudentSearchResults.hidden = true;
        recordStudentSearch.setAttribute('aria-expanded', 'false');
    }

    recordStudentSearch.addEventListener('focus', filterRecordStudentOptions);
    recordStudentSearch.addEventListener('input', () => {
        recordStudentId.value = '';
        recordStudentSearch.setCustomValidity('Choose a student from the search results.');
        filterRecordStudentOptions();
    });
    recordStudentSearch.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            filterRecordStudentOptions();
            recordStudentOptions.find(option => !option.hidden)?.focus();
        } else if (event.key === 'Escape') {
            closeRecordStudentOptions();
        }
    });

    recordStudentOptions.forEach(option => {
        option.addEventListener('click', () => {
            recordStudentId.value = option.dataset.studentId;
            recordStudentSearch.value = option.dataset.studentLabel;
            recordStudentSearch.setCustomValidity('');
            recordStudentSearch.focus();
            closeRecordStudentOptions();
        });
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('#recordStudentPicker')) {
            closeRecordStudentOptions();
        }
    });

    const previewFrame = document.getElementById('thinkSheetPreviewFrame');
    document.querySelectorAll('.think-sheet-record-row').forEach(row => {
        const openPreview = () => {
            previewFrame.src = row.dataset.previewUrl;
            openModal('thinkSheetPreviewModal');
        };

        row.addEventListener('click', event => {
            if (!event.target.closest('a, button, input, select, textarea, form, label')) {
                openPreview();
            }
        });

        row.addEventListener('keydown', event => {
            if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                openPreview();
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
