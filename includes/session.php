<?php
/**
 * RG PMS - Session bootstrap and timeouts.
 * Include BEFORE session_start(). Normally loaded via auth.php.
 */

const SESSION_IDLE_TIMEOUT     = 1800;   // 30 minutes of inactivity
const SESSION_ABSOLUTE_TIMEOUT = 28800;  // 8 hours total
const SESSION_REGEN_INTERVAL   = 900;    // regenerate ID every 15 minutes

if (session_status() === PHP_SESSION_NONE) {

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Lax');
    ini_set('session.cookie_secure',    $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_probability',   '1');
    ini_set('session.gc_divisor',       '100');
    ini_set('session.gc_maxlifetime',   (string)SESSION_ABSOLUTE_TIMEOUT);

    $basePath = '/';
    if (defined('BASE_URL')) {
        $p = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
        $basePath = rtrim($p, '/') . '/';
        if ($basePath === '//' || $basePath === '') $basePath = '/';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $basePath,
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function session_enforce_timeouts(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) return false;

    $now   = time();
    $idle  = $_SESSION['__last_activity'] ?? null;
    $born  = $_SESSION['__session_start'] ?? null;
    $regen = $_SESSION['__last_regen']    ?? null;

    if ($idle === null || $born === null) {
        $_SESSION['__session_start'] = $now;
        $_SESSION['__last_activity'] = $now;
        $_SESSION['__last_regen']    = $now;
        return true;
    }

    if (($now - $born) > SESSION_ABSOLUTE_TIMEOUT) {
        session_destroy_session('absolute_timeout');
        return false;
    }
    if (($now - $idle) > SESSION_IDLE_TIMEOUT) {
        session_destroy_session('idle_timeout');
        return false;
    }
    if (($now - $regen) > SESSION_REGEN_INTERVAL) {
        @session_regenerate_id(true);
        $_SESSION['__last_regen'] = $now;
    }

    $_SESSION['__last_activity'] = $now;
    return true;
}

function session_destroy_session(string $reason = ''): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
    if ($reason !== '') {
        setcookie('rgpms_session_end', $reason, time() + 60, '/', '', false, false);
    }
}