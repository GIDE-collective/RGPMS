<?php
/**
 * RG PMS - Change password: step 2 - set new password.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/password_reset.php';
require_login();

// Must have verified an OTP in the last 10 minutes
$verifiedUserId = pwd_reset_current_user();
if (!$verifiedUserId) {
    flash_set('error', 'Please verify the security code first.');
    redirect('password_change.php');
}

$pdo    = db();
$user   = current_user();
$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $newPw   = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($newPw) < 8)   $errors[] = 'New password must be at least 8 characters.';
    if ($newPw !== $confirm)  $errors[] = 'New passwords do not match.';

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPw, PASSWORD_DEFAULT), $verifiedUserId]);

        pwd_reset_clear_session();
        $done = true;
    }
}

page_header('Set new password', 'profile');
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">

    <?php if ($done): ?>
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-lg-5 text-center">
          <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success"
                  style="width:64px;height:64px;font-size:1.9rem;">
              <i class="bi bi-check2-circle"></i>
            </span>
          </div>
          <h5 class="fw-semibold mb-2">Password updated</h5>
          <p class="text-muted small mb-4">You can now sign in with your new password.</p>
          <a href="<?= BASE_URL ?>/profile.php" class="btn btn-brand">Back to profile</a>
        </div>
      </div>

    <?php else: ?>
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-lg-5">

          <div class="text-center mb-4">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success"
                  style="width:64px;height:64px;font-size:1.8rem;">
              <i class="bi bi-shield-check"></i>
            </span>
            <h5 class="fw-semibold mt-3 mb-1">Set your new password</h5>
            <p class="text-muted small mb-0">
              Security check passed. Choose a new password below.
            </p>
          </div>

          <?php if ($errors): ?>
            <div class="alert alert-danger small">
              <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label required">New password</label>
              <div class="input-group">
                <input type="password" name="new_password" id="new_password"
                       class="form-control" minlength="8" required autofocus>
                <button class="btn btn-outline-secondary" type="button"
                        onclick="togglePw('new_password', this)" tabindex="-1">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text">Minimum 8 characters.</div>
            </div>

            <div class="mb-4">
              <label class="form-label required">Confirm new password</label>
              <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password"
                       class="form-control" minlength="8" required>
                <button class="btn btn-outline-secondary" type="button"
                        onclick="togglePw('confirm_password', this)" tabindex="-1">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <button class="btn btn-brand w-100">
              <i class="bi bi-check2-circle me-2"></i>Update password
            </button>
          </form>

          <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>/profile.php" class="small text-decoration-none">
              <i class="bi bi-x me-1"></i>Cancel
            </a>
          </div>

        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
function togglePw(id, btn) {
  var input = document.getElementById(id);
  var icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

<?php page_footer(); ?>