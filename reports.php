<?php
// Builds administrator reports from the system's operational records.
$page_title = 'Reports';
$page_heading = 'Reports';

require_once 'config/database.php';
require_once 'includes/auth.php';
require_role(['admin']);

$type = $_GET['type'] ?? 'discipline';
$allowed = ['discipline', 'think_sheets', 'good_moral', 'evaluations'];
if (!in_array($type, $allowed, true)) {
    $type = 'discipline';
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $queries = [
        'discipline' => "SELECT s.student_number,CONCAT(s.last_name,', ',s.first_name) student_name,v.violation_type,v.category,v.description,v.incident_date,v.sanction,v.status,v.resolution_notes FROM violations v JOIN students s ON s.id=v.student_id ORDER BY v.incident_date DESC",
        'think_sheets' => "SELECT s.student_number,CONCAT(s.last_name,', ',s.first_name) student_name,t.assigned_date,t.due_date,t.submitted_date,t.reviewed_date,t.status,t.reviewed_by,t.review_notes FROM think_sheets t JOIN students s ON s.id=t.student_id ORDER BY t.created_at DESC",
        'good_moral' => "SELECT s.student_number,CONCAT(s.last_name,', ',s.first_name) student_name,g.purpose,g.status,g.created_at,g.saso_verified_by,g.saso_verified_at,g.registrar_processed_by,g.registrar_processed_at FROM good_moral_requests g JOIN students s ON s.id=g.student_id ORDER BY g.created_at DESC",
        'evaluations' => "SELECT CONCAT(f.last_name,', ',f.first_name) teacher,e.academic_year,e.semester,e.overall_score,e.status,e.created_at FROM teacher_evaluations e JOIN faculty f ON f.id=e.faculty_id ORDER BY e.created_at DESC",
    ];

    $rows = $pdo->query($queries[$type])->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="crmc_saso_' . $type . '_report.csv"');
    $out = fopen('php://output', 'w');
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
    }
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

$discipline = $pdo->query("SELECT violation_type,COUNT(*) total FROM violations GROUP BY violation_type ORDER BY violation_type")->fetchAll();
$think = $pdo->query("SELECT status,COUNT(*) total FROM think_sheets GROUP BY status ORDER BY status")->fetchAll();
$moral = $pdo->query("SELECT status,COUNT(*) total FROM good_moral_requests GROUP BY status ORDER BY status")->fetchAll();
$eval = $pdo->query("SELECT f.id,CONCAT(f.first_name,' ',f.last_name) teacher,COUNT(e.id) evaluations,ROUND(AVG(e.overall_score),2) average_score FROM faculty f LEFT JOIN teacher_evaluations e ON e.faculty_id=f.id GROUP BY f.id ORDER BY teacher")->fetchAll();

include 'includes/header.php';
?>

<div class="hero compact">
    <div>
        <span class="eyebrow">REPORT CENTER</span>
        <h1>System Reports</h1>
        <p>Generate operational summaries for discipline, Think Sheets, Good Moral requests, and teacher evaluations.</p>
    </div>
</div>

<div class="page-actions">
    <div class="search">
        <input id="tableSearch" placeholder="Search current report... ">
    </div>
    <div>
        <a class="btn secondary" href="reports.php?type=<?= urlencode($type) ?>&export=csv">Export CSV</a>
        <button class="btn secondary" onclick="window.print()">Print</button>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <h3>Report Type</h3>
        <div>
            <a class="btn <?= $type === 'discipline' ? 'primary' : 'secondary' ?>" href="reports.php?type=discipline">Discipline</a>
            <a class="btn <?= $type === 'think_sheets' ? 'primary' : 'secondary' ?>" href="reports.php?type=think_sheets">Think Sheets</a>
            <a class="btn <?= $type === 'good_moral' ? 'primary' : 'secondary' ?>" href="reports.php?type=good_moral">Good Moral</a>
            <a class="btn <?= $type === 'evaluations' ? 'primary' : 'secondary' ?>" href="reports.php?type=evaluations">Evaluations</a>
        </div>
    </div>

    <?php if ($type === 'discipline') : ?>
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $detail = $pdo->query("SELECT violation_type,category,COUNT(*) total FROM violations GROUP BY violation_type,category ORDER BY total DESC")->fetchAll();
                foreach ($detail as $r) :
                ?>
                    <tr>
                        <td><?= htmlspecialchars($r['violation_type']) ?></td>
                        <td><?= htmlspecialchars($r['category']) ?></td>
                        <td><?= htmlspecialchars($r['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$detail) : ?>
                    <tr><td colspan="3">No discipline data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'think_sheets') : ?>
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($think as $r) : ?>
                    <tr>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                        <td><?= htmlspecialchars($r['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$think) : ?>
                    <tr><td colspan="2">No Think Sheet data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'good_moral') : ?>
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moral as $r) : ?>
                    <tr>
                        <td><?= htmlspecialchars(str_replace('_', ' ', $r['status'])) ?></td>
                        <td><?= htmlspecialchars($r['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$moral) : ?>
                    <tr><td colspan="2">No Good Moral data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php else : ?>
        <table id="dataTable">
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>Evaluations</th>
                    <th>Average Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eval as $r) : ?>
                    <tr>
                        <td><?= htmlspecialchars($r['teacher']) ?></td>
                        <td><?= htmlspecialchars($r['evaluations']) ?></td>
                        <td><?= number_format((float) $r['average_score'], 2) ?> / 5</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$eval) : ?>
                    <tr><td colspan="3">No evaluation data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Summary</h3>
    <p class="muted">
        Discipline cases: <strong><?= array_sum(array_column($discipline, 'total')) ?></strong>
        · Think Sheets: <strong><?= array_sum(array_column($think, 'total')) ?></strong>
        · Good Moral requests: <strong><?= array_sum(array_column($moral, 'total')) ?></strong>
        · Teacher evaluations: <strong><?= array_sum(array_column($eval, 'evaluations')) ?></strong>
    </p>
</section>

<?php include 'includes/footer.php'; ?>
