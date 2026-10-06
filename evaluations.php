<?php

// Collects student evaluations and lets administrators review submitted feedback.

$page_title = 'Teacher Evaluations';
$page_heading = 'Teacher Performance Evaluation';

require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$user = current_user();
$student = get_student_for_user($pdo);
$message = '';
$error = '';

if (!in_array($user['role'], ['student', 'admin'], true)) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'student') {

    verify_csrf();

    try {

        $pdo->beginTransaction();

        if (!$student) {
            throw new Exception(
                'Your student profile is not linked to this account. Ask SASO to link it.'
            );
        }

        $faculty = $_POST['faculty_id'];

        $criteria = $pdo->query(
            "SELECT * FROM evaluation_criteria ORDER BY sort_order"
        )->fetchAll();

        $scores = [];
        $total = 0;

        foreach ($criteria as $c) {

            $score = (int)($_POST['score'][$c['id']] ?? 0);

            if ($score < 1 || $score > 5) {
                throw new Exception(
                    'Please rate every criterion from 1 to 5.'
                );
            }

            $scores[$c['id']] = $score;
            $total += $score;
        }

        $overall = round($total / count($criteria), 2);

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM teacher_evaluations
             WHERE faculty_id=?
             AND student_id=?
             AND academic_year=?
             AND semester=?"
        );

        $check->execute([
            $faculty,
            $student['id'],
            trim($_POST['academic_year']),
            trim($_POST['semester'])
        ]);

        if ($check->fetchColumn() > 0) {
            throw new Exception(
                'You already submitted an evaluation for this teacher for this academic period.'
            );
        }

        $stmt = $pdo->prepare(
            "INSERT INTO teacher_evaluations
            (
                faculty_id,
                student_id,
                evaluator_profile_id,
                academic_year,
                semester,
                overall_score,
                general_comment,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')"
        );

        $stmt->execute([
            $faculty,
            $student['id'],
            $user['id'],
            trim($_POST['academic_year']),
            trim($_POST['semester']),
            $overall,
            trim($_POST['general_comment'] ?? '')
        ]);

        $evaluationId = $pdo->lastInsertId();

        // MySQL CHAR(UUID()) does not return the inserted UUID via lastInsertId,
        // so retrieve it by the unique recent row.

        $q = $pdo->prepare(
            "SELECT id
             FROM teacher_evaluations
             WHERE faculty_id=?
             AND student_id=?
             ORDER BY created_at DESC
             LIMIT 1"
        );

        $q->execute([
            $faculty,
            $student['id']
        ]);

        $evaluationId = $q->fetchColumn();

        $ins = $pdo->prepare(
            "INSERT INTO evaluation_scores
            (
                evaluation_id,
                criteria_id,
                score,
                comment
            )
            VALUES (?, ?, ?, ?)"
        );

        foreach ($criteria as $c) {
            $ins->execute([
                $evaluationId,
                $c['id'],
                $scores[$c['id']],
                trim($_POST['comment'][$c['id']] ?? '')
            ]);
        }

        log_action(
            $pdo,
            'submitted',
            'Teacher Evaluation',
            $evaluationId,
            'Student submitted teacher evaluation and feedback.'
        );

        $pdo->commit();

        $message = 'Teacher evaluation submitted successfully.';

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$faculty = $pdo->query(
    "SELECT
        id,
        employee_number,
        CONCAT(first_name, ' ', last_name) name
     FROM faculty
     WHERE status='active'
     ORDER BY last_name, first_name"
)->fetchAll();

$criteria = $pdo->query(
    "SELECT * FROM evaluation_criteria ORDER BY sort_order"
)->fetchAll();

if ($user['role'] === 'student') {

    $rows = [];

    if ($student) {

        $stmt = $pdo->prepare(
            "SELECT
                e.*,
                CONCAT(f.first_name, ' ', f.last_name) faculty_name
             FROM teacher_evaluations e
             JOIN faculty f ON f.id=e.faculty_id
             WHERE e.student_id=?
             ORDER BY e.created_at DESC"
        );

        $stmt->execute([$student['id']]);
        $rows = $stmt->fetchAll();
    }

} else {

    $rows = $pdo->query(
        "SELECT
            e.*,
            CONCAT(f.first_name, ' ', f.last_name) faculty_name
         FROM teacher_evaluations e
         JOIN faculty f ON f.id=e.faculty_id
         ORDER BY e.created_at DESC"
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

<div class="page-actions">
    <p class="muted">
        <?php if ($user['role'] === 'student'): ?>
            Rate your teacher and leave an optional comment explaining what is
            working well or what could be improved.
        <?php else: ?>
            View evaluation scores and student feedback. Individual student
            comments are not shown to teachers.
        <?php endif; ?>
    </p>
</div>

<?php if ($user['role'] === 'student'): ?>

    <section class="card">

        <div class="card-head">
            <h3>Available Teachers to Evaluate</h3>

            <span class="badge gold">
                <?= count($faculty) ?> available
            </span>
        </div>

        <table>

            <thead>
                <tr>
                    <th>Employee Number</th>
                    <th>Teacher</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($faculty as $f): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($f['employee_number']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($f['name']) ?>
                        </td>

                        <td>
                            <button
                                class="btn secondary"
                                type="button"
                                onclick="evaluateTeacher('<?= htmlspecialchars($f['id'], ENT_QUOTES) ?>')"
                            >
                                Evaluate
                            </button>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$faculty): ?>

                    <tr>
                        <td colspan="3">
                            No active teachers are available for evaluation.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

<?php endif; ?>

<section class="card">

    <table>

        <thead>
            <tr>
                <th>Teacher</th>
                <th>Academic Year</th>
                <th>Semester</th>
                <th>Score</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($rows as $r): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($r['faculty_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($r['academic_year']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($r['semester']) ?>
                    </td>

                    <td>
                        <strong>
                            <?= number_format((float)$r['overall_score'], 2) ?> / 5
                        </strong>
                    </td>

                    <td>
                        <span class="badge green">
                            <?= htmlspecialchars($r['status']) ?>
                        </span>
                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$rows): ?>

                <tr>
                    <td colspan="5">
                        No evaluations submitted yet.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</section>

<?php if ($user['role'] === 'student'): ?>

    <div class="modal" id="evalModal">

        <div class="modal-box">

            <button
                class="close"
                onclick="closeModal('evalModal')"
            >
                ×
            </button>

            <h2>Teacher Evaluation</h2>

            <form method="post">

                <?= csrf_field() ?>

                <label>
                    Teacher

                    <select name="faculty_id" required>

                        <option value="">
                            Select teacher
                        </option>

                        <?php foreach ($faculty as $f): ?>

                            <option value="<?= $f['id'] ?>">
                                <?= htmlspecialchars(
                                    $f['employee_number'] . ' - ' . $f['name']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>

                <div class="form-grid">

                    <label>
                        Academic Year

                        <input
                            name="academic_year"
                            placeholder="2026-2027"
                            required
                        >
                    </label>

                    <label>
                        Semester

                        <select name="semester">

                            <option>
                                1st Semester
                            </option>

                            <option>
                                2nd Semester
                            </option>

                            <option>
                                Summer
                            </option>

                        </select>

                    </label>

                </div>

                <hr>

                <p class="hint">
                    <b>Rating:</b>
                    1 = Needs Significant Improvement,
                    5 = Excellent
                </p>

                <?php foreach ($criteria as $c): ?>

                    <div class="evaluation-item">

                        <label>

                            <?= htmlspecialchars($c['name']) ?>

                            <small>
                                <?= htmlspecialchars($c['description']) ?>
                            </small>

                            <select
                                name="score[<?= $c['id'] ?>]"
                                required
                            >

                                <option value="">
                                    Choose 1–5
                                </option>

                                <option value="1">
                                    1 - Needs Significant Improvement
                                </option>

                                <option value="2">
                                    2 - Needs Improvement
                                </option>

                                <option value="3">
                                    3 - Satisfactory
                                </option>

                                <option value="4">
                                    4 - Very Good
                                </option>

                                <option value="5">
                                    5 - Excellent
                                </option>

                            </select>

                            <textarea
                                name="comment[<?= $c['id'] ?>]"
                                rows="2"
                                placeholder="Optional comment about this criterion"
                            ></textarea>

                        </label>

                    </div>

                <?php endforeach; ?>

                <label>
                    Overall Student Feedback

                    <textarea
                        name="general_comment"
                        rows="5"
                        placeholder="What does the teacher do well? What could be improved? What support would help students?"
                    ></textarea>
                </label>

                <button class="btn primary full">
                    Submit Evaluation
                </button>

            </form>

        </div>

    </div>

<?php endif; ?>

<?php if ($user['role'] === 'student'): ?>

    <script>
        function evaluateTeacher(id) {
            document.querySelector(
                '#evalModal select[name="faculty_id"]'
            ).value = id;

            openModal('evalModal');
        }
    </script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
