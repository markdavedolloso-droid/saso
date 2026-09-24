<?php
// Student sign-up screen.
// It validates the form input, verifies password confirmation, and creates a linked profile record.
require_once "config/database.php";
require_once "includes/auth.php";

if (!empty($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$values = [
    "first_name" => "",
    "last_name" => "",
    "email" => "",
    "role" => "student",
    "student_number" => "",
    "course" => "",
    "year_level" => "1st Year",
    "section" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    foreach ($values as $field => $value) {
        $values[$field] = trim($_POST[$field] ?? $value);
    }

    $values["role"] = "student";
    $password = $_POST["password"] ?? "";
    $values["student_number"] = trim($_POST["student_number"] ?? "");
    $values["course"] = trim($_POST["course"] ?? "");
    $values["year_level"] = trim($_POST["year_level"] ?? "1st Year");
    $values["section"] = trim($_POST["section"] ?? "");
    $password_confirmation = $_POST["password_confirmation"] ?? "";

    if ($password !== $password_confirmation) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($values["role"] === "student" && $values["student_number"] === "") {
        $error = "Student Number is required for student accounts.";
    } elseif ($values["role"] === "student" && !filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid school email address.";
    } else {
        try {
            $pdo->beginTransaction();
            $studentRecord = null;

            if ($values["role"] === "student") {
                $studentLookup = $pdo->prepare("SELECT id, profile_id, first_name, last_name, email FROM students WHERE student_number=? LIMIT 1 FOR UPDATE");
                $studentLookup->execute([$values["student_number"]]);
                $studentRecord = $studentLookup->fetch();

                if (!$studentRecord) {
                    throw new RuntimeException("Student record not found. Please verify your Student Number or contact the appropriate CRMC office.");
                }
                if (!empty($studentRecord["profile_id"])) {
                    throw new RuntimeException("This Student Number is already linked to an account.");
                }
                if ($studentRecord["email"] !== "" && strcasecmp($studentRecord["email"], $values["email"]) !== 0) {
                    throw new RuntimeException("The email address does not match the registered student record.");
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO profiles(email, password, role, first_name, last_name) VALUES(?,?,?,?,?)"
            );
            $stmt->execute([
                $values["email"],
                password_hash($password, PASSWORD_DEFAULT),
                $values["role"],
                $values["first_name"],
                $values["last_name"],
            ]);

            if ($values["role"] === "student") {
                $profileStmt = $pdo->prepare("SELECT id FROM profiles WHERE email=? LIMIT 1");
                $profileStmt->execute([$values["email"]]);
                $profileId = $profileStmt->fetchColumn();

                $studentStmt = $pdo->prepare("UPDATE students SET profile_id=? WHERE id=? AND profile_id IS NULL");
                $studentStmt->execute([$profileId, $studentRecord["id"]]);

                if ($studentStmt->rowCount() !== 1) {
                    throw new RuntimeException("This Student Number could not be linked to the new account.");
                }

                $auditStmt = $pdo->prepare("INSERT INTO audit_logs(profile_id, action, module, record_id, details) VALUES(?,?,?,?,?)");
                $auditStmt->execute([$profileId, "linked", "Student Account", $studentRecord["id"], "Student account created and linked to the existing student record."]);
            }

            $pdo->commit();
            header("Location: login.php?registered=1");
            exit;
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getCode() === "23000"
                ? "An account with that email already exists."
                : "Unable to create your account right now.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | CRMC SASO</title>
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
            grid-template-columns: 1fr 1.2fr;
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
            margin: 0 auto 24px;
            text-align: center;
            max-width: 440px;
        }

        .hero-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            width: 100%;
            max-width: 440px;
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
            padding: 40px 36px;
            background: var(--bg-page);
            overflow-y: auto;
        }

        .auth-card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border-radius: 24px;
            padding: 36px 40px;
            box-shadow: 0 16px 40px rgba(74, 10, 24, 0.06), 0 2px 8px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
        }

        .auth-header {
            margin-bottom: 22px;
        }

        .auth-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .auth-header p {
            font-size: 13.5px;
            color: var(--muted);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
            letter-spacing: 0.01em;
        }

        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box svg.input-icon {
            position: absolute;
            left: 13px;
            width: 17px;
            height: 17px;
            color: #9c8e92;
            pointer-events: none;
            transition: color 0.18s;
        }

        .input-box input,
        .input-box select {
            width: 100%;
            height: 44px;
            padding: 0 40px 0 38px;
            border: 1.5px solid var(--border);
            border-radius: 11px;
            font-size: 13.5px;
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
            right: 10px;
            background: transparent;
            border: none;
            padding: 5px;
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
            width: 17px;
            height: 17px;
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
            height: 48px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(107, 16, 35, 0.25);
            transition: all 0.2s ease;
            margin-top: 6px;
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
            margin-bottom: 18px;
        }

        .auth-msg-box.danger {
            background: #fdf0f0;
            border: 1px solid #f9d5d5;
            color: #b42318;
        }

        .auth-msg-box svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .student-info-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fbf5f0;
            border: 1px solid #fae1ce;
            padding: 11px 14px;
            border-radius: 11px;
            font-size: 12px;
            color: #8c4c1a;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .student-info-note svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .auth-footer-links {
            margin-top: 20px;
            text-align: center;
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

        @media (max-width: 980px) {
            .auth-container {
                grid-template-columns: 1fr;
            }

            .auth-hero-pane {
                padding: 36px 24px;
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
                font-size: 22px;
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
                padding: 28px 18px;
            }

            .auth-card {
                padding: 26px 20px;
                border-radius: 20px;
            }

            .form-grid-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body class="auth-screen">
<div class="auth-container">

    <!-- ===== Left Branding / Hero Panel ===== -->
    <section class="auth-hero-pane" aria-label="CRMC SASO Overview">
        <div class="hero-top-badge">
            <span>Student Registration</span>
            <small style="color:rgba(255,255,255,0.4)">•</small>
            <span style="color:#ffffff">Est. 1947</span>
        </div>

        <div class="hero-center">
            <div class="hero-logo-wrap">
                <img src="logo.png" alt="CRMC Official Seal">
            </div>

            <h2 class="hero-title">One Student Account,<br><span>Your Whole Record</span></h2>
            <p class="hero-subtitle">Link your official CRMC Student Number to track discipline cases, digital Think Sheets, Good Moral verification, and evaluations.</p>

           
        </div>

        <div class="hero-bottom-text">
            <span>Cebu Roosevelt Memorial Colleges</span>
            <span>Secure Student Records</span>
        </div>
    </section>

    <!-- ===== Right Form Panel ===== -->
    <section class="auth-form-pane">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Enter your information as registered in the CRMC SASO database.</p>
            </div>

            <div class="student-info-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span><strong>Notice:</strong> Your Student Number must already be registered by SASO or Registrar to create an online account.</span>
            </div>

            <?php if ($error): ?>
            <div class="auth-msg-box danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <?= csrf_field() ?>

                <!-- Name Row -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="reg_first_name">First Name</label>
                        <div class="input-box">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" id="reg_first_name" name="first_name" required
                                   placeholder="e.g. Juan"
                                   autocomplete="given-name"
                                   value="<?= htmlspecialchars($values['first_name']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reg_last_name">Last Name</label>
                        <div class="input-box">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" id="reg_last_name" name="last_name" required
                                   placeholder="e.g. dela Cruz"
                                   autocomplete="family-name"
                                   value="<?= htmlspecialchars($values['last_name']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="reg_email">School Email Address</label>
                    <div class="input-box">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 8.586 5.879a2 2 0 0 0 2.828 0L22 7"/></svg>
                        <input type="email" id="reg_email" name="email" required
                               placeholder="your.email@crmc.edu.ph"
                               autocomplete="email"
                               value="<?= htmlspecialchars($values['email']) ?>">
                    </div>
                </div>

                <!-- Student Number -->
                <div class="form-group">
                    <label for="reg_student_number">Student Number</label>
                    <div class="input-box">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        <input type="text" id="reg_student_number" name="student_number" required
                               placeholder="e.g. 2026-0001"
                               value="<?= htmlspecialchars($values['student_number']) ?>">
                    </div>
                </div>

                <!-- Course & Year Level Row -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="reg_course">Course / Track</label>
                        <div class="input-box">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <input type="text" id="reg_course" name="course"
                                   placeholder="e.g. BSIT / BSED"
                                   value="<?= htmlspecialchars($values['course']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reg_year_level">Year / Grade Level</label>
                        <div class="input-box">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            <select id="reg_year_level" name="year_level">
                                <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','12 Grade','11 Grade','10 Grade','9 Grade','8 Grade','7 Grade'] as $lvl): ?>
                                <option<?= $values['year_level'] === $lvl ? ' selected' : '' ?>><?= $lvl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section -->
                <div class="form-group">
                    <label for="reg_section">Section</label>
                    <div class="input-box">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        <input type="text" id="reg_section" name="section"
                               placeholder="e.g. A"
                               value="<?= htmlspecialchars($values['section']) ?>">
                    </div>
                </div>

                <!-- Password Row -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="signupPassword">Create Password</label>
                        <div class="input-box">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="signupPassword" name="password" required minlength="6"
                                   placeholder="Min. 6 chars"
                                   autocomplete="new-password">
                            <button type="button" class="pw-toggle-btn" data-target="signupPassword" aria-pressed="false" aria-label="Toggle password visibility">
                                <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C5 19.5 1 12 1 12a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4.5c7 0 11 7.5 11 7.5a20.3 20.3 0 0 1-2.61 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="signupPasswordConfirm">Confirm Password</label>
                        <div class="input-box">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="signupPasswordConfirm" name="password_confirmation" required minlength="6"
                                   placeholder="Repeat password"
                                   autocomplete="new-password">
                            <button type="button" class="pw-toggle-btn" data-target="signupPasswordConfirm" aria-pressed="false" aria-label="Toggle password visibility">
                                <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C5 19.5 1 12 1 12a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4.5c7 0 11 7.5 11 7.5a20.3 20.3 0 0 1-2.61 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <span>Complete Registration</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <div class="auth-footer-links">
                <span style="font-size:13px;color:var(--muted)">Already registered? <a href="login.php">Sign in</a></span>
            </div>
        </div>
    </section>

</div>

<script src="assets/js/app.js?v=5"></script>
</body>
</html>
