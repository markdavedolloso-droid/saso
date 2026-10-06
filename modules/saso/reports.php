<?php
// Administrator report center: department summary and AI-generated insights.
$page_title = 'SASO Reports';
$page_heading = $page_title;

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'ai/analytics_ai.php';
require_role(['admin']);
$departments = require 'config/department_options.php';

$type = $_GET['type'] ?? 'department';
if (!in_array($type, ['department', 'ai_insights'], true)) {
    $type = 'department';
}

$aiError = '';
$aiInsights = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['report_action'] ?? '') === 'generate_ai_insights') {
        $result = askGemini(saso_ai_prompt(build_saso_ai_data($pdo)));
        if ($result['ok']) {
            $aiInsights = $result['text'];
        } else {
            $aiError = $result['error'];
        }
    }
}

$departmentAggregates = $pdo->query(
    "SELECT COALESCE(NULLIF(s.department, ''), 'Unspecified') department,
            COUNT(DISTINCT s.id) population,
            COUNT(DISTINCT CASE WHEN v.violation_type = 'minor' THEN v.id END) minor_frequency,
            COUNT(DISTINCT CASE WHEN v.violation_type = 'major' THEN v.id END) major_frequency
     FROM students s
     LEFT JOIN violations v ON v.student_id = s.id AND v.status <> 'dismissed'
     WHERE s.status = 'active'
     GROUP BY COALESCE(NULLIF(s.department, ''), 'Unspecified')
     ORDER BY department"
)->fetchAll();

$normalizeDepartment = static function (string $department): string {
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim($department)));
};
$departmentRows = [];
$departmentAliases = [];
foreach ($departments as $departmentCode => $departmentLabel) {
    preg_match('/\(([^)]+)\)$/', $departmentLabel, $matches);
    $departmentName = $matches[1] ?? $departmentLabel;
    $departmentRows[$departmentCode] = [
        'department' => $departmentLabel,
        'population' => 0,
        'minor_frequency' => 0,
        'major_frequency' => 0,
    ];
    $departmentAliases[$departmentCode] = array_map(
        $normalizeDepartment,
        [
            $departmentCode,
            $departmentLabel,
            $departmentName,
            $departmentName . ' (' . $departmentCode . ')',
        ]
    );
}

foreach ($departmentAggregates as $aggregate) {
    $normalizedDepartment = $normalizeDepartment($aggregate['department']);
    foreach ($departmentAliases as $departmentCode => $aliases) {
        if (!in_array($normalizedDepartment, $aliases, true)) {
            continue;
        }

        $departmentRows[$departmentCode]['population'] += (int) $aggregate['population'];
        $departmentRows[$departmentCode]['minor_frequency'] += (int) $aggregate['minor_frequency'];
        $departmentRows[$departmentCode]['major_frequency'] += (int) $aggregate['major_frequency'];
        break;
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $type === 'department') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="saso_department_discipline_summary.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Department', 'Students', 'Minor Offenses', 'Minor Offense %', 'Major Offenses', 'Major Offense %', 'Total Offenses', 'Total Offense %']);
    foreach ($departmentRows as $row) {
        $population = (int) $row['population'];
        $minorFrequency = (int) $row['minor_frequency'];
        $majorFrequency = (int) $row['major_frequency'];
        $totalFrequency = $minorFrequency + $majorFrequency;
        fputcsv($out, [
            $row['department'],
            $population,
            $minorFrequency,
            $population ? number_format($minorFrequency * 100 / $population, 0) . '%' : '0%',
            $majorFrequency,
            $population ? number_format($majorFrequency * 100 / $population, 0) . '%' : '0%',
            $totalFrequency,
            $population ? number_format($totalFrequency * 100 / $population, 0) . '%' : '0%',
        ]);
    }
    fclose($out);
    exit;
}

include 'includes/header.php';
?>
<style>
    .hero.compact { margin-bottom: 18px; }
    .report-tabs,
    .report-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 20px; }
    .report-tabs .btn + .btn,
    .report-actions .btn + .btn { margin-left: 0; }
    .ai-report-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
    .ai-report-actions p, .ai-report-actions form { margin: 0; }
    .ai-report-actions form { flex: 0 0 auto; }
    .ai-report-actions button { min-height: 44px; white-space: nowrap; }
    .department-report { min-width: 760px; }
    .department-report th, .department-report td { text-align: center; vertical-align: middle; }
    .department-report td:first-child { text-align: left; font-weight: 700; }
    .report-note { margin: 0 0 14px; color: var(--muted); font-size: 12px; }
    @media (max-width: 760px) {
        .ai-report-actions { align-items: flex-start; flex-direction: column; gap: 12px; }
    }
    @media print {
        .report-tabs, .page-actions, .report-note { display: none !important; }
        .department-report { min-width: 0; font-size: 10px; }
    }
</style>

<div class="hero compact">
    <div>
        <span class="eyebrow">REPORT CENTER</span>
        <h1>SASO Reports</h1>
        <p>Department discipline summary and AI-assisted insights.</p>
    </div>
</div>

<div class="page-actions">
    <div class="report-tabs" role="tablist" aria-label="SASO report types">
        <a class="btn <?= $type === 'department' ? 'primary' : 'secondary' ?>" href="reports.php?type=department">Department Summary</a>
        <a class="btn <?= $type === 'ai_insights' ? 'primary' : 'secondary' ?>" href="reports.php?type=ai_insights">AI Insights</a>
    </div>
    <?php if ($type === 'department'): ?>
        <div class="report-actions">
            <a class="btn secondary" href="reports.php?type=department&amp;export=csv">Export CSV</a>
            <button class="btn secondary" type="button" onclick="window.print()">Print</button>
        </div>
    <?php endif; ?>
</div>

<?php if ($type === 'department'): ?>
    <section class="card">
        <h3>Department Discipline Summary</h3>
        <p class="report-note">Shows one row for each department. Offense percentages are the non-dismissed offense count divided by active students in that department; the total percentage combines minor and major offenses.</p>
        <div class="table-wrap">
            <table id="dataTable" class="department-report">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Students</th>
                        <th>Minor Offenses</th>
                        <th>Minor %</th>
                        <th>Major Offenses</th>
                        <th>Major %</th>
                        <th>Total Offenses</th>
                        <th>Total %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalPopulation = 0;
                    $totalMinor = 0;
                    $totalMajor = 0;
                    foreach ($departmentRows as $row):
                        $population = (int) $row['population'];
                        $minorFrequency = (int) $row['minor_frequency'];
                        $majorFrequency = (int) $row['major_frequency'];
                        $totalFrequency = $minorFrequency + $majorFrequency;
                        $totalPopulation += $population;
                        $totalMinor += $minorFrequency;
                        $totalMajor += $majorFrequency;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['department'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $population ?></td>
                            <td><?= $minorFrequency ?></td>
                            <td><?= $population ? number_format($minorFrequency * 100 / $population, 0) . '%' : '0%' ?></td>
                            <td><?= $majorFrequency ?></td>
                            <td><?= $population ? number_format($majorFrequency * 100 / $population, 0) . '%' : '0%' ?></td>
                            <td><?= $totalFrequency ?></td>
                            <td><?= $population ? number_format($totalFrequency * 100 / $population, 0) . '%' : '0%' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td><?= $totalPopulation ?></td>
                        <td><?= $totalMinor ?></td>
                        <td><?= $totalPopulation ? number_format($totalMinor * 100 / $totalPopulation, 0) . '%' : '0%' ?></td>
                        <td><?= $totalMajor ?></td>
                        <td><?= $totalPopulation ? number_format($totalMajor * 100 / $totalPopulation, 0) . '%' : '0%' ?></td>
                        <td><?= $totalMinor + $totalMajor ?></td>
                        <td><?= $totalPopulation ? number_format(($totalMinor + $totalMajor) * 100 / $totalPopulation, 0) . '%' : '0%' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
<?php else: ?>
    <?php if ($aiError): ?>
        <div class="alert danger"><?= htmlspecialchars($aiError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <section class="card ai-insights-panel">
        <div class="card-head">
            <div>
                <h3>AI-Generated SASO Insights</h3>
                <p class="muted">AI summarizes de-identified aggregate discipline, Think Sheet repository, and Good Moral request data. SASO personnel remain responsible for final decisions.</p>
            </div>
        </div>
        <div class="ai-report-actions">
            <p class="muted">Generate an analysis of current aggregated SASO records.</p>
            <form method="post" action="reports.php?type=ai_insights">
                <?= csrf_field() ?>
                <input type="hidden" name="report_action" value="generate_ai_insights">
                <button class="btn primary" type="submit">Generate AI Insights</button>
            </form>
        </div>
        <?php if ($aiInsights): ?>
            <div class="ai-output"><?= nl2br(htmlspecialchars($aiInsights, ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
