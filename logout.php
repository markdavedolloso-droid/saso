<?php
// Records the sign-out action and clears the authenticated session.
// Session is already started by includes/auth.php.
require_once "config/database.php";
require_once "includes/auth.php";
if (!empty($_SESSION['user']['id'])) {
	try {
		log_action($pdo, 'logout', 'Authentication', $_SESSION['user']['id'], 'User signed out.');
	} catch (Throwable $e) {
		// Logging must never block sign-out.
	}
}
session_destroy();
header("Location: login.php");
exit;