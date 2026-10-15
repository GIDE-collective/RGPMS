<?php
require_once __DIR__ . '/includes/layout.php';

if (current_user()) {
    redirect(match (current_user()['role_name']) {
        'admin'     => 'admin/dashboard.php',
        'personnel' => 'personnel/dashboard.php',
        default     => 'client/dashboard.php',
    });
}

// Session-end messages (idle timeout / logout / expired)
$endReason = $_COOKIE['rgpms_session_end'] ?? '';
if ($endReason === 'idle_timeout') {
    flash_set('warning', 'You were signed out after a period of inactivity.');
} elseif ($endReason === 'absolute_timeout') {
    flash_set('warning', 'Your session has expired. Please sign in again.');
} elseif ($endReason === 'logout') {
    flash_set('success', 'You have been signed out.');
} elseif ($endReason === 'user_removed') {
    flash_set('error', 'Your account is no longer active.');
}
if ($endReason !== '') {
    setcookie('rgpms_session_end', '', time() - 3600, '/');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (isset($user['is_active']) && !$user['is_active']) {
            $error = 'This account has been deactivated. Please contact the administrator.';
        } else {
            login_user((int)$user['id']);
            flash_set('success', 'Welcome back, ' . $user['full_name'] . '!');
            redirect(match ($user['role_name']) {
                'admin'     => 'admin/dashboard.php',
                'personnel' => 'personnel/dashboard.php',
                default     => 'client/dashboard.php',
            });
        }
    } else {
        $error = 'Invalid email or password.';
    }
}

page_header('Sign in', 'login');
?>
<div class="auth-wrap">
  <div class="card auth-card">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <img src="<?= BASE_URL ?>/assets/img/logo-full.png" alt="<?= APP_NAME ?> logo" style="max-width:180px;height:auto;margin:0 auto .75rem;display:block">
        <h4 class="fw-semibold mb-0">Sign in to <?= APP_SYSTEM_NAME ?></h4>
        <p class="text-muted small mb-0"><?= APP_NAME ?></p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label required">Email address</label>
          <input type="email" name="email" class="form-control" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label required">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-brand w-100 py-2">Sign in</button>
      </form>

      <hr class="my-4">
      <p class="text-center small text-muted mb-0">
        <i class="bi bi-shield-lock me-1"></i>Client portal access is set up by <?= APP_NAME ?> - contact us at
        <a href="tel:<?= e(preg_replace('/\s+/', '', COMPANY_PHONE)) ?>"><?= e(COMPANY_PHONE) ?></a>
        or <a href="mailto:<?= e(COMPANY_EMAIL) ?>"><?= e(COMPANY_EMAIL) ?></a> for your sign-in details.
      </p>
    </div>
  </div>
</div>
<?php page_footer(); ?>