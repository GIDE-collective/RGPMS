<?php
/**
 * RG PMS - Authentication & session handling.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

// Enforce idle + absolute timeouts on every request
if (!session_enforce_timeouts() && isset($_SESSION['user_id'])) {
    unset($_SESSION['user_id']);
}

/**
 * Get the currently signed-in user (or null).
 */
function current_user(): ?array {
    static $cached = null;
    static $loaded = false;

    if ($loaded) return $cached;
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return $cached = null;
    }

    $stmt = db()->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy_session('user_removed');
        return $cached = null;
    }

    return $cached = $user;
}

/**
 * Require authentication.
 */
function require_login(): void {
    if (!current_user()) {
        $_SESSION['__intended'] = $_SERVER['REQUEST_URI'] ?? '';
        flash_set('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
}

/**
 * Require one or more roles.
 */
function require_role(string|array $roles): void {
    require_login();
    $user  = current_user();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($user['role_name'], $roles, true)) {
        http_response_code(403);
        flash_set('error', 'You do not have access to that page.');
        $target = match($user['role_name']) {
            'admin'     => 'admin/dashboard.php',
            'personnel' => 'personnel/dashboard.php',
            default     => 'client/dashboard.php',
        };
        redirect($target);
    }
}

/**
 * Sign a user in. Matches your existing call: login_user((int)$user['id']).
 */
function login_user(int $userId): void {
    // Prevent session fixation
    @session_regenerate_id(true);

    $_SESSION['user_id']         = $userId;
    $_SESSION['__session_start'] = time();
    $_SESSION['__last_activity'] = time();
    $_SESSION['__last_regen']    = time();

    try {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
            ->execute([$userId]);
    } catch (Exception $e) { /* column may not exist - ignore */ }
}

/**
 * Sign the current user out.
 */
function logout_user(): void {
    session_destroy_session('logout');
}