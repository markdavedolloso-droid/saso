<?php
// Accepts a recovery email and starts the password-reset workflow.
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$developmentResetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, email, first_name FROM profiles WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $profile = $stmt->fetch();

        if ($profile) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $resetStmt = $pdo->prepare(
                'INSERT INTO password_resets(profile_id, token_hash, expires_at) VALUES(?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
            );
            $resetStmt->execute([$profile['id'], $tokenHash]);

            $resetLink = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/reset_password.php?token=' . urlencode($token);
            $subject = 'CRMC SASO password reset';
            $body = "Hello {$profile['first_name']},\n\nUse this link to reset your CRMC SASO password. It expires in one hour:\n\n{$resetLink}\n\nIf you did not request this, you can ignore this email.";
            $headers = "From: no-reply@crmc.edu.ph\r\n";

            if (!@mail($profile['email'], $subject, $body, $headers)) {
                $developmentResetLink = $resetLink;
            }
        }

        $message = 'If an account matches that email, password reset instructions have been sent.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | CRMC SASO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <section class="auth-visual">
            <div class="auth-seal" aria-hidden="true"><svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="100" cy="100" r="98" stroke="#fff" stroke-width="1.2"/><circle cx="100" cy="100" r="80" stroke="#fff" stroke-width="1.2"/><path d="M100 24 L118 74 L172 74 L128 105 L145 156 L100 124 L55 156 L72 105 L28 74 L82 74 Z" stroke="#fff" stroke-width="1.2"/></svg></div>
            <div class="auth-brand"><div class="auth-brand-mark">S</div><div class="auth-brand-text"><strong>CRMC SASO</strong><small>Management System</small></div></div>
            <div class="auth-visual-content"><h1 class="auth-headline">A secure way <em>back in.</em></h1><p class="auth-sub">Reset your account password and return to the CRMC SASO workspace.</p></div>
            <div class="auth-visual-foot"><span>Student Affairs &amp; Services Office</span><span>Account recovery</span></div>
        </section>
        <section class="auth-form-panel"><div class="auth-form-card">
            <h1>Forgot password?</h1>
            <p class="muted">Enter your account email and we will send reset instructions.</p>
            <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($developmentResetLink): ?><div class="alert success">Mail delivery is not configured. Local reset link: <a href="<?= htmlspecialchars($developmentResetLink) ?>">Reset your password</a></div><?php endif; ?>
            <form method="post"><?= csrf_field() ?>
                <label>Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label>
                <button class="btn primary full">Send Reset Link</button>
            </form>
            <p class="hint"><a href="login.php">Back to sign in</a></p>
        </div></section>
    </div>
</body>
</html>
