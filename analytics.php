<?php

// Admin analytics page that summarizes discipline trends and evaluation feedback.

$page_title = 'AI Analytics';

$page_heading = 'AI-Assisted Analytics';

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/analytics_helper.php';
require_once 'ai/analytics_ai.php';

require_role(['admin']);

$aiError = '';
$disciplineInsights = '';
$evaluationInsights = '';
$askAnswer = '';
$askQuestion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $aiAction = $_POST['ai_action'] ?? '';

    if ($aiAction === 'generate_insights') {
        $disciplineResult = askOmniRoute(discipline_ai_prompt(build_discipline_ai_data($pdo)));
        $evaluationResult = askOmniRoute(evaluation_ai_prompt(build_evaluation_ai_data($pdo)));

        if ($disciplineResult['ok']) {
            $disciplineInsights = $disciplineResult['text'];
        } else {
            $aiError = $disciplineResult['error'];
        }

        if ($evaluationResult['ok']) {
            $evaluationInsights = $evaluationResult['text'];
        } elseif ($aiError === '') {
            $aiError = $evaluationResult['error'];
        }
    } elseif ($aiAction === 'ask_ai') {
        $askQuestion = trim((string) ($_POST['question'] ?? ''));
        $askResult = ask_analytics_ai($pdo, $askQuestion);

        if ($askResult['ok']) {
            $askAnswer = $askResult['text'];
        } else {
            $aiError = $askResult['error'];
        }
    }
}

$minor = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE violation_type='minor'")->fetchColumn();

$major = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE violation_type='major'")->fetchColumn();

$open = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE status IN ('pending','under_review')")->fetchColumn();

$resolved = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE status='resolved'")->fetchColumn();

$comments = $pdo->query("
    SELECT e.general_comment, es.comment
    FROM teacher_evaluations e
    LEFT JOIN evaluation_scores es ON es.evaluation_id = e.id
    WHERE e.status='submitted'
")->fetchAll();

$all = [];

foreach ($comments as $c) {
    if (trim($c['general_comment'] ?? '')) {
        $all[] = $c['general_comment'];
    }

    if (trim($c['comment'] ?? '')) {
        $all[] = $c['comment'];
    }
}

$analysis = analyzeComments($all);

$teachers = $pdo->query("
    SELECT
        f.id,
        CONCAT(f.first_name, ' ', f.last_name) name,
        ROUND(AVG(e.overall_score), 2) score,
        COUNT(e.id) evaluations
    FROM faculty f
    LEFT JOIN teacher_evaluations e
        ON e.faculty_id = f.id
        AND e.status='submitted'
    GROUP BY f.id
    ORDER BY evaluations DESC, f.last_name
")->fetchAll();

$trend = $pdo->query("
    SELECT
        DATE_FORMAT(incident_date, '%Y-%m') month,
        COUNT(*) total
    FROM violations
    WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(incident_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

$selected = $_GET['teacher'] ?? '';

$teacherAnalysis = null;
$teacherName = '';

if ($selected) {
    $q = $pdo->prepare(
        'SELECT CONCAT(first_name, " ", last_name) FROM faculty WHERE id=?'
    );

    $q->execute([$selected]);

    $teacherName = $q->fetchColumn() ?: '';

    $q = $pdo->prepare("
        SELECT e.general_comment, es.comment
        FROM teacher_evaluations e
        LEFT JOIN evaluation_scores es
            ON es.evaluation_id = e.id
        WHERE e.faculty_id=?
        AND e.status='submitted'
    ");

    $q->execute([$selected]);

    $rows = $q->fetchAll();

    $fb = [];

    foreach ($rows as $c) {
        if (trim($c['general_comment'] ?? '')) {
            $fb[] = $c['general_comment'];
        }

        if (trim($c['comment'] ?? '')) {
            $fb[] = $c['comment'];
        }
    }

    $teacherAnalysis = analyzeComments($fb);
}

include 'includes/header.php';

?>

<?php if ($aiError): ?>
    <div class="alert danger">
        <?= htmlspecialchars($aiError, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="card ai-insights-panel">
    <div class="card-head">
        <div>
            <h3>AI-Generated Insights</h3>
            <p class="muted">OmniRoute interprets the existing aggregated analytics for decision support. SASO personnel remain responsible for final decisions.</p>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="ai_action" value="generate_insights">
            <button class="btn primary" type="submit">Generate AI Insights</button>
        </form>
    </div>

    <div class="grid-2">
        <article class="ai-summary">
            <h4>Disciplinary Analysis</h4>
            <?php if ($disciplineInsights): ?>
                <div class="ai-output"><?= nl2br(htmlspecialchars($disciplineInsights, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php else: ?>
                <p class="muted">Select Generate AI Insights to interpret discipline patterns, statuses, and trends.</p>
            <?php endif; ?>
        </article>

        <article class="ai-summary">
            <h4>Teacher Evaluation Analysis</h4>
            <?php if ($evaluationInsights): ?>
                <div class="ai-output"><?= nl2br(htmlspecialchars($evaluationInsights, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php else: ?>
                <p class="muted">Select Generate AI Insights to summarize common strengths, improvement areas, and themes.</p>
            <?php endif; ?>
        </article>
    </div>

    <div class="ask-ai-box">
        <h4>Ask AI</h4>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="ai_action" value="ask_ai">
            <label class="wide">
                Question
                <textarea name="question" rows="3" maxlength="500" required placeholder="What are the most common disciplinary patterns?"><?= htmlspecialchars($askQuestion, ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <div class="form-end">
                <button class="btn secondary" type="submit">Ask AI</button>
            </div>
        </form>
        <?php if ($askAnswer): ?>
            <div class="ai-summary ai-answer">
                <strong>AI response</strong>
                <div class="ai-output"><?= nl2br(htmlspecialchars($askAnswer, ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="hero compact">
    <div>
        <span class="eyebrow">AI-ASSISTED</span>


    <h1>Feedback & Discipline Insights</h1>

    <p>
        Local, explainable text analysis groups recurring themes and sentiment
        indicators to help SASO review trends. It does not make disciplinary decisions.
    </p>
</div>

<div class="hero-mark">AI</div>


</div>

<div class="stats">


<div class="stat">
    <span>Minor Cases</span>
    <strong><?= $minor ?></strong>
</div>

<div class="stat">
    <span>Major Cases</span>
    <strong><?= $major ?></strong>
</div>

<div class="stat">
    <span>Open Cases</span>
    <strong><?= $open ?></strong>
</div>

<div class="stat">
    <span>Resolved Cases</span>
    <strong><?= $resolved ?></strong>
</div>

<div class="stat">
    <span>Feedback Comments</span>
    <strong><?= $analysis['count'] ?></strong>
</div>


</div>

<div class="grid-2">


<section class="card">

    <div class="card-head">
        <h3>Student Feedback Analysis</h3>
        <span class="badge gold">AI-assisted</span>
    </div>

    <p class="hint">
        The current implementation uses transparent keyword/theme and sentiment
        scoring. It can later be connected to an approved AI service for semantic summaries.
    </p>

    <p>
        <strong>Overall indicator:</strong>
        <?= htmlspecialchars($analysis['sentiment']) ?>
        · Positive terms: <?= $analysis['positive'] ?>
        · Attention terms: <?= $analysis['negative'] ?>
    </p>

    <h4>Common Strengths</h4>

    <?php if ($analysis['strengths']): ?>

        <?php foreach (array_slice($analysis['strengths'], 0, 4, true) as $label => $n): ?>

            <div class="bar-row">

                <span><?= htmlspecialchars(ucwords($label)) ?></span>

                <div>
                    <i style="width:<?= min(100, $n * 15) ?>%"></i>
                </div>

                <b><?= $n ?></b>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p class="muted">
            Not enough recurring strengths yet.
        </p>

    <?php endif; ?>

    <h4 class="section-gap">Areas for Improvement</h4>

    <?php if ($analysis['improvements']): ?>

        <?php foreach (array_slice($analysis['improvements'], 0, 4, true) as $label => $n): ?>

            <div class="bar-row">

                <span><?= htmlspecialchars(ucwords($label)) ?></span>

                <div>
                    <i style="width:<?= min(100, $n * 15) ?>%"></i>
                </div>

                <b><?= $n ?></b>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p class="muted">
            No recurring improvement theme detected.
        </p>

    <?php endif; ?>

</section>

<section class="card">

    <h3>Discipline Trend — Last 6 Months</h3>

    <?php if ($trend): ?>

        <table>

            <thead>
                <tr>
                    <th>Month</th>
                    <th>Cases</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($trend as $t): ?>

                    <tr>
                        <td><?= htmlspecialchars($t['month']) ?></td>
                        <td><?= htmlspecialchars($t['total']) ?></td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php else: ?>

        <p class="muted">
            No discipline trend data available.
        </p>

    <?php endif; ?>

    <div class="ai-summary">

        <b>Review reminder:</b>

        Trends are indicators only. SASO should review individual case records
        and institutional policy before taking action.

    </div>

</section>


</div>

<section class="card">


<h3>Teacher Evaluation Overview</h3>

<table>

    <thead>

        <tr>
            <th>Teacher</th>
            <th>Score</th>
            <th>Evaluations</th>
            <th>Feedback</th>
        </tr>

    </thead>

    <tbody>

        <?php foreach ($teachers as $t): ?>

            <tr>

                <td>
                    <a href="analytics.php?teacher=<?= urlencode($t['id']) ?>">
                        <?= htmlspecialchars($t['name']) ?>
                    </a>
                </td>

                <td>
                    <?= number_format((float)$t['score'], 2) ?>/5
                </td>

                <td>
                    <?= $t['evaluations'] ?>
                </td>

                <td>
                    <a href="analytics.php?teacher=<?= urlencode($t['id']) ?>">
                        Analyze
                    </a>
                </td>

            </tr>

        <?php endforeach; ?>

        <?php if (!$teachers): ?>

            <tr>
                <td colspan="4">
                    No teacher evaluation data.
                </td>
            </tr>

        <?php endif; ?>

    </tbody>

</table>


</section>

<?php if ($teacherAnalysis): ?>


<section class="card">

    <div class="card-head">

        <h3>
            AI-Assisted Feedback Summary:
            <?= htmlspecialchars($teacherName) ?>
        </h3>

        <a href="analytics.php">Clear</a>

    </div>

    <p>
        <strong>Indicator:</strong>
        <?= htmlspecialchars($teacherAnalysis['sentiment']) ?>
        · <?= $teacherAnalysis['count'] ?> comments analyzed.
    </p>

    <div class="summary-grid">

        <div>

            <h4>Strengths</h4>

            <ul>

                <?php foreach (array_slice($teacherAnalysis['strengths'], 0, 5, true) as $label => $n): ?>

                    <li>
                        <?= htmlspecialchars(ucwords($label)) ?>

                        <span class="muted">
                            (<?= $n ?> mentions)
                        </span>
                    </li>

                <?php endforeach; ?>

                <?php if (!$teacherAnalysis['strengths']): ?>

                    <li class="muted">
                        No recurring strength theme detected.
                    </li>

                <?php endif; ?>

            </ul>

        </div>

        <div>

            <h4>Areas for Improvement</h4>

            <ul>

                <?php foreach (array_slice($teacherAnalysis['improvements'], 0, 5, true) as $label => $n): ?>

                    <li>
                        <?= htmlspecialchars(ucwords($label)) ?>

                        <span class="muted">
                            (<?= $n ?> mentions)
                        </span>
                    </li>

                <?php endforeach; ?>

                <?php if (!$teacherAnalysis['improvements']): ?>

                    <li class="muted">
                        No recurring improvement theme detected.
                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

    <div class="ai-summary">

        <b>Suggested focus:</b>

        Review recurring themes alongside the numerical score and original feedback.
        This tool supports human review and does not automatically evaluate or sanction teachers.

    </div>

</section>


<?php endif; ?>

<?php include 'includes/footer.php'; ?>
