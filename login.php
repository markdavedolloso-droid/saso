<?php
// Login page for the CRMC SASO system.
// It verifies credentials and then stores the authenticated user in the session.
require_once "config/database.php";
require_once "includes/auth.php";

if (!empty($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$registered = isset($_GET["registered"]);
$demoPasswords = [
    'admin@crmc.edu.ph' => 'Admin@123',
    'registrar@crmc.edu.ph' => 'Registrar@123',
];
$showDemoAccounts = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

$demoAccounts = $pdo->query("
    SELECT email, password, role, first_name, last_name
    FROM profiles
        WHERE status = 'active'
            AND email IN ('admin@crmc.edu.ph','registrar@crmc.edu.ph')
    ORDER BY FIELD(role, 'admin', 'registrar')
")->fetchAll();
$demoAccounts = array_values(array_filter($demoAccounts, static function ($account) use ($demoPasswords) {
    return isset($demoPasswords[$account['email']])
        && password_verify($demoPasswords[$account['email']], $account['password']);
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        log_action($pdo, 'login', 'Authentication', $user['id'], 'Successful login.');
        header("Location: dashboard.php");
        exit;
    }

    $error = "Invalid email or password.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | CRMC SASO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists('assets/css/style.css') ? filemtime('assets/css/style.css') : time() ?>">
    <style>
        :root {
            --maroon: #6b1023;
            --maroon-dark: #400713;
            --maroon-light: #8f1c34;
            --gold: #c79a43;
            --gold-light: #f7e7c4;
            --ink: #1f1619;
            --muted: #6e6467;
            --border: #e6dcde;
            --bg-page: #fbf9fa;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.auth-screen {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg-page);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
        }

        .auth-container {
            width: 100%;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
        }

        /* ===== Left Hero / Branding Panel ===== */
        .auth-hero-pane {
            position: relative;
            background: radial-gradient(circle at 50% 25%, rgba(199, 154, 67, 0.18), transparent 50%),
                        radial-gradient(circle at 50% 80%, rgba(255, 255, 255, 0.05), transparent 45%),
                        linear-gradient(145deg, #26040b 0%, #540c1c 45%, #7a1529 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            padding: 48px 48px;
            overflow: hidden;
            box-shadow: 10px 0 40px rgba(0, 0, 0, 0.15);
            z-index: 2;
        }

        .auth-hero-pane::before {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.07);
            top: -150px;
            left: -150px;
            pointer-events: none;
        }

        .auth-hero-pane::after {
            content: "";
            position: absolute;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            border: 1px solid rgba(199, 154, 67, 0.15);
            bottom: -100px;
            right: -80px;
            pointer-events: none;
        }

        .hero-top-badge {
            position: relative;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 99px;
            width: fit-content;
            margin: 0 auto;
        }

        .hero-top-badge span {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gold-light);
        }

        .hero-center {
            position: relative;
            z-index: 3;
            max-width: 520px;
            margin: auto;
            padding: 24px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* Standout centered circular logo without square container */
        .hero-logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
        }

        .hero-logo-wrap img {
            width: 150px !important;
            height: 150px !important;
            max-width: 150px !important;
            max-height: 150px !important;
            object-fit: contain !important;
            display: block;
            border-radius: 50%;
            filter: drop-shadow(0 14px 28px rgba(0, 0, 0, 0.5)) drop-shadow(0 0 24px rgba(199, 154, 67, 0.3));
            transition: transform 0.25s ease;
        }

        .hero-logo-wrap img:hover {
            transform: scale(1.05);
        }

        .hero-title {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.02em;
            margin-bottom: 12px;
            text-align: center;
        }

        .hero-title span {
            color: #f7ce7e;
        }

        .hero-subtitle {
            font-size: 14px;
            line-height: 1.65;
            color: #eed5da;
            margin: 0 auto 26px;
            text-align: center;
            max-width: 440px;
        }

        .hero-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }

        .feature-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #fdf2f4;
            text-align: center;
        }

        .feature-pill svg {
            width: 16px;
            height: 16px;
            color: #f7ce7e;
            flex-shrink: 0;
        }

        .hero-bottom-text {
            position: relative;
            z-index: 3;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 18px;
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.65);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding-top: 20px;
            width: 100%;
            text-align: center;
        }

        /* ===== Right Form Panel ===== */
        .auth-form-pane {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            background: var(--bg-page);
            overflow-y: auto;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 16px 40px rgba(74, 10, 24, 0.06), 0 2px 8px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
        }

        .auth-header {
            margin-bottom: 28px;
        }

        .auth-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .auth-header p {
            font-size: 14px;
            color: var(--muted);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 7px;
            letter-spacing: 0.01em;
        }

        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box svg.input-icon {
            position: absolute;
            left: 14px;
            width: 18px;
            height: 18px;
            color: #9c8e92;
            pointer-events: none;
            transition: color 0.18s;
        }

        .input-box input,
        .input-box select {
            width: 100%;
            height: 48px;
            padding: 0 44px 0 42px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--ink);
            background: #fdfcfc;
            outline: none;
            transition: all 0.2s ease;
        }

        .input-box input:focus,
        .input-box select:focus {
            border-color: var(--maroon);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(107, 16, 35, 0.1);
        }

        .input-box:focus-within svg.input-icon {
            color: var(--maroon);
        }

        .pw-toggle-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            padding: 6px;
            color: #9c8e92;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.18s;
        }

        .pw-toggle-btn:hover {
            color: var(--maroon);
            background: #f8edf0;
        }

        .pw-toggle-btn svg {
            width: 18px;
            height: 18px;
        }

        .pw-toggle-btn .icon-hide {
            display: none;
        }

        .pw-toggle-btn[aria-pressed="true"] .icon-show {
            display: none;
        }

        .pw-toggle-btn[aria-pressed="true"] .icon-hide {
            display: block;
        }

        .submit-btn {
            width: 100%;
            height: 50px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(107, 16, 35, 0.25);
            transition: all 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(107, 16, 35, 0.32);
            opacity: 0.96;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .auth-msg-box {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .auth-msg-box.danger {
            background: #fdf0f0;
            border: 1px solid #f9d5d5;
            color: #b42318;
        }

        .auth-msg-box.success {
            background: #edf8f2;
            border: 1px solid #c8ecd7;
            color: #1a6f43;
        }

        .auth-msg-box svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .auth-footer-links {
            margin-top: 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .auth-footer-links a {
            color: var(--maroon);
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
        }

        .auth-footer-links a:hover {
            text-decoration: underline;
        }

        .auth-footer-links .hint-text {
            font-size: 13px;
            color: var(--muted);
        }

        .demo-accounts {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .demo-accounts h3 {
            font-size: 13px;
            margin-bottom: 10px;
        }
        .demo-account {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
        }
        .demo-account-info {
            min-width: 0;
        }
        .demo-account-info strong {
            display: block;
            font-size: 12px;
        }
        .demo-account-info span {
            display: block;
            font-size: 11px;
            color: var(--muted);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .demo-account-info .demo-password {
            overflow: visible;
            text-overflow: clip;
        }
        .demo-account-info code {
            color: var(--ink);
            font-family: inherit;
            font-weight: 700;
        }
        .demo-account-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .use-demo-btn {
            border: 0;
            border-radius: 8px;
            padding: 8px 11px;
            background: var(--maroon);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
        }
        .copy-demo-btn {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 7px 9px;
            background: #fff;
            color: var(--maroon);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
        }
        .demo-accounts-note {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .admin-note-card {
            margin-top: 16px;
            padding: 10px 14px;
            border-radius: 10px;
            background: #fbf3f5;
            border: 1px dashed #e4cbd2;
            font-size: 11.5px;
            color: #635055;
            text-align: center;
        }

        .admin-note-card strong {
            color: var(--maroon);
        }

        @media (max-width: 980px) {
            .auth-container {
                grid-template-columns: 1fr;
            }

            .auth-hero-pane {
                padding: 40px 24px 32px;
                text-align: center;
                align-items: center;
            }

            .hero-center {
                padding: 12px 0;
                align-items: center;
                text-align: center;
            }

            .hero-logo-wrap img {
                width: 125px !important;
                height: 125px !important;
                max-width: 125px !important;
                max-height: 125px !important;
            }

            .hero-title {
                font-size: 24px;
                text-align: center;
            }

            .hero-subtitle {
                text-align: center;
            }

            .hero-features {
                grid-template-columns: 1fr;
                max-width: 320px;
            }

            .hero-bottom-text {
                display: none;
            }

            .auth-form-pane {
                padding: 32px 20px;
            }

            .auth-card {
                padding: 28px 22px;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body class="auth-screen">
<div class="auth-container">

    <!-- ===== Left Branding / Hero Panel ===== -->
    <section class="auth-hero-pane" aria-label="CRMC SASO Overview">
        <div class="hero-top-badge">
            <span>Official Portal</span>
            <small style="color:rgba(255,255,255,0.4)">•</small>
            <span style="color:#ffffff">Est. 1947</span>
        </div>

        <div class="hero-center">
            <div class="hero-logo-wrap">
                <img src="logo.png" alt="CRMC Official Seal">
            </div>

            <h2 class="hero-title">CRMC <span>SASO</span> Management System</h2>
            <p class="hero-subtitle">Student Affairs &amp; Services Office — Cebu Roosevelt Memorial Colleges, Upper Pandan, Bogo City, Cebu.</p>

        
        </div>

        <div class="hero-bottom-text" style="gap: 250px;">
            <span>Cebu Roosevelt Memorial Colleges</span>
            <span>All records synchronized</span>
        </div>
    </section>

    <!-- ===== Right Form Panel ===== -->
    <section class="auth-form-pane">
        <div class="auth-card">
            <div class="auth-header">
                <h1 style="text-align: center;">Welcome back</h1>
            </div>

            <?php if ($registered): ?>
            <div class="auth-msg-box success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>Account created successfully! You may now sign in.</span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="auth-msg-box danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="loginEmail">Email Address</label>
                    <div class="input-box">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 8.586 5.879a2 2 0 0 0 2.828 0L22 7"/></svg>
                        <input type="email" id="loginEmail" name="email" required
                               placeholder=" "
                               autocomplete="username"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                      <label for="loginPassword" style="margin-bottom:0;">Password</label>
                    <div class="input-box">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="loginPassword" name="password" required
                               placeholder=" "
                               autocomplete="current-password">
                        <button type="button" class="pw-toggle-btn" data-target="loginPassword" aria-pressed="false" aria-label="Toggle password visibility">
                            <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C5 19.5 1 12 1 12a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4.5c7 0 11 7.5 11 7.5a20.3 20.3 0 0 1-2.61 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                        </button>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
                       
                        <a href="forgot_password.php" style="font-size:12px;color:var(--maroon);font-weight:600;text-decoration:none;">Forgot password?</a>
                    </div>

                <button type="submit" class="submit-btn">
                    <span>Sign In</span>
                   
                </button>
            </form>

            <?php if ($showDemoAccounts && $demoAccounts): ?>
            <section class="demo-accounts" aria-labelledby="demoAccountsHeading">
                <h3 id="demoAccountsHeading">Local demo accounts</h3>
                <?php foreach ($demoAccounts as $account): ?>
                <div class="demo-account">
                    <div class="demo-account-info">
                        <strong><?= htmlspecialchars(ucfirst($account['role'])) ?></strong>
                        <span><?= htmlspecialchars($account['email']) ?></span>
                        <span class="demo-password">Password: <code><?= htmlspecialchars($demoPasswords[$account['email']]) ?></code></span>
                    </div>
                    <div class="demo-account-actions">
                        <button type="button" class="copy-demo-btn" data-copy-demo data-email="<?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?>" data-password="<?= htmlspecialchars($demoPasswords[$account['email']], ENT_QUOTES, 'UTF-8') ?>">Copy</button>
                        <button type="button" class="use-demo-btn" data-use-demo data-email="<?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?>" data-password="<?= htmlspecialchars($demoPasswords[$account['email']], ENT_QUOTES, 'UTF-8') ?>">Use</button>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </section>
            <?php endif; ?>

            <div class="auth-footer-links">
                <div class="hint-text">
                    New student? <a href="signup.php">Create account</a>
                </div>
               
            </div>
        </div>
    </section>

</div>

<script>
function useDemoAccount(email, password) {
    const emailInput = document.getElementById('loginEmail');
    const passwordInput = document.getElementById('loginPassword');
    if (emailInput && passwordInput) {
        emailInput.value = email;
        passwordInput.value = password;
        emailInput.focus();
    }
}

document.querySelectorAll('[data-use-demo]').forEach(button => {
    button.addEventListener('click', () => useDemoAccount(button.dataset.email, button.dataset.password));
});

document.querySelectorAll('[data-copy-demo]').forEach(button => {
    button.addEventListener('click', async () => {
        const text = `Email: ${button.dataset.email}\nPassword: ${button.dataset.password}`;
        try {
            await navigator.clipboard.writeText(text);
        } catch (error) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            textarea.remove();
            if (!copied) {
                button.textContent = 'Copy failed';
                return;
            }
        }
        button.textContent = 'Copied';
        window.setTimeout(() => { button.textContent = 'Copy'; }, 1500);
    });
});
</script>
<script src="assets/js/app.js?v=5"></script>
</body>
</html>
