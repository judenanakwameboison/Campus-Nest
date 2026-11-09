<?php
// includes/auth.php - session start, idle timeout, access guards
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/functions.php';

// Log out after 30 minutes of inactivity
if (!empty($_SESSION['user_id'])) {
    if (time() - ($_SESSION['last_activity'] ?? time()) > 1800) {
        session_unset(); session_destroy();
        session_start();
        flash('warn', 'You were logged out after 30 minutes of inactivity.');
        redirect('/login.php');
    }
    $_SESSION['last_activity'] = time();
}
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_login(): void { if (!is_logged_in()) { redirect('/login.php'); } }
function require_role(string $role): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) { http_response_code(403); exit('Access denied.'); }
}
