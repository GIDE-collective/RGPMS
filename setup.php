<?php
/**
 * RG PMS - One-time setup script.
 * Clears sample data (keeps roles + schema), inserts the 4 production users.
 * ⚠️  DELETE THIS FILE AFTER RUNNING!
 */
require_once __DIR__ . '/includes/functions.php';

$pdo = db();

// ---- Default password for all new users ----
$defaultPassword = 'RGPMS@2026';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// ---- Users to insert ----
$users = [
    ['Reboane Goliath',  'Goliathreba@gmail.com',       '0797061210', 'admin'],
    ['Thabang Setsedi',  'thabangsetsedi843@gmail.com', '0676102156', 'personnel'],
    ['Oratile Goliath',  'oratile.nt@icloud.com',       '0795372284', 'personnel'],
    ['Lethabo Morathi',  'lethabomorathi6@gmail.com',   '0722344700', 'personnel'],
];

// ---- Tables to clear (in order - children first).
// Roles and any reference/lookup tables are NOT touched.
$tablesToClear = [
    // Child records first
    'password_resets',
    'notifications',
    'gallery',
    'photos',
    'material_requests',
    'materials',
    'invoice_items',
    'invoices',
    'quotation_items',
    'quotations',
    'meetings',
    'cashflows',
    'programmes',
    'issues_escalations',
    'monthly_reports',
    'project_personnel',
    // Parent records
    'projects',
    'clients',
    'personnel',
    'users',
];

// ---- Confirm action via POST ----
$confirmed = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RG PMS - Setup</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap.min.css">
<style>
body { background:#0f172a; color:#e2e8f0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; padding:30px 20px; }
.wrap { max-width:820px; margin:0 auto; }
.card { background:#111a2e; border:1px solid rgba(255,255,255,.08); border-radius:16px; padding:26px 30px; margin-bottom:20px; }
h1 { font-size:1.5rem; margin:0 0 6px; font-weight:700; }
.lead { color:#94a3b8; margin:0 0 20px; }
.log { font-family:'SF Mono',Consolas,monospace; font-size:.85rem; line-height:1.7; background:#0a0f1e; border-radius:10px; padding:16px 18px; max-height:420px; overflow-y:auto; }
.ok   { color:#4ade80; }
.skip { color:#64748b; }
.warn { color:#fbbf24; }
.err  { color:#f87171; }
.badge-ok { background:rgba(74,222,128,.12); color:#4ade80; padding:3px 9px; border-radius:6px; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
.btn-danger { background:#dc2626; border-color:#dc2626; color:#fff; padding:10px 22px; border-radius:8px; border:none; font-weight:600; cursor:pointer; }
.btn-danger:hover { background:#b91c1c; }
.btn-secondary { background:transparent; border:1px solid rgba(255,255,255,.18); color:#cbd5e1; padding:10px 22px; border-radius:8px; font-weight:500; text-decoration:none; display:inline-block; }
.warn-box { background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.3); color:#fbbf24; padding:14px 18px; border-radius:10px; margin-bottom:20px; }
.pill { display:inline-block; background:rgba(59,130,246,.15); color:#93c5fd; padding:2px 8px; border-radius:5px; font-family:monospace; font-size:.8rem; }
</style>
</head>
<body>
<div class="wrap">
<?php if (!$confirmed): ?>

  <div class="card">
    <h1>RG PMS - Setup</h1>
    <p class="lead">This script will <strong>clear sample data</strong>, then insert four production users.</p>

    <div class="warn-box">
      <strong>⚠️ Read before continuing</strong><br>
      All rows in the tables below will be <strong>permanently deleted</strong>. The <span class="pill">roles</span>
      table and all schema structure are left untouched.
    </div>

    <h6 class="mb-2">Tables that will be cleared:</h6>
    <div class="log mb-4">
      <?php foreach ($tablesToClear as $t): ?>
        <div>• <?= htmlspecialchars($t) ?></div>
      <?php endforeach; ?>
    </div>

    <h6 class="mb-2">Users that will be inserted:</h6>
    <div class="log mb-4">
      <?php foreach ($users as $u): ?>
        <div>• <strong><?= htmlspecialchars($u[0]) ?></strong> - <?= htmlspecialchars($u[1]) ?> - <?= htmlspecialchars($u[3]) ?></div>
      <?php endforeach; ?>
      <div class="mt-2 warn">Default password for all: <strong><?= htmlspecialchars($defaultPassword) ?></strong></div>
    </div>

    <form method="post" onsubmit="return confirm('Delete all sample data and insert the 4 users? This cannot be undone.');">
      <input type="hidden" name="confirm" value="yes">
      <button type="submit" class="btn-danger">Yes, run setup</button>
      <a href="<?= BASE_URL ?>/index.php" class="btn-secondary ms-2">Cancel</a>
    </form>
  </div>

<?php else: ?>

  <div class="card">
    <h1>Setup complete</h1>
    <p class="lead">
      <span class="badge-ok">Done</span>
      &nbsp;You can now delete <span class="pill">setup.php</span> from your project folder.
    </p>

    <div class="log">
    <?php
    // ---- CLEAR TABLES ----
    echo "<div class='ok'>─── Clearing data ───</div>\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($tablesToClear as $table) {
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $pdo->exec("DELETE FROM `$table`");
            try { $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1"); } catch (Exception $e) {}
            echo "<div class='ok'>✓ Cleared <strong>" . htmlspecialchars($table) . "</strong> - {$count} row(s) removed</div>\n";
        } catch (Exception $e) {
            echo "<div class='skip'>· Skipped <strong>" . htmlspecialchars($table) . "</strong> (table not present)</div>\n";
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // ---- ROLES CHECK ----
    echo "\n<div class='ok'>─── Verifying roles ───</div>\n";
    $roles = [];
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_COLUMN);
        $nameCol = in_array('name', $cols) ? 'name' : (in_array('role_name', $cols) ? 'role_name' : null);
        if (!$nameCol) {
            echo "<div class='err'>✗ Could not find role name column in roles table</div>\n";
        } else {
            foreach ($pdo->query("SELECT id, `$nameCol` AS n FROM roles") as $r) {
                $roles[$r['n']] = (int)$r['id'];
            }
            echo "<div class='ok'>Found " . count($roles) . " role(s): " . htmlspecialchars(implode(', ', array_keys($roles))) . "</div>\n";
        }
    } catch (Exception $e) {
        echo "<div class='err'>✗ Could not read roles table: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    }

    // ---- USERS ----
    echo "\n<div class='ok'>─── Inserting users ───</div>\n";

    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $hasPhone = in_array('phone', $userCols);

    $sql = $hasPhone
        ? 'INSERT INTO users (full_name, email, phone, password_hash, role_id) VALUES (?, ?, ?, ?, ?)'
        : 'INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)';
    $ins = $pdo->prepare($sql);

    foreach ($users as $u) {
        [$fullName, $email, $phone, $roleName] = $u;
        $roleId = $roles[$roleName] ?? null;

        if (!$roleId) {
            echo "<div class='err'>✗ Role missing for " . htmlspecialchars($fullName) . " ({$roleName})</div>\n";
            continue;
        }

        // Skip if email already exists (safety)
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetchColumn()) {
            echo "<div class='warn'>! Skipped " . htmlspecialchars($email) . " (already exists)</div>\n";
            continue;
        }

        try {
            $params = $hasPhone
                ? [$fullName, $email, $phone ?: null, $hash, $roleId]
                : [$fullName, $email, $hash, $roleId];
            $ins->execute($params);
            echo "<div class='ok'>✓ Inserted <strong>" . htmlspecialchars($fullName) . "</strong> ({$roleName}) - {$email}</div>\n";
        } catch (Exception $e) {
            echo "<div class='err'>✗ Failed to insert " . htmlspecialchars($fullName) . ": " . htmlspecialchars($e->getMessage()) . "</div>\n";
        }
    }

    // ---- SUMMARY ----
    echo "\n<div class='ok'>─── Summary ───</div>\n";
    try {
        $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "<div class='ok'>Total users in system: <strong>{$totalUsers}</strong></div>\n";
    } catch (Exception $e) {}

    try {
        $totalRoles = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        echo "<div class='ok'>Total roles kept: <strong>{$totalRoles}</strong></div>\n";
    } catch (Exception $e) {}

    echo "\n<div class='warn'>Default password for all 4 users: <strong>" . htmlspecialchars($defaultPassword) . "</strong></div>\n";
    echo "<div class='warn'>Tell each person to change it after first login (Profile - Change password).</div>\n";
    echo "<div class='err'>⚠️  Delete setup.php NOW.</div>\n";
    ?>
    </div>

    <div class="mt-4">
      <a href="<?= BASE_URL ?>/login.php" class="btn-secondary">Go to sign in</a>
    </div>
  </div>

<?php endif; ?>
</div>
</body>
</html>