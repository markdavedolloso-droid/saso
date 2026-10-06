<?php
// Validates a password-reset token and saves the user's new password.
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$validToken = false;
$error = '';
$message = '';

if ($token !== '') {
    $stmt = $pdo->prepare(
        "SELECT r.id, r.profile_id FROM password_resets r JOIN profiles p ON p.id = r.profile_id WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND p.status = 'active' LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();
    $validToken = (bool) $reset;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';

    if (!$validToken) {
        $error = 'This reset link is invalid or has expired.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'Passwords do not match.';
    } else {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE profiles SET password = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $reset['profile_id']]);
            $consume = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
            $consume->execute([$reset['id']]);
            $pdo->commit();
            $validToken = false;
            $message = 'Your password has been reset. You can now sign in.';
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $error = 'Unable to reset the password. Please request a new link.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | CRMC SASO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <section class="auth-visual"><div class="auth-seal" aria-hidden="true"><svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="100" cy="100" r="98" stroke="#fff" stroke-width="1.2"/><circle cx="100" cy="100" r="80" stroke="#fff" stroke-width="1.2"/><path d="M100 24 L118 74 L172 74 L128 105 L145 156 L100 124 L55 156 L72 105 L28 74 L82 74 Z" stroke="#fff" stroke-width="1.2"/></svg></div><div class="auth-brand"><div class="auth-brand-mark">S</div><div class="auth-brand-text"><strong>CRMC SASO</strong><small>Management System</small></div></div><div class="auth-visual-content"><h1 class="auth-headline">Choose a stronger <em>key.</em></h1><p class="auth-sub">Set a new password for your CRMC SASO account.</p></div><div class="auth-visual-foot"><span>Student Affairs &amp; Services Office</span><span>Account recovery</span></div></section>
        <section class="auth-form-panel"><div class="auth-form-card">
            <h1>Reset password</h1>
            <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><p class="hint"><a href="login.php">Go to sign in</a></p>
            <?php elseif (!$validToken): ?><div class="alert danger"><?= htmlspecialchars($error ?: 'This reset link is invalid or has expired.') ?></div><p class="hint"><a href="forgot_password.php">Request a new reset link</a></p>
            <?php else: ?>
                <p class="muted">Use at least 8 characters for your new password.</p>
                <?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <label>New password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                    <label>Confirm password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                    <button class="btn primary full">Reset Password</button>
                </form>
            <?php endif; ?>
        </div></section>
    </div>
</body>
</html>
