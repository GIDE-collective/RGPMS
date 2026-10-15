<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $errors   = [];
    if ($fullName === '') $errors[] = 'Full name is required.';

    $pdo = db();
    if (!$errors) {
        $upd = $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
        $upd->execute([$fullName, $phone ?: null, $user['id']]);
        flash_set('success', 'Profile updated.');
        redirect('profile.php');
    }
}

page_header('My profile', 'profile');
page_heading('My profile', 'Your account details');
?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-person-circle me-2 text-brand"></i>Account details</h6>
        <dl class="row mb-0 small">
          <dt class="col-sm-4 text-muted">Full name</dt><dd class="col-sm-8"><?= e($user['full_name']) ?></dd>
          <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8"><?= e($user['email']) ?></dd>
          <dt class="col-sm-4 text-muted">Phone</dt><dd class="col-sm-8"><?= e($user['phone'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Role</dt><dd class="col-sm-8"><?= badge($user['role_name'], $user['role_name'] === 'admin' ? 'warning' : ($user['role_name'] === 'personnel' ? 'info' : 'success')) ?></dd>
          <dt class="col-sm-4 text-muted">Member since</dt><dd class="col-sm-8"><?= nice_date($user['created_at']) ?></dd>
        </dl>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-2"><i class="bi bi-shield-lock me-2 text-brand"></i>Password &amp; security</h6>
        <p class="small text-muted mb-3">
          To change your password, we'll email you a 6-digit PIN and a secure link.
          Your current password stays active until you complete the change.
        </p>
        <a href="<?= BASE_URL ?>/password_change.php" class="btn btn-brand">
          <i class="bi bi-envelope-at me-2"></i>Change password
        </a>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-pencil-square me-2 text-brand"></i>Edit profile</h6>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label required">Full name</label>
            <input type="text" name="full_name" class="form-control" required value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
          </div>
          <button class="btn btn-brand">Save changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>