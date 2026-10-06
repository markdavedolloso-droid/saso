<?php
// Displays the signed-in student's profile and account-linked record details.
$page_title = "My Profile";
$page_heading = "My Profile";
require_once "config/database.php";
require_once "includes/auth.php";
require_login();

// Only students can access this page
if (($_SESSION["user"]["role"] ?? "") !== "student") {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION["user"];
$student = get_student_for_user($pdo);

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword     = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($newPassword === "") {
        $error = "New password cannot be empty.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM profiles WHERE id = ? LIMIT 1");
        $stmt->execute([$user["id"]]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            $error = "Current password is incorrect.";
        } else {
            $upd = $pdo->prepare("UPDATE profiles SET password = ? WHERE id = ?");
            $upd->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user["id"]]);
            log_action($pdo, "changed_password", "Profile", $user["id"], "Student changed their password.");
            $message = "Password changed successfully.";
        }
    }
}

include "includes/header.php";
?>
<?php if ($message): ?>
<div class="alert success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Hero banner -->
<div class="profile-hero">
    <div class="profile-avatar-lg">
        <?= strtoupper(substr($user["first_name"], 0, 1) . substr($user["last_name"], 0, 1)) ?>
    </div>
    <div class="profile-hero-text">
        <h2><?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?></h2>
        <p><?= htmlspecialchars($user["email"]) ?></p>
    </div>
    <?php if ($student): ?>
    <div class="profile-hero-badge">
        <?= htmlspecialchars($student["status"] ?? "Active") ?>
    </div>
    <?php endif; ?>
</div>

<div class="profile-grid">

    <!-- Left: Student Information -->
    <section class="card">
        <div class="card-head">
            <h3>Student Information</h3>
        </div>

        <?php if ($student): ?>
        <div class="profile-info-list">
            <div class="profile-info-row">
                <label>Student Number</label>
                <span><?= htmlspecialchars($student["student_number"]) ?></span>
            </div>
            <div class="profile-info-row">
                <label>Status</label>
                <span><?= htmlspecialchars(ucfirst($student["status"] ?? "")) ?></span>
            </div>
            <div class="profile-info-row">
                <label>First Name</label>
                <span><?= htmlspecialchars($student["first_name"]) ?></span>
            </div>
            <div class="profile-info-row">
                <label>Last Name</label>
                <span><?= htmlspecialchars($student["last_name"]) ?></span>
            </div>
            <div class="profile-info-row">
                <label>Middle Name</label>
                <span><?= $student["middle_name"] !== "" ? htmlspecialchars($student["middle_name"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
            <div class="profile-info-row">
                <label>Gender</label>
                <span><?= $student["gender"] !== "" ? htmlspecialchars($student["gender"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
            <div class="profile-info-row">
                <label>Course</label>
                <span><?= $student["course"] !== "" ? htmlspecialchars($student["course"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
            <div class="profile-info-row">
                <label>Year Level</label>
                <span><?= htmlspecialchars($student["year_level"] ?? "—") ?></span>
            </div>
            <div class="profile-info-row">
                <label>Section</label>
                <span><?= $student["section"] !== "" ? htmlspecialchars($student["section"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
            <div class="profile-info-row">
                <label>Birth Date</label>
                <span><?= $student["birth_date"] ? htmlspecialchars($student["birth_date"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
            <div class="profile-info-row">
                <label>Email</label>
                <span><?= $student["email"] !== "" ? htmlspecialchars($student["email"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
            <div class="profile-info-row">
                <label>Phone</label>
                <span><?= $student["phone"] !== "" ? htmlspecialchars($student["phone"]) : "<span class=\"empty\">—</span>" ?></span>
            </div>
        </div>
        <?php else: ?>
        <p class="muted" style="padding:16px">No student record is linked to your account. Please contact the SASO office.</p>
        <?php endif; ?>
    </section>

    <!-- Right: Change Password -->
    <section class="card profile-pw-card">
        <div class="card-head">
            <h3 style="text-align: center;"> Change Password</h3>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <label>Current Password
                <div class="field-icon">
                    <input type="password" name="current_password" id="pwCurrent" required placeholder="••••••••" autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="pwCurrent" aria-pressed="false" aria-label="Show password">
                        <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C5 19.5 1 12 1 12a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4.5c7 0 11 7.5 11 7.5a20.3 20.3 0 0 1-2.61 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                    </button>
                </div>
            </label>
            <label>New Password
                <div class="field-icon">
                    <input type="password" name="new_password" id="pwNew" required minlength="6" placeholder="Min. 6 characters" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="pwNew" aria-pressed="false" aria-label="Show password">
                        <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C5 19.5 1 12 1 12a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4.5c7 0 11 7.5 11 7.5a20.3 20.3 0 0 1-2.61 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                    </button>
                </div>
            </label>
            <label>Confirm New Password
                <div class="field-icon">
                    <input type="password" name="confirm_password" id="pwConfirm" required minlength="6" placeholder="Repeat new password" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="pwConfirm" aria-pressed="false" aria-label="Show password">
                        <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7.5 11-7.5S23 12 23 12s-4 7.5-11 7.5S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C5 19.5 1 12 1 12a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4.5c7 0 11 7.5 11 7.5a20.3 20.3 0 0 1-2.61 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                    </button>
                </div>
            </label>
            <div style="text-align:right;margin-top:8px">
                <button type="submit" class="btn primary">Update Password</button>
            </div>
        </form>
    </section>

</div>
<?php include "includes/footer.php"; ?>
