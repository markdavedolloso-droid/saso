<?php
// Authenticated Think Sheet details page with an inline scan preview.
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();
$user = current_user();

$recordId = trim((string) ($_GET['id'] ?? ''));
$stmt = $pdo->prepare(
    "SELECT r.id, r.student_id, r.recorded_on, r.reported_by, r.status, r.document_file,
            s.first_name, s.middle_name, s.last_name, s.department, s.course, s.year_level
     FROM think_sheet_records r
     JOIN students s ON s.id = r.student_id
     WHERE r.id = ?
     LIMIT 1"
);
$stmt->execute([$recordId]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    exit('Think Sheet record not found.');
}

if (($user['role'] ?? '') !== 'admin') {
    $student = ($user['role'] ?? '') === 'student' ? get_student_for_user($pdo) : null;
    if (!$student || $student['id'] !== $record['student_id']) {
        http_response_code(403);
        exit('You are not authorized to view this Think Sheet.');
    }
}

$filename = $record['document_file'];
if (!is_string($filename) || $filename === '' || basename($filename) !== $filename) {
    http_response_code(404);
    exit('Think Sheet scan not found.');
}

$uploadDir = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'think_sheet_records');
$path = $uploadDir ? realpath($uploadDir . DIRECTORY_SEPARATOR . $filename) : false;
if (!$uploadDir || !$path || strpos($path, $uploadDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('Think Sheet scan not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mime, $allowedMimeTypes, true)) {
    http_response_code(415);
    exit('Unsupported Think Sheet scan type.');
}

if (($_GET['file'] ?? '') === '1') {
    $extension = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg');
    $previewName = 'think-sheet-preview-' . preg_replace('/[^a-zA-Z0-9-]/', '', substr($recordId, 0, 12)) . '.' . $extension;
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $previewName . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

$page_title = 'Think Sheet Record';
$page_heading = $page_title;
$isModalPreview = ($_GET['modal'] ?? '') === '1';
$studentName = trim($record['first_name'] . ' ' . ($record['middle_name'] ?? '') . ' ' . $record['last_name']);
$previewUrl = 'view_think_sheet_record.php?id=' . rawurlencode($record['id']) . '&file=1';
if ($isModalPreview):
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
        <script>const previewThemeKey = <?= json_encode('crmc-theme-' . ($user['id'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;document.documentElement.dataset.themeKey=previewThemeKey;document.documentElement.dataset.theme=localStorage.getItem(previewThemeKey)||'light';</script>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
    <main class="think-sheet-modal-page">
    <?php
else:
    include 'includes/header.php';
endif;
?>
<style>
    .think-sheet-modal-page { padding: 16px; }
    .think-sheet-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 24px;
        margin: 0;
    }
    .think-sheet-details div { min-width: 0; }
    .think-sheet-details dt { color: var(--muted); font-size: 12px; font-weight: 700; }
    .think-sheet-details dd { margin: 5px 0 0; overflow-wrap: anywhere; }
    .think-sheet-preview-frame {
        display: block;
        width: 100%;
        height: min(76vh, 900px);
        border: 1px solid var(--line);
        background: #f6f3f4;
    }
    .think-sheet-preview-image {
        display: block;
        max-width: 100%;
        max-height: 76vh;
        margin: 0 auto;
        object-fit: contain;
    }
    @media (max-width: 640px) {
        .think-sheet-details { grid-template-columns: 1fr; }
    }
</style>

<?php if (!$isModalPreview): ?>
    <div class="page-actions">
        <p class="muted">Think Sheet record and status.</p>
        <a class="btn secondary" href="think_sheets.php">Back to Think Sheets</a>
    </div>
<?php endif; ?>

<section class="card">
    <h2>Think Sheet Information</h2>
    <dl class="think-sheet-details">
        <div><dt>Date</dt><dd><?= htmlspecialchars($record['recorded_on'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Student Name</dt><dd><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Department</dt><dd><?= htmlspecialchars($record['department'] ?: '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Course and Year Level</dt><dd><?= htmlspecialchars(trim($record['course'] . ' · ' . $record['year_level']) ?: '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Reported By</dt><dd><?= htmlspecialchars($record['reported_by'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Think Sheet Status</dt><dd><span class="badge <?= $record['status'] === 'reviewed' ? 'green' : 'gold' ?>"><?= htmlspecialchars(ucfirst($record['status']), ENT_QUOTES, 'UTF-8') ?></span></dd></div>
    </dl>
</section>

<section class="card">
    <h2>Think Sheet</h2>
    <?php if ($mime === 'application/pdf'): ?>
        <iframe class="think-sheet-preview-frame" src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>#toolbar=0&navpanes=0" title="Scanned Think Sheet"></iframe>
    <?php else: ?>
        <img class="think-sheet-preview-image" src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Scanned Think Sheet">
    <?php endif; ?>
</section>

<?php if ($isModalPreview): ?>
    </main>
    </body>
    </html>
<?php else: ?>
    <?php include 'includes/footer.php'; ?>
<?php endif; ?>
