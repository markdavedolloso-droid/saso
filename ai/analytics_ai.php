<?php
// Prepares de-identified analytics and constrained prompts for OmniRoute.

require_once __DIR__ . '/gemini.php';

function sanitize_feedback_for_ai(string $text): string
{
    $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email removed]', $text);
    $text = preg_replace('/(?<!\d)(?:\+?\d[\d\s().-]{7,}\d)(?!\d)/', '[phone removed]', $text);
    return trim((string) $text);
}

function build_discipline_ai_data(PDO $pdo): array
{
    $data = [
        'total_cases' => (int) $pdo->query('SELECT COUNT(*) FROM violations')->fetchColumn(),
        'by_type' => [],
        'by_status' => [],
        'by_category' => [],
        'by_month' => [],
    ];

    foreach ($pdo->query('SELECT violation_type, COUNT(*) total FROM violations GROUP BY violation_type ORDER BY total DESC')->fetchAll() as $row) {
        $data['by_type'][$row['violation_type']] = (int) $row['total'];
    }

    foreach ($pdo->query('SELECT status, COUNT(*) total FROM violations GROUP BY status ORDER BY total DESC')->fetchAll() as $row) {
        $data['by_status'][$row['status']] = (int) $row['total'];
    }

    foreach ($pdo->query('SELECT category, COUNT(*) total FROM violations GROUP BY category ORDER BY total DESC LIMIT 20')->fetchAll() as $row) {
        $data['by_category'][$row['category']] = (int) $row['total'];
    }

    foreach ($pdo->query("SELECT DATE_FORMAT(incident_date, '%Y-%m') month, COUNT(*) total FROM violations WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(incident_date, '%Y-%m') ORDER BY month")->fetchAll() as $row) {
        $data['by_month'][$row['month']] = (int) $row['total'];
    }

    return $data;
}

function build_evaluation_ai_data(PDO $pdo): array
{
    $comments = $pdo->query("SELECT e.general_comment, es.comment FROM teacher_evaluations e LEFT JOIN evaluation_scores es ON es.evaluation_id=e.id WHERE e.status='submitted'")->fetchAll();
    $feedback = [];

    foreach ($comments as $row) {
        foreach (['general_comment', 'comment'] as $field) {
            $text = trim((string) ($row[$field] ?? ''));
            if ($text !== '') {
                $safeText = sanitize_feedback_for_ai($text);
                $feedback[] = function_exists('mb_substr')
                    ? mb_substr($safeText, 0, 500)
                    : substr($safeText, 0, 500);
            }
        }
    }

    $scoreStats = $pdo->query("SELECT COUNT(*) evaluations, ROUND(AVG(overall_score), 2) average_score, MIN(overall_score) lowest_score, MAX(overall_score) highest_score FROM teacher_evaluations WHERE status='submitted'")->fetch() ?: [];

    return [
        'evaluation_count' => (int) ($scoreStats['evaluations'] ?? 0),
        'average_score' => $scoreStats['average_score'] !== null ? (float) $scoreStats['average_score'] : null,
        'lowest_score' => $scoreStats['lowest_score'] !== null ? (float) $scoreStats['lowest_score'] : null,
        'highest_score' => $scoreStats['highest_score'] !== null ? (float) $scoreStats['highest_score'] : null,
        'feedback_count' => count($feedback),
        'feedback' => array_slice($feedback, 0, 120),
    ];
}

function discipline_ai_prompt(array $data): string
{
    return "You are the AI-Assisted Analytics component of the CRMC Student Affairs and Services Office. Interpret only the de-identified statistics below. Do not invent statistics, identify people, recommend punishments or sanctions, or make final administrative decisions. Mention uncertainty when data is limited. Produce concise plain text with these headings: Overall summary, Recurring patterns, Status observations, Notable trends, Areas for SASO review. Areas for review must be review topics only, never disciplinary decisions.\n\nDATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function evaluation_ai_prompt(array $data): string
{
    return "You are the AI-Assisted Analytics component of the CRMC Student Affairs and Services Office. Summarize only the supplied teacher evaluation statistics and anonymous feedback. Do not rank teachers, identify students or teachers, make employment decisions, or invent information. State clearly when data is insufficient. Produce concise plain text with these headings: Overall summary, Common strengths, Improvement areas, Recurring themes, General observations.\n\nDATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function ask_analytics_ai(PDO $pdo, string $question): array
{
    $question = trim($question);
    $questionLength = function_exists('mb_strlen') ? mb_strlen($question) : strlen($question);
    if ($question === '' || $questionLength > 500) {
        return ['ok' => false, 'error' => 'Please enter a question of 500 characters or fewer.'];
    }

    $data = [
        'discipline' => build_discipline_ai_data($pdo),
        'teacher_evaluations' => build_evaluation_ai_data($pdo),
    ];

    $prompt = "You are a constrained decision-support assistant for CRMC SASO. Answer the administrator's question using only the supplied aggregated/de-identified analytics. Do not execute SQL, request more data, identify people, recommend punishment, rank teachers, make employment decisions, or make final administrative decisions. If the question cannot be answered from the data, say so. Keep the answer concise and plain text.\n\nQUESTION:\n" . $question . "\n\nDATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return askOmniRoute($prompt);
}
