<?php
/**
 * RG PMS - Admin: Create / Edit personnel
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo     = db();
$editId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit  = $editId > 0;
$errors  = [];

// Defaults
$p = [
    'id'         => 0,
    'user_id'    => 0,
    'full_name'  => '',
    'email'      => '',
    'phone'      => '',
    'specialty'  => '',
    'role_name'  => 'personnel',
];

// ---- Detect columns so we're safe ----
$pCols = $pdo->query("SHOW COLUMNS FROM personnel")->fetchAll(PDO::FETCH_COLUMN);
$hasSpecialty = in_array('specialty', $pCols);
$uCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$hasPhone = in_array('phone', $uCols);

// ---- Load existing ----
if ($isEdit) {
    $stmt = $pdo->prepare(
        'SELECT p.*, u.full_name, u.email, u.phone, r.name AS role_name
         FROM personnel p
         LEFT JOIN users u ON u.id = p.user_id
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$editId]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('error', 'Personnel record not found.');
        redirect('admin/personnel.php');
    }
    $p = array_merge($p, $row);
}

// ---- Handle submit ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $p['full_name'] = trim($_POST['full_name'] ?? '');
    $p['email']     = trim($_POST['email'] ?? '');
    $p['phone']     = trim($_POST['phone'] ?? '');
    $p['specialty'] = trim($_POST['specialty'] ?? '');
    $p['role_name'] = trim($_POST['role_name'] ?? 'personnel');
    $newPassword    = (string)($_POST['password'] ?? '');

    if ($p['full_name'] === '') $errors[] = 'Full name is required.';
    if ($p['email'] === '')     $errors[] = 'Email is required.';
    if (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
    if (!$isEdit && strlen($newPassword) < 8) $errors[] = 'Password must be at least 8 characters.';

    // Role lookup
    $roleId = null;
    if (!$errors) {
        $rq = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $rq->execute([$p['role_name']]);
        $roleId = $rq->fetchColumn();
        if (!$roleId) $errors[] = 'Role not found.';
    }

    // Unique email check
    if (!$errors) {
        $ex = $pdo->prepare('SELECT id FROM users WHERE email = ?' . ($isEdit ? ' AND id <> ?' : ''));
        $ex->execute($isEdit ? [$p['email'], $p['user_id']] : [$p['email']]);
        if ($ex->fetchColumn()) $errors[] = 'Another user already uses that email.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($isEdit && $p['user_id']) {
                // ---- UPDATE user ----
                if ($hasPhone) {
                    $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, role_id=? WHERE id=?')
                        ->execute([$p['full_name'], $p['email'], $p['phone'] ?: null, $roleId, $p['user_id']]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name=?, email=?, role_id=? WHERE id=?')
                        ->execute([$p['full_name'], $p['email'], $roleId, $p['user_id']]);
                }

                if ($newPassword !== '') {
                    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $p['user_id']]);
                }

                // ---- UPDATE personnel ----
                if ($hasSpecialty) {
                    $pdo->prepare('UPDATE personnel SET specialty=? WHERE id=?')
                        ->execute([$p['specialty'] ?: null, $editId]);
                }

                flash_set('success', 'Team member updated.');
            } else {
                // ---- INSERT user ----
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($hasPhone) {
                    $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role_id) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$p['full_name'], $p['email'], $p['phone'] ?: null, $hash, $roleId]);
                } else {
                    $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)')
                        ->execute([$p['full_name'], $p['email'], $hash, $roleId]);
                }
                $newUserId = (int)$pdo->lastInsertId();

                // ---- INSERT personnel ----
                if ($hasSpecialty) {
                    $pdo->prepare('INSERT INTO personnel (user_id, specialty) VALUES (?, ?)')
                        ->execute([$newUserId, $p['specialty'] ?: null]);
                } else {
                    $pdo->prepare('INSERT INTO personnel (user_id) VALUES (?)')
                        ->execute([$newUserId]);
                }

                flash_set('success', 'Team member created.');
            }

            $pdo->commit();
            redirect('admin/personnel.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

page_header($isEdit ? 'Edit team member' : 'New team member', 'personnel');
page_heading(
    $isEdit ? 'Edit team member' : 'New team member',
    $isEdit ? 'Update this person\'s details' : 'Add someone to the team'
);
?>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4 p-lg-5">

        <?php if ($errors): ?>
          <div class="alert alert-danger small">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label required">Full name</label>
            <input type="text" name="full_name" class="form-control" required
                   value="<?= e($p['full_name']) ?>">
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required">Email</label>
              <input type="email" name="email" class="form-control" required
                     value="<?= e($p['email']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control"
                     value="<?= e($p['phone']) ?>">
            </div>
          </div>

          <div class="row g-3 mt-0">
            <?php if ($hasSpecialty): ?>
              <div class="col-md-6">
                <label class="form-label">Specialty</label>
                <input type="text" name="specialty" class="form-control"
                       value="<?= e($p['specialty']) ?>"
                       placeholder="e.g. Civil engineering">
              </div>
            <?php endif; ?>
            <div class="col-md-6">
              <label class="form-label required">Role</label>
              <select name="role_name" class="form-select" required>
                <option value="personnel" <?= $p['role_name'] === 'personnel' ? 'selected' : '' ?>>Personnel</option>
                <option value="admin"     <?= $p['role_name'] === 'admin'     ? 'selected' : '' ?>>Admin</option>
              </select>
            </div>
          </div>

          <div class="mb-4 mt-3">
            <label class="form-label <?= $isEdit ? '' : 'required' ?>">
              <?= $isEdit ? 'New password (leave blank to keep current)' : 'Password' ?>
            </label>
            <input type="password" name="password" class="form-control"
                   <?= $isEdit ? '' : 'required' ?> minlength="8">
            <div class="form-text">
              <?= $isEdit
                  ? 'Only fill this in if you want to change their password.'
                  : 'Minimum 8 characters.' ?>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <a href="<?= BASE_URL ?>/admin/personnel.php" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
            <button class="btn btn-brand">
              <i class="bi bi-check2-circle me-2"></i><?= $isEdit ? 'Save changes' : 'Create team member' ?>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>