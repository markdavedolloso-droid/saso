<?php
require_once __DIR__ . "/auth.php";
require_login();

$current = basename($_SERVER["PHP_SELF"]);
$user = $_SESSION["user"];
$unreadCount = isset($pdo) ? unread_notification_count($pdo, $user["id"]) : 0;
$headerNotifications = isset($pdo) ? recent_notifications($pdo, $user["id"], 8) : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? "CRMC SASO") ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>document.documentElement.dataset.themeKey='crmc-theme-<?= htmlspecialchars($user["id"], ENT_QUOTES) ?>';document.documentElement.dataset.theme=localStorage.getItem(document.documentElement.dataset.themeKey)||'light';</script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Main sidebar">
        <div class="brand">
            <div class="brand-logo" style="background:transparent;box-shadow:none;padding:0;">
                <img src="logo.png" alt="CRMC Logo" style="width:42px;height:42px;object-fit:contain;">
            </div>
            <div>
                <strong>CRMC SASO</strong>
                <small>Management System</small>
            </div>
        </div>

        <nav aria-label="Primary navigation">
            <a class="<?= $current === "dashboard.php" ? "active" : "" ?>" href="dashboard.php"><img class="nav-icon" src="assets/icons/grid-outline.svg" alt="" aria-hidden="true">Dashboard</a>
            <?php if ($user["role"] === "admin"): ?>
                <a class="<?= $current === "violations.php" ? "active" : "" ?>" href="violations.php"><img class="nav-icon" src="assets/icons/warning-outline.svg" alt="" aria-hidden="true">Discipline Management</a>
                <a class="<?= $current === "think_sheets.php" ? "active" : "" ?>" href="think_sheets.php"><img class="nav-icon" src="assets/icons/document-text-outline.svg" alt="" aria-hidden="true">Think Sheet Repository</a>
                <a class="<?= $current === "good_moral.php" ? "active" : "" ?>" href="good_moral.php"><img class="nav-icon" src="assets/icons/checkmark-circle-outline.svg" alt="" aria-hidden="true">Good Moral Verification</a>
                <a class="<?= $current === "analytics.php" ? "active" : "" ?>" href="analytics.php"><img class="nav-icon" src="assets/icons/analytics-outline.svg" alt="" aria-hidden="true">AI Analytics</a>
                <a class="<?= $current === "reports.php" ? "active" : "" ?>" href="reports.php"><img class="nav-icon" src="assets/icons/bar-chart-outline.svg" alt="" aria-hidden="true">Reports</a>
                <a class="<?= $current === "accounts.php" ? "active" : "" ?>" href="accounts.php"><img class="nav-icon" src="assets/icons/settings-outline.svg" alt="" aria-hidden="true">User Accounts</a>
                <a class="<?= $current === "archive.php" ? "active" : "" ?>" href="archive.php"><img class="nav-icon" src="assets/icons/archive-outline.svg" alt="" aria-hidden="true">Archive</a>
                <a class="<?= $current === "audit_logs.php" ? "active" : "" ?>" href="audit_logs.php"><img class="nav-icon" src="assets/icons/time-outline.svg" alt="" aria-hidden="true">Audit Logs</a>
            <?php elseif ($user["role"] === "registrar"): ?>
                <a class="<?= $current === "students.php" ? "active" : "" ?>" href="students.php"><img class="nav-icon" src="assets/icons/people-outline.svg" alt="" aria-hidden="true">Student Records</a>
                <a class="<?= $current === "archive.php" ? "active" : "" ?>" href="archive.php"><img class="nav-icon" src="assets/icons/archive-outline.svg" alt="" aria-hidden="true">Student Archive</a>
                <a class="<?= $current === "good_moral.php" ? "active" : "" ?>" href="good_moral.php"><img class="nav-icon" src="assets/icons/checkmark-circle-outline.svg" alt="" aria-hidden="true">Good Moral Requests</a>
            <?php else: ?>
                <a class="<?= $current === "profile.php" ? "active" : "" ?>" href="profile.php"><img class="nav-icon" src="assets/icons/person-outline.svg" alt="" aria-hidden="true">My Profile</a>
                <a class="<?= $current === "violations.php" ? "active" : "" ?>" href="violations.php"><img class="nav-icon" src="assets/icons/warning-outline.svg" alt="" aria-hidden="true">My Disciplinary Record</a>
                <a class="<?= $current === "think_sheets.php" ? "active" : "" ?>" href="think_sheets.php"><img class="nav-icon" src="assets/icons/document-text-outline.svg" alt="" aria-hidden="true">My Think Sheets</a>
                <a class="<?= $current === "good_moral.php" ? "active" : "" ?>" href="good_moral.php"><img class="nav-icon" src="assets/icons/checkmark-circle-outline.svg" alt="" aria-hidden="true">My Good Moral</a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-user">
            <div class="avatar">
                <?php
                $headerFirstName = $user["first_name"] ?? "";
                $headerLastName = $user["last_name"] ?? "";
                $headerInitials = strtoupper(
                    ($headerFirstName !== "" ? substr($headerFirstName, 0, 1) : "") .
                    ($headerLastName !== "" ? substr($headerLastName, 0, 1) : "")
                );
                if ($headerInitials === "") {
                    $headerInitials = strtoupper(substr($user["email"] ?? "U", 0, 1));
                }
                $headerDisplayName = trim($headerFirstName . " " . $headerLastName);
                if ($headerDisplayName === "") {
                    $headerDisplayName = $user["email"] ?? "User";
                }
                ?>
                <?= htmlspecialchars($headerInitials) ?>
            </div>
            <div class="user-text">
                <strong><?= htmlspecialchars($headerDisplayName) ?></strong>
                <small><?= htmlspecialchars($user["role"]) ?></small>
            </div>
            <a class="logout-icon" href="logout.php" title="Sign out" data-confirm-navigation data-confirmation="Are you sure you want to log out?"><img class="nav-icon" src="assets/icons/log-out-outline.svg" alt="" aria-hidden="true"></a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="menu-btn" id="menuBtn" aria-label="Open navigation"><img class="control-icon" src="assets/icons/menu-outline.svg" alt="" aria-hidden="true"></button>
            <div>
                <h2><?= htmlspecialchars($page_heading ?? "Dashboard") ?></h2>
                <small><?= date("l, F j, Y") ?></small>
            </div>
            <div class="topbar-actions">
                <details class="notif-bell" id="notifBell">
                    <summary aria-label="Notifications" title="Notifications">
                        <img class="control-icon" src="assets/icons/notifications-outline.svg" alt="" aria-hidden="true"><?php if ($unreadCount > 0): ?><span class="notif-count"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
                    </summary>
                    <div class="notif-panel">
                        <div class="notif-panel-head">
                            <strong>Notifications</strong>
                            <?php if ($unreadCount > 0): ?>
                            <form method="post" action="notifications.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_all_read">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($current) ?>">
                                <button class="link-btn" type="submit">Mark all read</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="notif-list">
                            <?php if (!$headerNotifications): ?>
                                <p class="notif-empty">No notifications yet.</p>
                            <?php endif; ?>
                            <?php foreach ($headerNotifications as $n): ?>
                                <form method="post" action="notifications.php" class="notif-item-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($n['id'], ENT_QUOTES) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($n['link'] ?: $current, ENT_QUOTES) ?>">
                                    <button class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" type="submit">
                                        <span class="notif-title"><?= htmlspecialchars($n['title']) ?></span>
                                        <span class="notif-message"><?= htmlspecialchars($n['message']) ?></span>
                                        <span class="notif-time"><?= htmlspecialchars($n['created_at']) ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <img class="control-icon theme-icon theme-moon" src="assets/icons/moon-outline.svg" alt="" aria-hidden="true">
                    <img class="control-icon theme-icon theme-sun" src="assets/icons/sunny-outline.svg" alt="" aria-hidden="true">
                </button>
                <div class="top-user"><?= htmlspecialchars($user["email"] ?? "") ?></div>
            </div>
        </header>
        <main class="content">
