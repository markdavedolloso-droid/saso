<?php
// Shared authentication and session helpers for all app pages.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Redirects unauthenticated users to the login page.
function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }

    global $pdo;
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT status FROM profiles WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user']['id'] ?? '']);
        if ($stmt->fetchColumn() !== 'active') {
            $_SESSION = [];
            session_destroy();
            header('Location: login.php?deactivated=1');
            exit;
        }
    }
}

// Ensures the current user has one of the allowed roles.
function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        header('Location: dashboard.php');
        exit;
    }
}

// Returns the currently logged-in user data.
function current_user(): array {
    require_login();
    return $_SESSION['user'];
}

// Generates or reuses the CSRF token used for form protection.
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Creates the hidden CSRF input field used in HTML forms.
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Verifies the submitted token before processing POST requests.
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back and try again.');
    }
}

// Logs a user action into the audit_logs table when available.
// Logging must never break the page (e.g. logout) even with a stale session.
function log_action(PDO $pdo, string $action, string $module, ?string $recordId = null, ?string $details = null): void {
    try {
        $profileId = $_SESSION['user']['id'] ?? null;
        if ($profileId !== null) {
            $profileStmt = $pdo->prepare('SELECT id FROM profiles WHERE id = ? LIMIT 1');
            $profileStmt->execute([$profileId]);
            $profileId = $profileStmt->fetchColumn() ?: null;
        }

        $stmt = $pdo->prepare('INSERT INTO audit_logs(profile_id, action, module, record_id, details) VALUES(?,?,?,?,?)');
        try {
            $stmt->execute([$profileId, $action, $module, $recordId, $details]);
        } catch (PDOException $insertException) {
            // Stale session or deleted profile: retry without a profile link.
            if (($insertException->errorInfo[1] ?? null) === 1452) {
                $stmt->execute([null, $action, $module, $recordId, $details]);
            } else {
                throw $insertException;
            }
        }
        $oldLogs = $pdo->query('SELECT * FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT 18446744073709551615 OFFSET 15')->fetchAll();
        if ($oldLogs) {
            $archive = $pdo->prepare('INSERT INTO audit_logs_archive(original_id,profile_id,action,module,record_id,details,created_at) VALUES(?,?,?,?,?,?,?)');
            $delete = $pdo->prepare('DELETE FROM audit_logs WHERE id=?');
            foreach ($oldLogs as $oldLog) {
                $archive->execute([$oldLog['id'],$oldLog['profile_id'],$oldLog['action'],$oldLog['module'],$oldLog['record_id'],$oldLog['details'],$oldLog['created_at']]);
                $delete->execute([$oldLog['id']]);
            }
        }
    } catch (PDOException $exception) {
        $code = $exception->errorInfo[1] ?? null;
        // 1146 = missing table, 1452 = stale/deleted profile FK: never break the page for audit logging.
        if ($code !== 1146 && $code !== 1452) {
            throw $exception;
        }
    }
}

// Links a student profile to an existing student record when the email matches.
function get_student_for_user(PDO $pdo): ?array {
    if (($_SESSION['user']['role'] ?? '') !== 'student') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM students WHERE profile_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user']['id']]);
    $student = $stmt->fetch();
    if ($student) {
        return $student;
    }

    $profileStmt = $pdo->prepare('SELECT email FROM profiles WHERE id = ? LIMIT 1');
    $profileStmt->execute([$_SESSION['user']['id']]);
    $email = trim((string) $profileStmt->fetchColumn());
    if ($email === '') {
        return null;
    }

    $candidateStmt = $pdo->prepare('SELECT id FROM students WHERE profile_id IS NULL AND email <> "" AND email = ?');
    $candidateStmt->execute([$email]);
    $candidates = $candidateStmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($candidates) !== 1) {
        return null;
    }

    $linkStmt = $pdo->prepare('UPDATE students SET profile_id = ? WHERE id = ? AND profile_id IS NULL');
    $linkStmt->execute([$_SESSION['user']['id'], $candidates[0]]);
    if ($linkStmt->rowCount() === 1) {
        log_action($pdo, 'linked', 'Student Account', $candidates[0], 'Existing student account was automatically linked by matching email.');
    }

    $stmt->execute([$_SESSION['user']['id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Creates a single in-app notification for one user account.
 */
function notify(PDO $pdo, ?string $profileId, string $title, string $message, string $link = ''): void {
    if (!$profileId) {
        return;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO notifications(profile_id, title, message, link) VALUES(?,?,?,?)');
        $stmt->execute([$profileId, $title, $message, $link]);
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1146) {
            throw $exception;
        }
    }
}

/**
 * Sends the same notification to everyone who has a specific role.
 */
function notify_role(PDO $pdo, string $role, string $title, string $message, string $link = '', ?string $excludeProfileId = null): void {
    try {
        $stmt = $pdo->prepare('SELECT id FROM profiles WHERE role = ?');
        $stmt->execute([$role]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $profileId) {
            if ($excludeProfileId && $profileId === $excludeProfileId) {
                continue;
            }
            notify($pdo, $profileId, $title, $message, $link);
        }
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1146) {
            throw $exception;
        }
    }
}

// Counts unread notifications for a given profile.
function unread_notification_count(PDO $pdo, string $profileId): int {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE profile_id=? AND is_read=0');
        $stmt->execute([$profileId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) === 1146) {
            return 0;
        }
        throw $exception;
    }
}

/**
 * Maps Good Moral statuses to their CSS badge classes.
 */
function good_moral_badge_class(string $status): string {
    switch ($status) {
        case 'completed':
            return 'green';
        case 'rejected':
            return 'red';
        case 'on_hold':
            return 'orange';
        case 'saso_verified':
        case 'registrar_processing':
            return 'blue';
        case 'pending_saso':
        default:
            return 'gold';
    }
}

// Retrieves recent notifications for a profile with a sensible default limit.
function recent_notifications(PDO $pdo, string $profileId, int $limit = 8): array {
    try {
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE profile_id=? ORDER BY created_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$profileId]);
        return $stmt->fetchAll();
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) === 1146) {
            return [];
        }
        throw $exception;
    }
}
?>
