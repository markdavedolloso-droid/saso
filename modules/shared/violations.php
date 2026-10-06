
<?php
// Admin management page and student-owned discipline record view.

require_once 'config/database.php';
require_once 'includes/auth.php';
$departments = require 'config/department_options.php';

require_role(['admin', 'student']);

$user = current_user();
$isStudent = $user['role'] === 'student';

$page_title = $isStudent ? 'My Disciplinary Record' : 'Discipline Management';
$page_heading = $page_title;

$student = $isStudent ? get_student_for_user($pdo) : null;

$message = '';
$error = '';

if ($isStudent && $_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    exit('This page is read-only for student accounts.');
}

if (!$isStudent && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'create') {
            $status = $_POST['status'] ?? 'pending';
            if (!in_array($status, ['pending', 'cleared', 'dismissed'], true)) {
                throw new RuntimeException('Choose a valid discipline status.');
            }

            $studentId = trim((string) ($_POST['student_id'] ?? ''));
            $studentCheck = $pdo->prepare("SELECT id, profile_id FROM students WHERE id = ? AND status = 'active' LIMIT 1");
            $studentCheck->execute([$studentId]);
            $assignedStudent = $studentCheck->fetch();
            if (!$assignedStudent) {
                throw new RuntimeException('Choose a student from the search results.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO violations(
                    student_id,
                    violation_type,
                    category,
                    description,
                    incident_date,
                    location,
                    reported_by,
                    parent_contact_number,
                    sanction,
                    remarks,
                    status,
                    released_date
                ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
            );

            $stmt->execute([
                $studentId,
                $_POST['violation_type'],
                trim($_POST['category'] ?? 'Other'),
                trim($_POST['description'] ?? ''),
                $_POST['incident_date'],
                trim($_POST['location'] ?? ''),
                trim($_POST['reported_by'] ?? ''),
                trim($_POST['parent_contact_number'] ?? ''),
                trim($_POST['sanction'] ?? ''),
                trim($_POST['remarks'] ?? ''),
                $status,
                ($_POST['released_date'] ?? '') ?: null
            ]);

            $q = $pdo->prepare(
                'SELECT id FROM violations
                 WHERE student_id = ? AND incident_date = ?
                 ORDER BY created_at DESC
                 LIMIT 1'
            );

            $q->execute([
                $_POST['student_id'],
                $_POST['incident_date']
            ]);

            $id = $q->fetchColumn();

            log_action(
                $pdo,
                'created',
                'Discipline',
                $id,
                'Recorded a student discipline case.'
            );

            notify(
                $pdo,
                $assignedStudent['profile_id'],
                'New Disciplinary Record',
                'A new disciplinary case has been added to your student record. Sign in to review the details.',
                'violations.php'
            );

            $message = 'Discipline case recorded successfully.';

        } elseif ($action === 'update') {

            $status = $_POST['status'] ?? 'pending';
            if (!in_array($status, ['pending', 'cleared', 'dismissed'], true)) {
                throw new RuntimeException('Choose a valid discipline status.');
            }

            $resolved = in_array($status, ['cleared', 'dismissed'], true)
                ? date('Y-m-d H:i:s')
                : null;

            $stmt = $pdo->prepare(
                'UPDATE violations SET
                    violation_type = ?,
                    category = ?,
                    description = ?,
                    incident_date = ?,
                    location = ?,
                    reported_by = ?,
                    parent_contact_number = ?,
                    sanction = ?,
                    remarks = ?,
                    status = ?,
                    resolution_notes = ?,
                    resolved_date = ?,
                    released_date = ?
                WHERE id = ?'
            );

            $stmt->execute([
                $_POST['violation_type'],
                trim($_POST['category'] ?? ''),
                trim($_POST['description'] ?? ''),
                $_POST['incident_date'],
                trim($_POST['location'] ?? ''),
                trim($_POST['reported_by'] ?? ''),
                trim($_POST['parent_contact_number'] ?? ''),
                trim($_POST['sanction'] ?? ''),
                trim($_POST['remarks'] ?? ''),
                $status,
                trim($_POST['resolution_notes'] ?? ''),
                $resolved,
                ($_POST['released_date'] ?? '') ?: null,
                $_POST['id']
            ]);

            log_action(
                $pdo,
                'updated',
                'Discipline',
                $_POST['id'],
                'Updated discipline case status/details.'
            );

            $message = 'Discipline case updated successfully.';

        } elseif ($action === 'delete') {

            $id = trim((string)($_POST['id'] ?? ''));

            $stmt = $pdo->prepare(
                'SELECT id FROM violations WHERE id = ? LIMIT 1'
            );

            $stmt->execute([$id]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Disciplinary case not found.');
            }

            $stmt = $pdo->prepare(
                'DELETE FROM violations WHERE id = ?'
            );

            $stmt->execute([$id]);

            log_action(
                $pdo,
                'deleted',
                'Discipline',
                $id,
                'Removed discipline case.'
            );

            $message = 'Discipline case removed successfully.';
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$students = $isStudent
    ? []
    : $pdo->query(
        "SELECT id, student_number, first_name, last_name, department, course, year_level
         FROM students
         WHERE status = 'active'
         ORDER BY last_name"
    )->fetchAll();
if ($isStudent) {
    $rows = [];

    if ($student) {
        $stmt = $pdo->prepare(
            'SELECT
                id,
                violation_type,
                category,
                description,
                incident_date,
                location,
                reported_by,
                parent_contact_number,
                sanction,
                remarks,
                status,
                released_date,
                resolved_date,
                resolution_notes
             FROM violations
             WHERE student_id = ?
             ORDER BY incident_date DESC, created_at DESC'
        );

        $stmt->execute([$student['id']]);
        $rows = $stmt->fetchAll();
    }

} else {

    $rows = $pdo->query(
        "SELECT
            v.*,
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) student_name,
            s.department,
            s.course,
            s.year_level
         FROM violations v
         JOIN students s ON s.id = v.student_id
         ORDER BY v.created_at DESC"
    )->fetchAll();
}

$studentDisplayName = $student
    ? trim(
        $student['first_name'] . ' ' .
        ($student['middle_name'] ?? '') . ' ' .
        $student['last_name']
    )
    : '';

include 'includes/header.php';
?>

<?php if ($isStudent): ?>

    <div class="page-actions">
        <p class="muted">
            This page shows disciplinary records associated with your student account.
        </p>
    </div>

    <?php if (!$student): ?>

        <div class="alert danger">
            Your student account is not linked to a student record.
            Please contact SASO or the Registrar.
        </div>

    <?php else: ?>

        <section class="card">

            <p class="muted">
                <?= htmlspecialchars($student['department'] ?? '') ?>
                <?= !empty($student['course']) ? ' · ' . htmlspecialchars($student['course']) : '' ?>
                <?= !empty($student['year_level']) ? ' · ' . htmlspecialchars($student['year_level']) : '' ?>
            </p>

            <h3>My Disciplinary Record</h3>

            <?php if ($rows): ?>

                <style>
                    .student-case-row {
                        cursor: pointer;
                    }

                    .student-case-row:hover td {
                        background: var(--maroon3);
                    }

                    .student-case-row:focus {
                        outline: 2px solid var(--maroon);
                        outline-offset: -2px;
                    }

                    .case-detail-list {
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 14px 24px;
                        margin: 0;
                    }

                    .case-detail-list div {
                        min-width: 0;
                    }

                    .case-detail-list dt {
                        color: var(--muted);
                        font-size: 12px;
                        font-weight: 700;
                    }

                    .case-detail-list dd {
                        margin: 5px 0 0;
                        overflow-wrap: anywhere;
                    }

                    @media (max-width: 640px) {
                        .case-detail-list {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>

                <div class="table-wrap">

                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Violation</th>
                                <th>Type</th>
                                <th>Parent/Guardian Contact</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Date Released</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($rows as $caseIndex => $r): ?>

                                <tr
                                    class="student-case-row"
                                    tabindex="0"
                                    role="button"
                                    aria-label="View disciplinary case details"
                                    data-record-index="<?= (int) $caseIndex ?>"
                                >
                                    <td>
                                        <?= htmlspecialchars($r['incident_date']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($r['description'] ?? '') ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $r['violation_type'] === 'major' ? 'red' : 'gold' ?>">
                                            <?= htmlspecialchars(ucfirst($r['violation_type'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($r['parent_contact_number'] ?: '—') ?>
                                    </td>

                                    <td>
                                        <?= nl2br(htmlspecialchars($r['remarks'] ?: $r['sanction'] ?: '—')) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $r['status'] === 'cleared' ? 'green' : ($r['status'] === 'dismissed' ? 'red' : 'gold') ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['status']))) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $r['released_date'] ?: (
                                                $r['resolved_date']
                                                    ? substr($r['resolved_date'], 0, 10)
                                                    : '—'
                                            )
                                        ) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>

                </div>

            <?php else: ?>

                <p class="muted">
                    No disciplinary records are currently associated with your account.
                </p>

            <?php endif; ?>

        </section>

        <?php if ($rows): ?>

            <div
                class="modal"
                id="studentCaseModal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="studentCaseModalTitle"
            >

                <div class="modal-box">

                    <button
                        class="close"
                        type="button"
                        onclick="closeModal('studentCaseModal')"
                        aria-label="Close"
                    >
                        ×
                    </button>

                    <h2 id="studentCaseModalTitle">
                        Disciplinary Case Details
                    </h2>

                    <dl
                        class="case-detail-list"
                        id="studentCaseDetails"
                    ></dl>

                </div>
            </div>

            <script>
                const studentDisciplineCases = <?= json_encode(
                    $rows,
                    JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                ) ?>;

                const studentDisciplineMeta = {
                    name: <?= json_encode(
                        $studentDisplayName,
                        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                    ) ?>,

                    department: <?= json_encode(
                        $student['department'] ?? '',
                        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                    ) ?>,

                    course: <?= json_encode(
                        $student['course'] ?? '',
                        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                    ) ?>,

                    yearLevel: <?= json_encode(
                        $student['year_level'] ?? '',
                        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                    ) ?>
                };

                function showStudentDisciplineDetails(record) {
                    const list = document.getElementById('studentCaseDetails');

                    list.replaceChildren();

                    const releasedDate = record.released_date ||
                        (record.resolved_date ? record.resolved_date.slice(0, 10) : '');
                    const offenseType = record.violation_type
                        ? record.violation_type.charAt(0).toUpperCase() + record.violation_type.slice(1).toLowerCase()
                        : '';
                    const status = record.status
                        ? record.status.replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase())
                        : '';

                    const fields = [
                        ['Date', record.incident_date],
                        ['Student Name', studentDisciplineMeta.name],
                        ['Department', studentDisciplineMeta.department],
                        [
                            'Course and Year Level',
                            [
                                studentDisciplineMeta.course,
                                studentDisciplineMeta.yearLevel
                            ].filter(Boolean).join(' · ')
                        ],
                        ['Parent/Guardian Contact Number', record.parent_contact_number],
                        [
                            'Violation',
                            record.description
                        ],
                        ['Type of Offense (Major/Minor)', offenseType],
                        ['Remarks', record.remarks || record.sanction],
                        ['Reported By', record.reported_by],
                        ['Status', status],
                        ['Date Released', releasedDate]
                    ];

                    fields.forEach(([label, value]) => {
                        const item = document.createElement('div');
                        const term = document.createElement('dt');
                        const description = document.createElement('dd');

                        term.textContent = label;
                        description.textContent = value || '—';

                        item.append(term, description);
                        list.append(item);
                    });

                    openModal('studentCaseModal');
                }

                document.querySelectorAll('.student-case-row').forEach(row => {
                    const openDetails = () => {
                        showStudentDisciplineDetails(
                            studentDisciplineCases[Number(row.dataset.recordIndex)]
                        );
                    };

                    row.addEventListener('click', openDetails);

                    row.addEventListener('keydown', event => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openDetails();
                        }
                    });
                });
            </script>

        <?php endif; ?>

    <?php endif; ?>

<?php else: ?>

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

    <div class="page-actions">

        <p class="muted">
            Record cases as Pending, Cleared, or Dismissed. Resolution details are retained for reporting.
        </p>

        <button
            class="btn primary"
            onclick="openModal('violationModal')"
        >
            + Record Violation
        </button>

    </div>

    <section class="card">

        <div class="search">
            <input
                id="tableSearch"
                placeholder="Search discipline records..."
            >
        </div>

        <div class="table-wrap">

            <table id="dataTable">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Department</th>
                        <th>Course / Year</th>
                        <th>Parent Contact</th>
                        <th>Violation</th>
                        <th>Offense</th>
                        <th>Remarks</th>
                        <th>Reported By</th>
                        <th>Status</th>
                        <th>Date Released</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($rows as $r): ?>

                        <tr>
                            <td>
                                <?= htmlspecialchars($r['incident_date']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['student_number'] . ' - ' . $r['student_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['department'] ?: '—') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(trim($r['course'] . ' ' . $r['year_level'])) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['parent_contact_number'] ?: '—') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['description'] ?? '') ?>
                            </td>

                            <td>
                                <span class="badge <?= $r['violation_type'] === 'major' ? 'red' : 'gold' ?>">
                                    <?= htmlspecialchars(ucfirst($r['violation_type'])) ?>
                                </span>
                            </td>

                            <td>
                                <?= nl2br(htmlspecialchars($r['remarks'] ?: $r['sanction'] ?: '—')) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['reported_by'] ?: '—') ?>
                            </td>

                            <td>
                                <span class="badge <?= $r['status'] === 'cleared' ? 'green' : ($r['status'] === 'dismissed' ? 'red' : 'gold') ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $r['status'])) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $r['released_date'] ?: (
                                        $r['resolved_date']
                                            ? substr($r['resolved_date'], 0, 10)
                                            : '—'
                                    )
                                ) ?>
                            </td>

                            <td class="discipline-actions">
                                <button
                                    class="btn secondary"
                                    type="button"
                                    onclick='editViolation(<?= json_encode($r) ?>)'
                                >
                                    Edit
                                </button>

                                <form
                                    method="post"
                                    data-confirmation="Remove this discipline case? Linked Think Sheet records will be retained."
                                >
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <button class="btn secondary" type="submit">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="12">No discipline records found.</td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <div class="modal" id="violationModal">

        <div class="modal-box">

            <button
                class="close"
                onclick="closeModal('violationModal')"
            >
                ×
            </button>

            <h2 id="violationTitle">Record Violation</h2>

            <form
                method="post"
                class="form-grid"
                data-confirm-create="Are you sure you want to record this disciplinary case?"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    id="violationAction"
                    value="create"
                >

                <input
                    type="hidden"
                    name="id"
                    id="violationId"
                >

                <input type="hidden" name="category" id="category" value="Other">
                <input type="hidden" name="location" id="location">
                <input type="hidden" name="sanction" id="sanction">
                <input type="hidden" name="resolution_notes" id="resolution_notes">

                <label>
                    Date
                    <input type="date" name="incident_date" id="incident_date" value="<?= date('Y-m-d') ?>" required>
                </label>

                <label>
                    Student Name

                    <div class="student-picker" id="disciplineStudentPicker">
                        <input
                            type="search"
                            id="studentSearch"
                            placeholder="Search student name or number..."
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="studentSearchResults"
                            required
                        >
                        <div class="student-picker-results" id="studentSearchResults" role="listbox" hidden>
                        <?php foreach ($students as $s): ?>
                            <button
                                type="button"
                                class="student-picker-option"
                                role="option"
                                data-student-id="<?= htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8') ?>"
                                data-student-label="<?= htmlspecialchars($s['student_number'] . ' - ' . $s['last_name'] . ', ' . $s['first_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-department="<?= htmlspecialchars($s['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-course="<?= htmlspecialchars($s['course'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-year-level="<?= htmlspecialchars($s['year_level'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars(
                                    $s['student_number'] . ' - ' .
                                    $s['last_name'] . ', ' .
                                    $s['first_name']
                                ) ?>
                            </button>
                        <?php endforeach; ?>
                            <p class="student-picker-empty" id="studentSearchEmpty" hidden>No matching students.</p>
                        </div>
                    </div>
                    <input type="hidden" name="student_id" id="student_id">
                </label>

                <label>
                    Department
                    <select id="studentDepartment" aria-label="Filter students by department">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $departmentCode => $departmentLabel): ?>
                            <option value="<?= htmlspecialchars($departmentCode, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($departmentLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Course and Year Level
                    <input type="text" id="studentCourseYear" readonly>
                </label>

                <label>
                    Type of Offense (Major/Minor)
                    <select name="violation_type" id="violation_type">
                        <option value="minor">Minor</option>
                        <option value="major">Major</option>
                    </select>
                </label>

                <label>
                    Parent/Guardian Contact Number
                    <input
                        type="tel"
                        name="parent_contact_number"
                        id="parent_contact_number"
                    >
                </label>

                <label>
                    Reported By
                    <input name="reported_by" id="reported_by">
                </label>

                <label class="wide">
                    Violation
                    <textarea name="description" id="description" required></textarea>
                </label>

                <label class="wide">
                    Remarks
                    <textarea name="remarks" id="remarks"></textarea>
                </label>

                <label>
                    Status

                    <select name="status" id="caseStatus">
                        <option value="pending">Pending</option>
                        <option value="cleared">Cleared</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </label>

                <label>
                    Date Released
                    <input
                        type="date"
                        name="released_date"
                        id="released_date"
                    >
                </label>

                <div class="form-end">
                    <button class="btn primary">
                        Save Case
                    </button>
                </div>

            </form>

        </div>

    </div>

    <script>
        const studentSearch = document.getElementById('studentSearch');
        const studentIdInput = document.getElementById('student_id');
        const studentSearchResults = document.getElementById('studentSearchResults');
        const studentSearchEmpty = document.getElementById('studentSearchEmpty');
        const studentDepartmentSelect = document.getElementById('studentDepartment');
        const studentOptions = Array.from(document.querySelectorAll('.student-picker-option'));
        const departmentOptions = <?= json_encode($departments, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        function normalizeDepartment(value) {
            return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        function getDepartmentCode(department) {
            const normalized = normalizeDepartment(department);
            return Object.entries(departmentOptions).find(([code, label]) => {
                const fullName = label.match(/\(([^)]+)\)$/)?.[1] || label;
                return [code, label, fullName].some(alias => normalizeDepartment(alias) === normalized);
            })?.[0] || '';
        }

        function updateStudentDetails(student) {
            const course = student?.dataset.course || student?.course || '';
            const yearLevel = student?.dataset.yearLevel || student?.year_level || '';
            const department = student?.dataset.department || student?.department || '';

            if (student) {
                studentDepartmentSelect.value = getDepartmentCode(department);
            }
            document.getElementById('studentCourseYear').value = [course, yearLevel].filter(Boolean).join(' · ');
        }

        function filterStudentOptions() {
            const query = studentSearch.value.trim().toLowerCase();
            const selectedDepartment = studentDepartmentSelect.value;
            const selectedLabel = departmentOptions[selectedDepartment] || '';
            const selectedFullName = selectedLabel.match(/\(([^)]+)\)$/)?.[1] || selectedLabel;
            const selectedAliases = [selectedDepartment, selectedLabel, selectedFullName];
            let visibleCount = 0;

            studentOptions.forEach(option => {
                const matches = option.dataset.studentLabel.toLowerCase().includes(query)
                    && (!selectedDepartment || selectedAliases.some(alias =>
                        normalizeDepartment(alias) === normalizeDepartment(option.dataset.department)
                    ));
                option.hidden = !matches;
                if (matches) visibleCount++;
            });

            studentSearchEmpty.hidden = visibleCount > 0;
            studentSearchResults.hidden = false;
            studentSearch.setAttribute('aria-expanded', 'true');
        }

        function closeStudentOptions() {
            studentSearchResults.hidden = true;
            studentSearch.setAttribute('aria-expanded', 'false');
        }

        studentSearch.addEventListener('focus', filterStudentOptions);
        studentDepartmentSelect.addEventListener('change', () => {
            studentSearch.value = '';
            studentIdInput.value = '';
            studentSearch.setCustomValidity('Choose a student from the search results.');
            updateStudentDetails(null);
            studentSearch.focus();
            filterStudentOptions();
        });
        studentSearch.addEventListener('input', () => {
            studentIdInput.value = '';
            studentSearch.setCustomValidity('Choose a student from the search results.');
            updateStudentDetails(null);
            filterStudentOptions();
        });

        studentSearch.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                filterStudentOptions();
                studentOptions.find(option => !option.hidden)?.focus();
            } else if (event.key === 'Escape') {
                closeStudentOptions();
            }
        });

        studentOptions.forEach(option => {
            option.addEventListener('click', () => {
                studentIdInput.value = option.dataset.studentId;
                studentSearch.value = option.dataset.studentLabel;
                studentSearch.setCustomValidity('');
                studentDepartmentSelect.value = option.dataset.department || '';
                updateStudentDetails(option);
                studentSearch.focus();
                closeStudentOptions();
            });
        });

        document.addEventListener('click', event => {
            if (!event.target.closest('#disciplineStudentPicker')) {
                closeStudentOptions();
            }
        });

        function editViolation(v) {
            document.getElementById('violationTitle').textContent = 'Manage Discipline Case';

            document.getElementById('violationAction').value = 'update';
            document.getElementById('violationId').value = v.id;

            [
                'student_id',
                'violation_type',
                'category',
                'incident_date',
                'description',
                'location',
                'reported_by',
                'parent_contact_number',
                'sanction',
                'remarks',
                'released_date',
                'resolution_notes'
            ].forEach(k => {
                const e = document.getElementById(k);

                if (e) {
                    e.value = v[k] ?? '';
                }
            });

            document.getElementById('caseStatus').value = v.status;
            const studentOption = studentOptions.find(option => option.dataset.studentId === v.student_id);
            studentSearch.value = studentOption?.dataset.studentLabel || v.student_name || '';
            studentSearch.setCustomValidity('');
            updateStudentDetails(studentOption || v);
            closeStudentOptions();

            openModal('violationModal');
        }
    </script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>