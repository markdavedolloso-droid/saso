<?php
// Prepares de-identified analytics and constrained prompts for Gemini.

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
        'by_department' => [],
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

    foreach ($pdo->query("SELECT COALESCE(NULLIF(s.department, ''), 'Unspecified') department, COUNT(*) total FROM violations v JOIN students s ON s.id = v.student_id GROUP BY department ORDER BY total DESC")->fetchAll() as $row) {
        $data['by_department'][$row['department']] = (int) $row['total'];
    }

    foreach ($pdo->query("SELECT DATE_FORMAT(incident_date, '%Y-%m') month, COUNT(*) total FROM violations WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(incident_date, '%Y-%m') ORDER BY month")->fetchAll() as $row) {
        $data['by_month'][$row['month']] = (int) $row['total'];
    }

    return $data;
}

function build_module_ai_data(PDO $pdo, string $table): array
{
    $allowedTables = ['good_moral_requests'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Unsupported SASO module.');
    }

    $data = [
        'total_records' => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(),
        'by_status' => [],
        'by_month' => [],
    ];

    foreach ($pdo->query("SELECT status, COUNT(*) total FROM {$table} GROUP BY status ORDER BY total DESC")->fetchAll() as $row) {
        $data['by_status'][$row['status']] = (int) $row['total'];
    }

    foreach ($pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') month, COUNT(*) total FROM {$table} WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month")->fetchAll() as $row) {
        $data['by_month'][$row['month']] = (int) $row['total'];
    }

    return $data;
}

function build_think_sheet_ai_data(PDO $pdo): array
{
    $data = [
        'total_records' => (int) $pdo->query('SELECT COUNT(*) FROM think_sheet_records')->fetchColumn(),
        'linked_to_discipline_cases' => (int) $pdo->query('SELECT COUNT(*) FROM think_sheet_records WHERE violation_id IS NOT NULL')->fetchColumn(),
        'by_status' => [],
        'by_month' => [],
    ];

    foreach ($pdo->query('SELECT status, COUNT(*) total FROM think_sheet_records GROUP BY status ORDER BY status')->fetchAll() as $row) {
        $data['by_status'][$row['status']] = (int) $row['total'];
    }

    foreach ($pdo->query("SELECT DATE_FORMAT(recorded_on, '%Y-%m') month, COUNT(*) total FROM think_sheet_records WHERE recorded_on >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(recorded_on, '%Y-%m') ORDER BY month")->fetchAll() as $row) {
        $data['by_month'][$row['month']] = (int) $row['total'];
    }

    return $data;
}

function build_saso_ai_data(PDO $pdo): array
{
    return [
        'discipline' => build_discipline_ai_data($pdo),
        'think_sheet_repository' => build_think_sheet_ai_data($pdo),
        'good_moral_requests' => build_module_ai_data($pdo, 'good_moral_requests'),
    ];
}

function saso_ai_prompt(array $data): string
{
    return "You are the AI-Assisted Analytics component of the CRMC Student Affairs and Services Office. Interpret only the aggregated, de-identified statistics below across discipline, including department totals, Think Sheet repository records, and Good Moral requests. Do not infer or identify individual students, invent statistics, recommend punishments or sanctions, or make final administrative decisions. Mention uncertainty when data is limited. Produce concise plain text with these headings: Overall summary, Discipline patterns by department, Think Sheet repository observations, Good Moral workflow observations, Notable trends, Areas for SASO review. Areas for review must be review topics only, never disciplinary decisions.\n\nDATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


function ask_analytics_ai(PDO $pdo, string $question): array
{
    $question = trim($question);
    $questionLength = function_exists('mb_strlen') ? mb_strlen($question) : strlen($question);
    if ($question === '' || $questionLength > 500) {
        return ['ok' => false, 'error' => 'Please enter a question of 500 characters or fewer.'];
    }

    $data = build_saso_ai_data($pdo);

    $prompt = "You are a constrained decision-support assistant for CRMC SASO. Answer the administrator's question using only the supplied aggregated, de-identified discipline (including department totals), Think Sheet repository, and Good Moral request analytics. Do not execute SQL, request more data, identify individual students, recommend punishment, rank people, make employment decisions, or make final administrative decisions. If the question cannot be answered from the data, say so. Keep the answer concise and plain text.\n\nQUESTION:\n" . $question . "\n\nDATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return askGemini($prompt);
}
