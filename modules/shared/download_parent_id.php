<?php
// Securely serves uploaded parent/guardian IDs to the owning student or SASO admin.
require_once 'config/database.php';
require_once 'includes/auth.php';

require_login();
$user = current_user();
$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    exit('Invalid Think Sheet.');
}

$stmt = $pdo->prepare("SELECT t.parent_id_file, t.student_id, s.profile_id
    FROM think_sheets t
    JOIN students s ON s.id = t.student_id
    WHERE t.id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || !$row['parent_id_file']) {
    http_response_code(404);
    exit('Parent/guardian ID not found.');
}

$isAdmin = ($user['role'] ?? '') === 'admin';
$isOwner = ($user['role'] ?? '') === 'student' && ($row['profile_id'] ?? '') === ($user['id'] ?? '');
if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    exit('You are not authorized to view this file.');
}

$file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'think_sheets' . DIRECTORY_SEPARATOR . basename($row['parent_id_file']);
if (!is_file($file)) {
    http_response_code(404);
    exit('Parent/guardian ID file is missing.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file);
$allowed = ['image/jpeg','image/png','application/pdf'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported file type.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="parent-guardian-id-' . preg_replace('/[^A-Za-z0-9_-]/', '', $id) . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
