<?php
// Lists notifications and processes read-status updates for signed-in users.
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE profile_id=? AND is_read=0');
        $stmt->execute([$user['id']]);
    } elseif ($action === 'mark_read' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND profile_id=?');
        $stmt->execute([$_POST['id'], $user['id']]);
    }
}

$redirect = $_POST['redirect'] ?? ($_GET['redirect'] ?? 'dashboard.php');
// Only allow redirecting back to a local .php page, never an external URL.
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $redirect)) $redirect = 'dashboard.php';
header('Location: ' . $redirect);
exit;
