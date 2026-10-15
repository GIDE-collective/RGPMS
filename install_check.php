<?php
/**
 * RG PMS - Installation / environment checker.
 * Open this page once after setting up the system (then delete it before going live):
 *   http://localhost/rg-pms/install_check.php
 */
require_once __DIR__ . '/config/config.php';

$checks = [];

function add_check(string $name, bool $ok, string $detail = '', string $hint = ''): void {
    global $checks;
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail, 'hint' => $hint];
}

// ---------- PHP version ----------
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
add_check(
    'PHP version',
    $phpOk,
    'Running PHP ' . PHP_VERSION . ' (7.4+ recommended, 8.x ideal)',
    $phpOk ? '' : 'XAMPP ships PHP 8.x - update XAMPP to a current version.'
);

// ---------- Required extensions ----------
foreach (['pdo_mysql' => 'PDO MySQL (database driver)', 'gd' => 'GD (image upload validation)', 'mbstring' => 'mbstring (text handling)'] as $ext => $label) {
    $loaded = extension_loaded($ext);
    add_check(
        'PHP extension: ' . $ext,
        $loaded,
        $loaded ? 'Loaded' : 'NOT loaded',
        $loaded ? '' : 'In XAMPP: open C:\xampp\php\php.ini and uncomment (remove the ; in front of) "extension=' . $ext . '". Restart Apache.'
    );
}

// ---------- Session dir ----------
$sessOk = is_writable(session_save_path() ?: sys_get_temp_dir());
add_check('PHP session storage', $sessOk, 'Session save path: ' . (session_save_path() ?: sys_get_temp_dir()), $sessOk ? '' : 'Make the session directory writable, or add session.save_path to php.ini.');

// ---------- Database connection ----------
$dbError = null;
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbOk = true;
} catch (PDOException $ex) {
    $dbOk = false;
    $dbError = $ex->getMessage();
}
add_check(
    'Database connection',
    $dbOk,
    $dbOk ? 'Connected to "' . DB_NAME . '" as "' . DB_USER . '"' : 'Connection failed: ' . $dbError,
    $dbOk ? '' : '1) Import sql/schema.sql via phpMyAdmin (it creates the "rgpms" database). 2) Check DB_USER / DB_PASS in config/config.php. On default XAMPP, user is "root" and password is empty.'
);

// ---------- Tables + seed data ----------
$tableCount = null;
if ($dbOk) {
    try {
        $tableCount = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = "' . DB_NAME . '"')->fetchColumn();
    } catch (Exception $e) { $tableCount = null; }
}
add_check(
    'Database tables',
    $tableCount !== null && $tableCount >= 15,
    $tableCount !== null ? $tableCount . ' tables found in "' . DB_NAME . '" (expected 18)' : 'Could not read table list',
    ($tableCount !== null && $tableCount >= 15) ? '' : 'Re-import sql/schema.sql (phpMyAdmin - Import - choose sql/schema.sql - Go).'
);

$userCount = null;
if ($dbOk) {
    try {
        $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    } catch (Exception $e) { $userCount = null; }
}
add_check(
    'Demo data',
    $userCount !== null && $userCount > 0,
    $userCount !== null ? $userCount . ' user accounts found (demo logins are ready)' : 'users table not readable',
    ($userCount !== null && $userCount > 0) ? '' : 'Re-import sql/schema.sql to load the demo accounts.'
);

// ---------- BASE_URL ----------
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$detectedBase = '/' . trim(dirname($script), '/');
$baseOk = (BASE_URL === $detectedBase) || (BASE_URL === '' && $detectedBase === '');
add_check(
    'BASE_URL setting',
    $baseOk,
    'Configured: "' . BASE_URL . '" · Detected from this URL: "' . $detectedBase . '"',
    $baseOk ? '' : 'Open config/config.php and set BASE_URL to "' . $detectedBase . '" (or to "" if the site is at your domain root).'
);

// ---------- Uploads ----------
$uploadsOk = true;
$uploadChecks = [];
foreach (['uploads' => __DIR__ . '/uploads', 'photos' => UPLOAD_PHOTOS_DIR, 'documents' => UPLOAD_DOCS_DIR] as $label => $dir) {
    if (!is_dir($dir)) {
        $uploadChecks[] = $label . ': folder missing (create it)';
        $uploadsOk = false;
    } elseif (!is_writable($dir)) {
        $uploadChecks[] = $label . ': NOT writable';
        $uploadsOk = false;
    }
}
add_check(
    'Upload folders',
    $uploadsOk,
    $uploadsOk ? 'uploads/, uploads/photos/, uploads/documents/ exist and are writable' : implode('; ', $uploadChecks),
    $uploadsOk ? '' : 'On Windows this normally just works. On Linux/macOS run: chmod -R 775 uploads. (In XAMPP, files are usually already writable.)'
);

// ---------- Summary ----------
$failed = count(array_filter($checks, fn($c) => !$c['ok']));
$passed = count($checks) - $failed;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RG PMS - Installation checker</title>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; color: #1f2937; margin: 0; padding: 24px 16px; }
  .wrap { max-width: 860px; margin: 0 auto; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .sub { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
  .banner { border-radius: 12px; padding: 16px 20px; margin-bottom: 18px; font-size: 14px; font-weight: 600; }
  .banner.ok { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
  .banner.bad { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
  .check { background: #fff; border-radius: 12px; padding: 14px 18px; margin-bottom: 10px; display: flex; gap: 12px; align-items: flex-start; box-shadow: 0 1px 3px rgba(16,24,40,.08); }
  .icon { font-size: 18px; line-height: 1.4; }
  .name { font-weight: 600; font-size: 14px; }
  .detail { font-size: 12.5px; color: #4b5563; margin-top: 2px; }
  .hint { font-size: 12.5px; color: #b45309; background: #fffbeb; border-left: 3px solid #f59e0b; padding: 6px 10px; border-radius: 0 8px 8px 0; margin-top: 6px; }
  .foot { font-size: 12px; color: #9ca3af; margin-top: 18px; text-align: center; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🏗️ RG PMS - Installation checker</h1>
  <div class="sub">Run this once after setting up the system. It checks your PHP, MySQL and folder configuration.
  <strong>Delete this file (install_check.php) before the site goes live.</strong></div>

  <div class="banner <?= $failed === 0 ? 'ok' : 'bad' ?>">
    <?= $failed === 0 ? '✅ All ' . $passed . ' checks passed - the system should work. Open ' . APP_NAME . ' and sign in.' : '❌ ' . $failed . ' of ' . $passed + $failed . ' checks failed - fix the items below, then refresh this page.' ?>
  </div>

  <?php foreach ($checks as $c): ?>
    <div class="check">
      <div class="icon"><?= $c['ok'] ? '✅' : '❌' ?></div>
      <div style="flex:1">
        <div class="name"><?= htmlspecialchars($c['name']) ?></div>
        <div class="detail"><?= htmlspecialchars($c['detail']) ?></div>
        <?php if (!$c['ok'] && $c['hint']): ?><div class="hint">💡 <?= htmlspecialchars($c['hint']) ?></div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="foot">RG PMS · <?= APP_NAME ?> · generated <?= date('Y-m-d H:i') ?></div>
</div>
</body>
</html>
