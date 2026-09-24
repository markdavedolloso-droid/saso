<?php
// Dashboard entry point based on the logged-in user role.
// It loads role-specific counts and recent activity for admin, registrar, or student users.
require_once "config/database.php";
require_once "includes/auth.php";
require_once "includes/analytics_helper.php";

require_login();

$user = current_user();
$role = $user['role'];
$page_title = $role === 'admin' ? 'SASO Dashboard' : ($role === 'registrar' ? 'Registrar Dashboard' : 'Student Dashboard');
$page_heading = $page_title;
$student = get_student_for_user($pdo);

if ($role === 'student') {
    $studentId = $student['id'] ?? '';
    $think = $moral = $eval = 0;
    $availableTeachers = $pdo->query("SELECT id, employee_number, CONCAT(first_name, ' ', last_name) name FROM faculty WHERE status='active' ORDER BY last_name, first_name")->fetchAll();

    if ($studentId) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM think_sheets WHERE student_id=? AND status<>'reviewed'");
        $s->execute([$studentId]);
        $think = (int) $s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM good_moral_requests WHERE student_id=? AND status NOT IN ('completed','rejected')");
        $s->execute([$studentId]);
        $moral = (int) $s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM teacher_evaluations WHERE student_id=? AND status='submitted'");
        $s->execute([$studentId]);
        $eval = (int) $s->fetchColumn();

        $recent = [];
        $s = $pdo->prepare("SELECT 'Think Sheet' item, status, created_at FROM think_sheets WHERE student_id=? UNION ALL SELECT 'Good Moral Request', status, created_at FROM good_moral_requests WHERE student_id=? ORDER BY created_at DESC LIMIT 8");
        $s->execute([$studentId, $studentId]);
        $recent = $s->fetchAll();
    } else {
        $recent = [];
    }
} elseif ($role === 'registrar') {
    $counts = [];
    foreach (['pending_registrar' => 'Needs Registrar Verification', 'pending_saso' => 'Needs SASO Verification', 'saso_verified' => 'SASO Verified', 'registrar_processing' => 'Processing', 'completed' => 'Completed'] as $status => $label) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM good_moral_requests WHERE status=?");
        $s->execute([$status]);
        $counts[$status] = (int) $s->fetchColumn();
    }

    $recent = $pdo->query("SELECT g.*,s.student_number,CONCAT(s.first_name,' ',s.last_name) student_name FROM good_moral_requests g JOIN students s ON s.id=g.student_id ORDER BY g.created_at DESC LIMIT 8")->fetchAll();
} else {
    $counts = [];
    foreach (['students', 'faculty', 'violations', 'think_sheets', 'good_moral_requests', 'teacher_evaluations'] as $table) {
        $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }

    $pendingViolations = (int) $pdo->query("SELECT COUNT(*) FROM violations WHERE status IN ('pending','under_review')")->fetchColumn();
    $pendingThink = (int) $pdo->query("SELECT COUNT(*) FROM think_sheets WHERE status='submitted'")->fetchColumn();
    $pendingMoral = (int) $pdo->query("SELECT COUNT(*) FROM good_moral_requests WHERE status='pending_saso'")->fetchColumn();
    $recent = $pdo->query("SELECT v.*,CONCAT(s.first_name,' ',s.last_name) student_name FROM violations v JOIN students s ON s.id=v.student_id ORDER BY v.created_at DESC LIMIT 6")->fetchAll();
    $dashboardComments = $pdo->query("SELECT e.general_comment,es.comment FROM teacher_evaluations e LEFT JOIN evaluation_scores es ON es.evaluation_id=e.id WHERE e.status='submitted'")->fetchAll();
    $dashboardFeedback = [];
    foreach ($dashboardComments as $comment) {
        if (trim($comment['general_comment'] ?? '')) {
            $dashboardFeedback[] = $comment['general_comment'];
        }
        if (trim($comment['comment'] ?? '')) {
            $dashboardFeedback[] = $comment['comment'];
        }
    }
    $dashboardAnalysis = analyzeComments($dashboardFeedback);
}

include "includes/header.php";
?>

<?php if ($role === 'student') : ?>
    <div class="hero">
        <div>
            <span class="eyebrow">CRMC • STUDENT PORTAL</span>
            <h1>Welcome, <?= htmlspecialchars($user['first_name']) ?></h1>
            <p>Track your Think Sheets, Good Moral request, and teacher evaluations in one place.</p>
        </div>
        <div class="hero-mark">STU</div>
    </div>

    <div class="stats">
        <div class="stat"><span>Pending Think Sheets</span><strong><?= $think ?></strong><small>Need your attention</small></div>
        <div class="stat"><span>Good Moral</span><strong><?= $moral ?></strong><small>Active requests</small></div>
        <div class="stat"><span>Evaluations</span><strong><?= $eval ?></strong><small>Submitted</small></div>
        <div class="stat"><span>Student Number</span><strong class="small-stat"><?= htmlspecialchars($student['student_number'] ?? '—') ?></strong><small><?= htmlspecialchars(($student['course'] ?? '') . ' ' . ($student['year_level'] ?? '')) ?></small></div>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="card-head">
                <h3>My Recent Activities</h3>
                <a href="think_sheets.php">Think Sheets</a>
            </div>
            <table>
                <thead>
                    <tr><th>Module</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r) : ?>
                        <tr>
                            <td><?= htmlspecialchars($r['item']) ?></td>
                            <td><span class="badge gold"><?= htmlspecialchars(str_replace('_', ' ', $r['status'])) ?></span></td>
                            <td><?= htmlspecialchars($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent) : ?><tr><td colspan="3">No activity yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h3>Quick Actions</h3>
            <div class="quick">
                <a href="good_moral.php">+ Request Good Moral</a>
                <a href="think_sheets.php">View Think Sheets</a>
                <a href="evaluations.php">Teacher Evaluation</a>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-head">
            <h3>Available Teachers to Evaluate</h3>
            <a href="evaluations.php">Open Evaluations</a>
        </div>
        <table>
            <thead>
                <tr><th>Employee Number</th><th>Teacher</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($availableTeachers as $teacher) : ?>
                    <tr>
                        <td><?= htmlspecialchars($teacher['employee_number']) ?></td>
                        <td><?= htmlspecialchars($teacher['name']) ?></td>
                        <td><a class="btn secondary" href="evaluations.php">Evaluate</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$availableTeachers) : ?><tr><td colspan="3">No active teachers are available for evaluation.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
<?php elseif ($role === 'registrar') : ?>
    <div class="hero compact">
        <div>
            <span class="eyebrow">REGISTRAR OFFICE</span>
            <h1>Registrar Office</h1>
            <p>Manage official student records and process Good Moral requests through Registrar and SASO verification.</p>
        </div>
        <div class="hero-mark">REG</div>
    </div>

    <div class="stats">
        <div class="stat"><span>Needs Registrar Verification</span><strong><?= $counts['pending_registrar'] ?></strong></div>
        <div class="stat"><span>Needs SASO Verification</span><strong><?= $counts['pending_saso'] ?></strong></div>
        <div class="stat"><span>SASO Verified</span><strong><?= $counts['saso_verified'] ?></strong></div>
        <div class="stat"><span>Registrar Processing</span><strong><?= $counts['registrar_processing'] ?></strong></div>
        <div class="stat"><span>Completed</span><strong><?= $counts['completed'] ?></strong></div>
    </div>

    <section class="card">
        <div class="card-head">
            <h3>Recent Good Moral Requests</h3>
            <a href="students.php">Student Records</a> <a href="good_moral.php">Manage</a>
        </div>
        <table>
            <thead>
                <tr><th>Student</th><th>Purpose</th><th>Status</th><th>Requested</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r) : ?>
                    <tr>
                        <td><?= htmlspecialchars($r['student_number'] . ' - ' . $r['student_name']) ?></td>
                        <td><?= htmlspecialchars($r['purpose']) ?></td>
                        <td><span class="badge <?= good_moral_badge_class($r['status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $r['status'])) ?></span></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php else : ?>
    <div class="hero">
        <div>
            <span class="eyebrow">CRMC • SASO</span>
            <h1>Student Affairs & Services Office</h1>
            <p>Centralized management of discipline, digital Think Sheets, Good Moral verification, teacher evaluations, and AI-assisted feedback analysis.</p>
        </div>
        <div class="hero-mark">SASO</div>
    </div>

    <div class="stats">
        <div class="stat"><span>Students</span><strong><?= $counts['students'] ?></strong></div>
        <div class="stat"><span>Faculty</span><strong><?= $counts['faculty'] ?></strong></div>
        <div class="stat"><span>Open Discipline Cases</span><strong><?= $pendingViolations ?></strong></div>
        <div class="stat"><span>Think Sheets to Review</span><strong><?= $pendingThink ?></strong></div>
        <div class="stat"><span>Good Moral Verification</span><strong><?= $pendingMoral ?></strong></div>
        <div class="stat"><span>Evaluations</span><strong><?= $counts['teacher_evaluations'] ?></strong></div>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="card-head">
                <h3>Recent Discipline Records</h3>
                <a href="violations.php">View all</a>
            </div>
            <table>
                <thead>
                    <tr><th>Student</th><th>Type</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r) : ?>
                        <tr>
                            <td><?= htmlspecialchars($r['student_name']) ?></td>
                            <td><span class="badge <?= $r['violation_type'] === 'major' ? 'red' : 'gold' ?>"><?= htmlspecialchars($r['violation_type']) ?></span></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $r['status'])) ?></td>
                            <td><?= htmlspecialchars($r['incident_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h3>Quick Actions</h3>
            <div class="quick">
                <a href="violations.php">+ Record Violation</a>
                <a href="think_sheets.php">+ Assign Think Sheet</a>
                <a href="good_moral.php">Good Moral Verification</a>
                <a href="evaluations.php">View Evaluations</a>
                <a href="analytics.php">Open AI Analytics</a>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h3>AI Analytics Result</h3><a href="analytics.php">View details</a></div>
        <p><strong>Feedback indicator:</strong> <?= htmlspecialchars($dashboardAnalysis['sentiment']) ?> · <?= $dashboardAnalysis['count'] ?> comments analyzed.</p>
        <p class="muted">Positive terms: <?= $dashboardAnalysis['positive'] ?> · Attention terms: <?= $dashboardAnalysis['negative'] ?></p>
        <div class="summary-grid"><div><h4>Common Strengths</h4><p><?= htmlspecialchars($dashboardAnalysis['strengths'] ? ucwords(implode(', ', array_keys(array_slice($dashboardAnalysis['strengths'], 0, 3, true)))) : 'No recurring strengths yet.') ?></p></div><div><h4>Areas for Improvement</h4><p><?= htmlspecialchars($dashboardAnalysis['improvements'] ? ucwords(implode(', ', array_keys(array_slice($dashboardAnalysis['improvements'], 0, 3, true)))) : 'No recurring improvement themes yet.') ?></p></div></div>
    </section>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
