<?php
/**
 * RG PMS - Change password: step 1 - generate code + enter it.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/password_reset.php';
require_login();

$user   = current_user();
$pdo    = db();
$errors = [];

// Currently displayed OTP + token (held in session so a refresh doesn't lose them)
$otp   = $_SESSION['pwd_reset_shown_otp']   ?? null;
$token = $_SESSION['pwd_reset_shown_token'] ?? null;

// ---- ACTION: generate a fresh OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    csrf_check();

    // Rate limit: one request per 60s
    $recent = $pdo->prepare(
        'SELECT 1 FROM password_resets
         WHERE user_id = ? AND created_at > (NOW() - INTERVAL 60 SECOND)
         LIMIT 1'
    );
    $recent->execute([$user['id']]);

    if ($recent->fetchColumn()) {
        $errors[] = 'Please wait a minute before generating another code.';
    } else {
        $res = create_password_reset((int)$user['id']);
        if ($res) {
            $_SESSION['pwd_reset_shown_otp']   = $res['otp'];
            $_SESSION['pwd_reset_shown_token'] = $res['token'];
            redirect('password_change.php');
        } else {
            $errors[] = 'Could not generate a code. Please try again.';
        }
    }
}

// ---- ACTION: verify the entered OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    csrf_check();

    $entered = trim($_POST['otp'] ?? '');
    $postedToken = trim($_POST['token'] ?? '');

    if (!preg_match('/^\d{6}$/', $entered)) {
        $errors[] = 'Please enter the 6-digit code.';
    } else {
        $userId = verify_password_reset($postedToken, $entered, true);
        if (!$userId) {
            $errors[] = 'That code is incorrect or has expired. Try again or generate a new one.';
        } else {
            // Success - mark session as verified, clear the shown OTP, jump to password page
            pwd_reset_mark_verified($userId);
            unset($_SESSION['pwd_reset_shown_otp'], $_SESSION['pwd_reset_shown_token']);
            redirect('password_verify.php');
        }
    }

    // If we got here, verification failed - keep showing the same card
    $otp   = $_SESSION['pwd_reset_shown_otp']   ?? null;
    $token = $_SESSION['pwd_reset_shown_token'] ?? null;
}

page_header('Change password', 'profile');
page_heading('Change password', 'Step 1 - verify the security code');
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4 p-lg-5">

        <div class="text-center mb-4">
          <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-brand-soft"
                style="width:64px;height:64px;font-size:1.8rem;">
            <i class="bi bi-shield-check"></i>
          </span>
          <h5 class="fw-semibold mt-3 mb-1">Security check</h5>
          <p class="text-muted small mb-0">
            Read the code in the image, type it below, then continue.
          </p>
        </div>

        <?php if ($errors): ?>
          <div class="alert alert-danger small">
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($otp): ?>

          <!-- ===== CAPTCHA CHALLENGE + INPUT ===== -->
          <div class="captcha-card mb-4">
            <div class="captcha-header">
              <span class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-brand"></i>
                <span class="fw-semibold small">Security check</span>
              </span>
              <span class="captcha-status">
                <span class="captcha-dot"></span>
                <span class="small text-muted">Protected</span>
              </span>
            </div>

            <div class="captcha-body">
              <?= render_captcha_otp($otp) ?>
            </div>

            <div class="captcha-footer">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="humanCheck" checked disabled>
                <label class="form-check-label small" for="humanCheck">I'm not a robot</label>
              </div>
              <div class="small text-muted">
                <i class="bi bi-clock me-1"></i>Expires in 15 min
              </div>
            </div>
          </div>

          <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="mb-3">
              <label class="form-label required">Enter the code</label>
              <input type="text" name="otp" class="form-control text-center"
                     inputmode="numeric" pattern="\d{6}" maxlength="6"
                     placeholder="● ● ● ● ● ●"
                     style="letter-spacing:.6rem;font-size:1.25rem;font-weight:600;"
                     required autofocus>
            </div>

            <button class="btn btn-brand w-100">
              <i class="bi bi-check2-circle me-2"></i>Verify code
            </button>
          </form>

          <div class="text-center mt-3">
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="generate">
              <button class="btn btn-link btn-sm text-decoration-none p-0 me-3">
                <i class="bi bi-arrow-repeat me-1"></i>New code
              </button>
            </form>
            <a href="<?= BASE_URL ?>/profile.php" class="small text-decoration-none">
              <i class="bi bi-x me-1"></i>Cancel
            </a>
          </div>

        <?php else: ?>

          <div class="alert alert-light border small mb-4">
            <i class="bi bi-info-circle me-1 text-brand"></i>
            What happens next:
            <ol class="mb-0 mt-2 ps-3">
              <li>A security code will be shown to you.</li>
              <li>You type it in the box below the image.</li>
              <li>Then you set your new password.</li>
            </ol>
          </div>

          <form method="post" class="text-center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate">
            <button class="btn btn-brand btn-lg">
              <i class="bi bi-shield-check me-2"></i>Show security code
            </button>
          </form>

        <?php endif; ?>

      </div>
    </div>

  </div>
</div>

<style>
.captcha-card {
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 14px;
  background: #ffffff;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(15,23,42,.05);
}
.captcha-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: #f8fafc;
}
.captcha-status { display: inline-flex; align-items: center; gap: 6px; }
.captcha-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 3px rgba(34,197,94,.18);
}
.captcha-body {
  padding: 16px;
  display: flex;
  justify-content: center;
  background: #ffffff;
}
.captcha-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-top: 1px solid var(--border-color, #e2e8f0);
  background: #f8fafc;
}
html.theme-dark .captcha-card { background: #111a2e; border-color: rgba(255,255,255,.08); }
html.theme-dark .captcha-header,
html.theme-dark .captcha-footer { background: #0d1425; border-color: rgba(255,255,255,.08); }
html.theme-dark .captcha-body { background: #111a2e; }
html.theme-dark .form-check-label { color: #e2e8f0; }
</style>

<?php page_footer(); ?>