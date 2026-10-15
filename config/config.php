<?php
/**
 * RG PMS - Application configuration
 * ----------------------------------
 * Edit these values when deploying to a live web host.
 */

// --- Database credentials (change on your host) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'rgpms');
define('DB_USER', 'root'); // XAMPP default: root
define('DB_PASS', '');       // XAMPP default: empty

// --- Application paths ---
// BASE_URL: set to the folder the app is served from, e.g.
//   localhost/rg-pms  ->  '/rg-pms'
//   yourdomain.com    ->  ''
if (!defined('BASE_URL')) {
    define('BASE_URL', '/rg-pms'); // sandbox test mount
}

define('APP_NAME', 'RG Trade and Projects');
define('APP_SYSTEM_NAME', 'RG PMS');
define('APP_CURRENCY', 'R');

// Company details shown in document footers
define('COMPANY_PHONE', '079 706 1210');
define('COMPANY_EMAIL', 'goliathreba@gmail.com');
define('COMPANY_ADDRESS', 'Polokwane, Limpopo, South Africa');

// Upload settings
define('UPLOAD_PHOTOS_DIR', __DIR__ . '/../uploads/photos');
define('UPLOAD_DOCS_DIR',   __DIR__ . '/../uploads/documents');
define('MAX_UPLOAD_SIZE', 8 * 1024 * 1024); // 8 MB

// Session name
define('SESSION_NAME', 'rgpms_session');

date_default_timezone_set('Africa/Johannesburg');
