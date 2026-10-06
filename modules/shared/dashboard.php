<?php
// Dashboard entry point based on the logged-in user role.
// It loads role-specific counts and recent activity for admin, registrar, or student users.
require_once "config/database.php";
require_once "includes/auth.php";

require_login();

$user = current_user();
$role = $user['role'];
$page_title = $role === 'admin' ? 'SASO Dashboard' : ($role === 'registrar' ? 'Registrar Dashboard' : 'Student Dashboard');
$page_heading = $page_title;
$student = get_student_for_user($pdo);

if ($role === 'student') {
    $studentId = $student['id'] ?? '';
    $discipline = $think = $moral = 0;
    $disciplineRecords = [];

    if ($studentId) {
        $s = $pdo->prepare('SELECT COUNT(*) FROM violations WHERE student_id=?');
        $s->execute([$studentId]);
        $discipline = (int) $s->fetchColumn();

        $s = $pdo->prepare(
            'SELECT incident_date, category, description, violation_type,
                parent_contact_number, remarks, sanction, status,
                released_date, resolved_date
             FROM violations
             WHERE student_id = ?
             ORDER BY incident_date DESC, created_at DESC'
        );
        $s->execute([$studentId]);
        $disciplineRecords = $s->fetchAll();

        $s = $pdo->prepare('SELECT COUNT(*) FROM think_sheet_records WHERE student_id=?');
        $s->execute([$studentId]);
        $think = (int) $s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM good_moral_requests WHERE student_id=? AND status NOT IN ('completed','rejected')");
        $s->execute([$studentId]);
        $moral = (int) $s->fetchColumn();

        $recent = [];
        $s = $pdo->prepare("SELECT 'Discipline Record' item, status, created_at FROM violations WHERE student_id=? UNION ALL SELECT 'Think Sheet Record', status, created_at FROM think_sheet_records WHERE student_id=? UNION ALL SELECT 'Good Moral Request', status, created_at FROM good_moral_requests WHERE student_id=? ORDER BY created_at DESC LIMIT 8");
        $s->execute([$studentId, $studentId, $studentId]);
        $recent = $s->fetchAll();
    } else {
        $recent = [];
    }
} elseif ($role === 'registrar') {
    $counts = array_fill_keys(['pending_saso', 'saso_verified', 'registrar_processing', 'completed'], 0);
    foreach ($pdo->query('SELECT status, COUNT(*) total FROM good_moral_requests GROUP BY status')->fetchAll() as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int) $row['total'];
        }
    }

    $recent = $pdo->query("SELECT g.*,s.student_number,CONCAT(s.first_name,' ',s.last_name) student_name FROM good_moral_requests g JOIN students s ON s.id=g.student_id ORDER BY g.created_at DESC LIMIT 8")->fetchAll();
} else {
    $counts = [];
    foreach (['students', 'violations', 'think_sheet_records', 'good_moral_requests'] as $table) {
        $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }

    $pendingViolations = (int) $pdo->query("SELECT COUNT(*) FROM violations WHERE status='pending'")->fetchColumn();
    $pendingMoral = (int) $pdo->query("SELECT COUNT(*) FROM good_moral_requests WHERE status='pending_saso'")->fetchColumn();
    $recent = $pdo->query("SELECT v.*,CONCAT(s.first_name,' ',s.last_name) student_name FROM violations v JOIN students s ON s.id=v.student_id ORDER BY v.created_at DESC LIMIT 6")->fetchAll();
}

include "includes/header.php";
?>

<?php if ($role === 'student') : ?>
    <div class="hero">
        <div>
            <span class="eyebrow">CRMC • STUDENT PORTAL</span>
            <h1>Welcome, <?= htmlspecialchars($user['first_name']) ?></h1>
            <p>Track your disciplinary records and Good Moral requests in one place.</p>
        </div>
        <div class="hero-mark"></div>
    </div>

    <div class="stats">
        <div class="stat"><span>Disciplinary Cases</span><strong><?= $discipline ?></strong><small>Records associated with your account</small></div>
        <div class="stat"><span>Think Sheet Records</span><strong><?= $think ?></strong><small>Stored for your student account</small></div>
        <div class="stat"><span>Good Moral</span><strong><?= $moral ?></strong><small>Active requests</small></div>
        <div class="stat"><span>Student Number</span><strong class="small-stat"><?= htmlspecialchars($student['student_number'] ?? '—') ?></strong><small><?= htmlspecialchars($student['year_level'] ?? '') ?></small></div>
    </div>

    <section class="card">
        <div class="card-head">
            <h3>My Disciplinary Records</h3>
            <a href="violations.php">View all</a>
        </div>
        <?php if ($disciplineRecords): ?>
            <div class="search">
                <input id="studentDisciplineSearch" type="search" placeholder="Search date, violation, offense, or status...">
            </div>
            <div class="table-wrap">
                <table id="studentDisciplineTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Violation</th>
                            <th>Offense</th>
                            <th>Parent/Guardian Contact</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Date Released</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disciplineRecords as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['incident_date']) ?></td>
                                <td><?= htmlspecialchars($record['category'] . ': ' . $record['description']) ?></td>
                                <td><span class="badge <?= $record['violation_type'] === 'major' ? 'red' : 'gold' ?>"><?= htmlspecialchars(ucfirst($record['violation_type'])) ?></span></td>
                                <td><?= htmlspecialchars($record['parent_contact_number'] ?: '—') ?></td>
                                <td><?= nl2br(htmlspecialchars($record['remarks'] ?: $record['sanction'] ?: '—')) ?></td>
                                <td><span class="badge <?= $record['status'] === 'cleared' ? 'green' : ($record['status'] === 'dismissed' ? 'red' : 'gold') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $record['status']))) ?></span></td>
                                <td><?= htmlspecialchars($record['released_date'] ?: ($record['resolved_date'] ? substr($record['resolved_date'], 0, 10) : '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted" id="studentDisciplineNoResults" hidden>No matching disciplinary records.</p>
        <?php else: ?>
            <p class="muted">No disciplinary records are currently associated with your account.</p>
        <?php endif; ?>
    </section>

    <?php if ($disciplineRecords): ?>
        <script>
            const studentDisciplineSearch = document.getElementById('studentDisciplineSearch');
            const studentDisciplineRows = document.querySelectorAll('#studentDisciplineTable tbody tr');
            const studentDisciplineNoResults = document.getElementById('studentDisciplineNoResults');

            studentDisciplineSearch.addEventListener('input', () => {
                const query = studentDisciplineSearch.value.trim().toLowerCase();
                let visibleRows = 0;

                studentDisciplineRows.forEach(row => {
                    const matches = row.innerText.toLowerCase().includes(query);
                    row.hidden = !matches;
                    if (matches) visibleRows++;
                });

                studentDisciplineNoResults.hidden = visibleRows > 0;
            });
        </script>
    <?php endif; ?>

    <div class="grid-2">
        <section class="card">
            <div class="card-head">
                <h3>My Recent Activities</h3>
                <a href="violations.php">Disciplinary Record</a>
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
                <a href="violations.php">View disciplinary record</a>
                <a href="think_sheets.php">View Think Sheet status</a>
                <a href="good_moral.php">+ Request Good Moral</a>
            </div>
        </section>
    </div>
<?php elseif ($role === 'registrar') : ?>
    <div class="hero compact">
        <div>
            <span class="eyebrow">REGISTRAR OFFICE</span>
            <h1>Registrar Office</h1>
            <p>Manage official student records and process Good Moral requests through Registrar and SASO verification.</p>
        </div>
        <div class="hero-mark"> </div>
    </div>

    <div class="stats">
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
            <p>Centralized management of discipline, Think Sheet records, Good Moral verification, and AI-assisted analytics.</p>
        </div>
        <div class="hero-mark"></div>
    </div>

    <div class="stats">
        <div class="stat"><span>Students</span><strong><?= $counts['students'] ?></strong></div>
        <div class="stat"><span>Open Discipline Cases</span><strong><?= $pendingViolations ?></strong></div>
        <div class="stat"><span>Think Sheet Repository</span><strong><?= $counts['think_sheet_records'] ?></strong></div>
        <div class="stat"><span>Good Moral Verification</span><strong><?= $pendingMoral ?></strong></div>
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
                <a href="think_sheets.php">Open Think Sheet Repository</a>
                <a href="good_moral.php">Good Moral Verification</a>
                <a href="analytics.php">Open AI Analytics</a>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h3>Centralized SASO Records</h3><a href="reports.php">Open reports</a></div>
        <div class="summary-grid">
            <div><h4>Discipline Cases</h4><p><?= $counts['violations'] ?></p></div>
            <div><h4>Think Sheet Records</h4><p><?= $counts['think_sheet_records'] ?></p></div>
            <div><h4>Good Moral Requests</h4><p><?= $counts['good_moral_requests'] ?></p></div>
        </div>
    </section>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
