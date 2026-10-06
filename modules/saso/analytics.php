<?php
$page_title = 'AI Analytics';
$page_heading = 'AI-Assisted Analytics';

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'ai/analytics_ai.php';

require_role(['admin']);

$aiError = '';
$sasoInsights = '';
$askAnswer = '';
$askQuestion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $aiAction = $_POST['ai_action'] ?? '';

    if ($aiAction === 'generate_insights') {
        $result = askGemini(saso_ai_prompt(build_saso_ai_data($pdo)));
        if ($result['ok']) {
            $sasoInsights = $result['text'];
        } else {
            $aiError = $result['error'];
        }
    } elseif ($aiAction === 'ask_ai') {
        $askQuestion = trim((string)($_POST['question'] ?? ''));
        $result = ask_analytics_ai($pdo, $askQuestion);
        if ($result['ok']) {
            $askAnswer = $result['text'];
        } else {
            $aiError = $result['error'];
        }
    }
}

$minor = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE violation_type='minor'")->fetchColumn();
$major = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE violation_type='major'")->fetchColumn();
$open = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE status='pending'")->fetchColumn();
$cleared = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE status='cleared'")->fetchColumn();
$thinkSheets = build_think_sheet_ai_data($pdo);
$goodMoralRequests = build_module_ai_data($pdo, 'good_moral_requests');

$trend = $pdo->query("
    SELECT DATE_FORMAT(incident_date, '%Y-%m') month, COUNT(*) total
    FROM violations
    WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(incident_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

include 'includes/header.php';
?>

<?php if ($aiError): ?>
    <div class="alert danger"><?= htmlspecialchars($aiError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="card ai-insights-panel">
    <div class="card-head">
        <div>
            <h3>AI-Generated SASO Insights</h3>
            <p class="muted">AI analyzes de-identified aggregate discipline, Think Sheet, and Good Moral records. SASO personnel remain responsible for final decisions.</p>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="ai_action" value="generate_insights">
            <button class="btn primary" type="submit">Generate AI Insights</button>
        </form>
    </div>
    <div class="grid-2">
        <article class="ai-summary">
            <h4>SASO Record Analysis</h4>
            <?php if ($sasoInsights): ?>
                <div class="ai-output"><?= nl2br(htmlspecialchars($sasoInsights, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php else: ?>
                <p class="muted">Generate an analysis from current aggregated SASO records.</p>
            <?php endif; ?>
        </article>
        <article class="ai-summary">
            <h4>Current Record Summary</h4>
            <p>Minor cases: <strong><?= $minor ?></strong></p>
            <p>Major cases: <strong><?= $major ?></strong></p>
            <p>Open cases: <strong><?= $open ?></strong></p>
            <p>Cleared cases: <strong><?= $cleared ?></strong></p>
            <p>Think Sheet records: <strong><?= $thinkSheets['total_records'] ?></strong> (<?= (int)($thinkSheets['by_status']['assigned'] ?? 0) ?> assigned, <?= (int)($thinkSheets['by_status']['submitted'] ?? 0) ?> submitted, <?= (int)($thinkSheets['by_status']['reviewed'] ?? 0) ?> reviewed)</p>
            <p>Good Moral requests: <strong><?= $goodMoralRequests['total_records'] ?></strong> (<?= (int)($goodMoralRequests['by_status']['pending_saso'] ?? 0) ?> awaiting SASO)</p>
        </article>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Ask AI About SASO Records</h3>
            <p class="muted">Questions use aggregated discipline data, including department totals, repository status, and Good Moral request data.</p>
        </div>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="ai_action" value="ask_ai">
        <label class="wide">Question
            <textarea name="question" rows="3" required placeholder="Example: Which record statuses or monthly trends may need attention?"><?= htmlspecialchars($askQuestion) ?></textarea>
        </label>
        <div class="form-end"><button class="btn primary">Ask AI</button></div>
    </form>
    <?php if ($askAnswer): ?>
        <div class="ai-output" style="margin-top:16px;"><?= nl2br(htmlspecialchars($askAnswer, ENT_QUOTES, 'UTF-8')) ?></div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h3>Disciplinary Trend</h3></div>
    <table>
        <thead><tr><th>Month</th><th>Total Cases</th></tr></thead>
        <tbody>
        <?php foreach ($trend as $row): ?>
            <tr><td><?= htmlspecialchars($row['month']) ?></td><td><?= htmlspecialchars($row['total']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$trend): ?><tr><td colspan="2">No disciplinary data available.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
