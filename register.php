<?php
/**
 * Client account registration is managed by RG Trade and Projects.
 * This page no longer allows self-registration - client portal logins are
 * created by the company and handed to the client directly.
 */
require_once __DIR__ . '/includes/layout.php';

if (current_user()) {
    redirect(match (current_user()['role_name']) {
        'admin'     => 'admin/dashboard.php',
        'personnel' => 'personnel/dashboard.php',
        default     => 'client/dashboard.php',
    });
}

page_header('Client access', 'register');
?>
<div class="auth-wrap">
  <div class="card auth-card">
    <div class="card-body p-4 p-md-5 text-center">
      <div class="auth-logo"><i class="bi bi-person-check"></i></div>
      <h4 class="fw-semibold mb-2">Client access is set up by <?= APP_NAME ?></h4>
      <p class="text-muted small mb-4">
        When a project discussion begins, our team creates your client portal account
        and hands you your sign-in credentials directly - no public registration needed.
      </p>

      <a href="<?= BASE_URL ?>/login.php" class="btn btn-brand w-100 py-2 mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in to the portal</a>

      <p class="small text-muted mb-0">
        Need access to your project?
        <a href="tel:<?= e(preg_replace('/\s+/', '', COMPANY_PHONE)) ?>"><?= e(COMPANY_PHONE) ?></a>
        · <a href="mailto:<?= e(COMPANY_EMAIL) ?>"><?= e(COMPANY_EMAIL) ?></a>
      </p>
    </div>
  </div>
</div>
<?php page_footer(); ?>
