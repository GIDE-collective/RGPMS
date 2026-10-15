<?php
/**
 * RG PMS - Top navbar + left sidebar layout (modern, with theme toggle)
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/delete.php';

function page_header(string $title, string $active = ''): void {
    $user    = current_user();
    $role    = $user['role_name'] ?? null;
    $unread  = $user ? unread_notification_count((int)$user['id']) : 0;
    $flash   = flash_get();
    $base    = BASE_URL;

    $initials = '';
    if ($user) {
        foreach (preg_split('/\s+/', trim($user['full_name'])) as $part) {
            if ($part !== '') $initials .= strtoupper($part[0]);
        }
        $initials = substr($initials, 0, 2);
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<script>
(function(){
  try {
    var t = localStorage.getItem('rgpms_theme') || 'light';
    if (t === 'dark') document.documentElement.classList.add('theme-dark');
  } catch(e){}
})();
</script>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/img/logo-full.png">
<link rel="stylesheet" href="<?= $base ?>/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/vendor/bootstrap-icons.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<style>
/* ============ CRITICAL LAYOUT (forced) ============ */
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }

body {
  background: #f8fafc !important;
  color: #0f172a !important;
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif !important;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-navbar {
  position: sticky !important;
  top: 0 !important;
  z-index: 1040 !important;
  background: #0f172a !important;
  color: #fff !important;
  border-bottom: 1px solid rgba(255,255,255,.06) !important;
  height: 64px !important;
  min-height: 64px !important;
  display: flex !important;
  align-items: center !important;
}
.navbar-inner {
  width: 100%;
  padding: 0 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.app-navbar .navbar-brand { display: flex !important; align-items: center !important; text-decoration: none !important; padding: 0 !important; }
.header-logo {
  height: 40px !important; width: auto !important; max-width: 200px !important;
  object-fit: contain !important; display: block !important;
  border-radius: 10px !important; background: rgba(255,255,255,.05) !important; padding: 3px !important;
}
.header-fallback-mark {
  display: inline-flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: #fff; font-size: 1.05rem; font-weight: 700;
}
.navbar-divider { width: 1px; height: 28px; background: rgba(255,255,255,.12); flex-shrink: 0; }
.navbar-page-title { color: #e2e8f0 !important; font-size: .9rem !important; font-weight: 600 !important; letter-spacing: .2px; white-space: nowrap; }
.navbar-page-sub { color: #94a3b8 !important; font-size: .72rem !important; font-weight: 400 !important; }
.btn-hamburger {
  background: transparent !important; border: none !important; color: #fff !important;
  font-size: 1.4rem !important; padding: 4px 8px !important; border-radius: 8px !important; cursor: pointer !important;
}
.btn-hamburger:hover { background: rgba(255,255,255,.08) !important; }

.nav-icon-link {
  display: inline-flex !important; align-items: center !important; justify-content: center !important;
  padding: .45rem .7rem !important; border-radius: 8px !important;
  color: #cbd5e1 !important; text-decoration: none !important;
  transition: background .15s ease, color .15s ease;
  border: none !important; background: transparent !important;
  position: relative !important; cursor: pointer !important;
}
.nav-icon-link:hover { background: rgba(255,255,255,.08) !important; color: #fff !important; }
.nav-icon-link .badge-dot {
  position: absolute !important; top: 2px !important; right: 2px !important;
  background: #ef4444 !important; color: #fff !important;
  font-size: .62rem !important; font-weight: 700 !important;
  padding: 2px 5px !important; border-radius: 999px !important;
  line-height: 1 !important; border: 2px solid #0f172a !important;
}
#themeToggle .icon-dark { display: none !important; }
html.theme-dark #themeToggle .icon-light { display: none !important; }
html.theme-dark #themeToggle .icon-dark  { display: inline-block !important; }

.app-layout {
  display: flex !important; flex-direction: row !important;
  width: 100% !important; align-items: stretch !important; flex: 1 1 auto !important;
}
.app-sidebar {
  flex: 0 0 260px !important; width: 260px !important; min-width: 260px !important; max-width: 260px !important;
  background: #0f172a !important; color: #cbd5e1 !important;
  display: flex !important; flex-direction: column !important;
  border-right: 1px solid rgba(255,255,255,.06) !important;
  position: sticky !important; top: 64px !important;
  height: calc(100vh - 64px) !important;
  overflow: visible !important; z-index: 1032 !important;
}
.sidebar-scroll {
  flex: 1 1 auto !important; overflow-y: auto !important; overflow-x: hidden !important;
  padding: 8px 0 !important; min-height: 0 !important;
}
.sidebar-scroll::-webkit-scrollbar { width: 6px; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 3px; }
.sidebar-user-wrap {
  flex-shrink: 0 !important; padding: 10px 12px 14px !important;
  border-top: 1px solid rgba(255,255,255,.06) !important;
}
.app-main {
  flex: 1 1 auto !important; min-width: 0 !important;
  display: flex !important; flex-direction: column !important;
  position: relative !important; z-index: 1 !important;
}
.sidebar-section-title {
  font-size: .68rem !important; text-transform: uppercase !important;
  letter-spacing: .8px !important; color: #64748b !important;
  padding: 10px 20px 6px !important; font-weight: 600 !important;
}
.sidebar-nav .nav-link {
  display: flex !important; align-items: center !important; gap: 12px !important;
  color: #cbd5e1 !important; font-size: .875rem !important; font-weight: 500 !important;
  padding: .55rem 20px !important; position: relative !important;
  text-decoration: none !important; background: transparent !important;
  border: none !important; border-radius: 0 !important; cursor: pointer !important;
}
.sidebar-nav .nav-link i { font-size: 1.05rem !important; width: 20px !important; text-align: center !important; color: #64748b !important; transition: color .15s ease !important; }
.sidebar-nav .nav-link:hover { background: rgba(255,255,255,.06) !important; color: #fff !important; }
.sidebar-nav .nav-link:hover i { color: #fff !important; }
.sidebar-nav .nav-link.active { background: rgba(59,130,246,.15) !important; color: #fff !important; font-weight: 600 !important; }
.sidebar-nav .nav-link.active::before {
  content: "" !important; position: absolute !important;
  left: 0 !important; top: 6px !important; bottom: 6px !important;
  width: 3px !important; background: #3b82f6 !important; border-radius: 0 4px 4px 0 !important;
}
.sidebar-nav .nav-link.active i { color: #93c5fd !important; }

.app-sidebar .dropdown-menu {
  background: #ffffff !important; border: 1px solid #e2e8f0 !important;
  border-radius: 12px !important; box-shadow: 0 20px 40px rgba(15,23,42,.28) !important;
  padding: 6px !important; min-width: 230px !important; margin-left: 8px !important; z-index: 2000 !important;
}
.app-sidebar .dropdown-item { color: #0f172a !important; border-radius: 6px !important; font-size: .85rem !important; padding: 8px 10px !important; }
.app-sidebar .dropdown-item:hover { background: #f1f5f9 !important; }
.app-sidebar .dropdown-divider { border-color: #f1f5f9 !important; margin: 4px 0 !important; }

.sidebar-user {
  display: flex !important; align-items: center !important; gap: 10px !important;
  padding: 8px 10px !important; border-radius: 12px !important;
  text-decoration: none !important; color: #fff !important; cursor: pointer !important;
}
.sidebar-user:hover { background: rgba(255,255,255,.06) !important; color: #fff !important; }
.sidebar-user .avatar {
  flex-shrink: 0 !important; width: 36px !important; height: 36px !important;
  border-radius: 50% !important; background: linear-gradient(135deg, #3b82f6, #1e40af) !important;
  color: #fff !important; display: inline-flex !important;
  align-items: center !important; justify-content: center !important;
  font-size: .8rem !important; font-weight: 700 !important; letter-spacing: .5px !important;
  box-shadow: 0 2px 8px rgba(59,130,246,.35) !important;
}
.sidebar-user .user-info { min-width: 0 !important; flex: 1 1 auto !important; line-height: 1.2 !important; }
.sidebar-user .user-name { font-size: .82rem !important; font-weight: 600 !important; color: #fff !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
.sidebar-user .user-role { font-size: .68rem !important; color: #94a3b8 !important; text-transform: uppercase !important; letter-spacing: .4px !important; margin-top: 2px !important; }

.sidebar-backdrop { display: none !important; position: fixed !important; inset: 0 !important; background: rgba(15,23,42,.5) !important; z-index: 1035 !important; }

@media (max-width: 991.98px) {
  .app-sidebar {
    position: fixed !important; top: 64px !important; bottom: 0 !important; left: 0 !important;
    height: auto !important; z-index: 1036 !important;
    transform: translateX(-100%) !important; transition: transform .25s ease !important;
    box-shadow: 4px 0 24px rgba(0,0,0,.3) !important;
  }
  body.sidebar-open .app-sidebar { transform: translateX(0) !important; }
  body.sidebar-open .sidebar-backdrop { display: block !important; }
}

.fact-toast {
  position: fixed !important; bottom: 24px !important; right: 24px !important;
  max-width: 380px !important; background: #ffffff !important;
  border: 1px solid #e2e8f0 !important; border-left: 4px solid #3b82f6 !important;
  border-radius: 14px !important; box-shadow: 0 20px 40px rgba(15,23,42,.22) !important;
  padding: 16px 18px !important;
  transform: translateY(140%) !important; opacity: 0 !important;
  transition: transform .35s cubic-bezier(.16,1,.3,1), opacity .3s ease !important;
  z-index: 2000 !important; pointer-events: none !important;
  display: flex !important; gap: 12px !important; align-items: flex-start !important;
}
.fact-toast.show { transform: translateY(0) !important; opacity: 1 !important; pointer-events: auto !important; }
.fact-toast-icon {
  flex-shrink: 0 !important; width: 36px !important; height: 36px !important;
  border-radius: 10px !important; background: rgba(59,130,246,.12) !important;
  color: #3b82f6 !important; display: inline-flex !important;
  align-items: center !important; justify-content: center !important; font-size: 1.1rem !important;
}
.fact-toast-title { font-size: .72rem !important; text-transform: uppercase !important; letter-spacing: .8px !important; color: #3b82f6 !important; font-weight: 700 !important; margin-bottom: 4px !important; }
.fact-toast-text { font-size: .875rem !important; line-height: 1.45 !important; color: #0f172a !important; }

.text-brand        { color: #3b82f6 !important; }
.text-brand-light  { color: #93c5fd !important; }
.text-brand-dark   { color: #1e40af !important; }
.bg-brand-soft     { background: rgba(59,130,246,.12) !important; color: #3b82f6 !important; }

.app-footer {
  background: #0f172a !important; color: #b0b8d0 !important;
  font-size: .82rem;
  border-top: 1px solid rgba(255,255,255,.06) !important;
}
.app-footer a { color: #cbd5e1 !important; text-decoration: none; transition: color .15s ease; }
.app-footer a:hover { color: #ffffff !important; }
.footer-links { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; }
.footer-links a { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
.footer-divider { width: 1px; height: 16px; background: rgba(255,255,255,.12); }
.footer-meta { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; color: #94a3b8; }

html.theme-animating,
html.theme-animating body,
html.theme-animating body * {
  transition: background-color .35s ease, color .35s ease, border-color .35s ease !important;
}

/* ============ DARK THEME ============ */
html.theme-dark body { background: #0a0f1e !important; color: #e2e8f0 !important; }
html.theme-dark .app-main, html.theme-dark .public-main,
html.theme-dark .container, html.theme-dark .container-fluid,
html.theme-dark .container-lg, html.theme-dark .container-md,
html.theme-dark .container-sm, html.theme-dark .container-xl,
html.theme-dark .container-xxl, html.theme-dark .row,
html.theme-dark [class*="col-"] { background: transparent !important; }
html.theme-dark .card, html.theme-dark .card-body,
html.theme-dark .stat-tile, html.theme-dark .project-card,
html.theme-dark .service-tile, html.theme-dark .step-card,
html.theme-dark .portal-card, html.theme-dark .auth-card,
html.theme-dark .list-group-item {
  background: #111a2e !important; border-color: rgba(255,255,255,.08) !important; color: #e2e8f0 !important;
}
html.theme-dark .card-header { background: #111a2e !important; border-bottom-color: rgba(255,255,255,.06) !important; color: #e2e8f0 !important; }
html.theme-dark .card-footer { background: #0d1425 !important; border-top-color: rgba(255,255,255,.06) !important; color: #94a3b8 !important; }
html.theme-dark .form-control, html.theme-dark .form-select, html.theme-dark .input-group-text {
  background: #0a0f1e !important; border-color: rgba(255,255,255,.08) !important; color: #e2e8f0 !important;
}
html.theme-dark .form-control::placeholder { color: #64748b !important; }
html.theme-dark .form-label, html.theme-dark .form-check-label { color: #e2e8f0 !important; }
html.theme-dark .table, html.theme-dark table,
html.theme-dark .table > :not(caption) > * > *,
html.theme-dark .table-light, html.theme-dark .table-white,
html.theme-dark .table-striped, html.theme-dark .table-hover,
html.theme-dark .table-bordered, html.theme-dark .table-responsive {
  --bs-table-bg: #111a2e !important; --bs-table-striped-bg: rgba(255,255,255,.03) !important;
  --bs-table-hover-bg: rgba(255,255,255,.06) !important; --bs-table-color: #e2e8f0 !important;
  --bs-table-striped-color: #e2e8f0 !important; --bs-table-hover-color: #e2e8f0 !important;
  --bs-table-border-color: rgba(255,255,255,.08) !important;
  background-color: #111a2e !important; color: #e2e8f0 !important; border-color: rgba(255,255,255,.08) !important;
}
html.theme-dark .table thead th { background: #0d1425 !important; color: #94a3b8 !important; border-color: rgba(255,255,255,.08) !important; }
html.theme-dark .table td, html.theme-dark .table th { background: transparent !important; color: #e2e8f0 !important; border-color: rgba(255,255,255,.08) !important; }
html.theme-dark .table-striped tbody tr:nth-of-type(odd) > * { background-color: rgba(255,255,255,.03) !important; color: #e2e8f0 !important; }
html.theme-dark .table-hover tbody tr:hover > * { background-color: rgba(255,255,255,.06) !important; color: #e2e8f0 !important; }
html.theme-dark .nav-tabs { border-bottom-color: rgba(255,255,255,.08) !important; }
html.theme-dark .nav-tabs .nav-link { background: transparent !important; border-color: transparent !important; color: #94a3b8 !important; }
html.theme-dark .nav-tabs .nav-link:hover { color: #e2e8f0 !important; border-color: rgba(255,255,255,.08) rgba(255,255,255,.08) transparent !important; }
html.theme-dark .nav-tabs .nav-link.active { background: #111a2e !important; border-color: rgba(255,255,255,.08) rgba(255,255,255,.08) #111a2e !important; color: #ffffff !important; }
html.theme-dark .nav-pills .nav-link { color: #94a3b8 !important; }
html.theme-dark .nav-pills .nav-link:hover { color: #e2e8f0 !important; background: rgba(255,255,255,.06) !important; }
html.theme-dark .nav-pills .nav-link.active { background: #3b82f6 !important; color: #fff !important; }
html.theme-dark .text-muted { color: #94a3b8 !important; }
html.theme-dark .text-brand { color: #93c5fd !important; }
html.theme-dark .text-brand-light { color: #93c5fd !important; }
html.theme-dark .text-brand-dark { color: #60a5fa !important; }
html.theme-dark .dropdown-menu { background: #111a2e !important; border-color: rgba(255,255,255,.08) !important; color: #e2e8f0 !important; }
html.theme-dark .dropdown-item { color: #e2e8f0 !important; }
html.theme-dark .dropdown-item:hover { background: rgba(255,255,255,.06) !important; }
html.theme-dark .dropdown-divider { border-color: rgba(255,255,255,.06) !important; }
html.theme-dark .dropdown-header { color: #94a3b8 !important; }
html.theme-dark .alert { background: #111a2e !important; border-color: rgba(255,255,255,.08) !important; color: #e2e8f0 !important; }
html.theme-dark .progress { background: rgba(255,255,255,.06) !important; }
html.theme-dark .border, html.theme-dark .border-top, html.theme-dark .border-bottom,
html.theme-dark .border-start, html.theme-dark .border-end, html.theme-dark hr { border-color: rgba(255,255,255,.08) !important; }
html.theme-dark .bg-white, html.theme-dark .bg-light, html.theme-dark .bg-body { background: #0a0f1e !important; color: #e2e8f0 !important; }
html.theme-dark .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
html.theme-dark .btn-light { background: #111a2e !important; color: #e2e8f0 !important; border-color: rgba(255,255,255,.08) !important; }
html.theme-dark .btn-outline-secondary { color: #cbd5e1 !important; border-color: rgba(255,255,255,.18) !important; }
html.theme-dark .btn-outline-secondary:hover { background: rgba(255,255,255,.08) !important; color: #fff !important; }
html.theme-dark .btn-outline-light { color: #cbd5e1 !important; border-color: rgba(255,255,255,.18) !important; }
html.theme-dark .btn-outline-light:hover { background: rgba(255,255,255,.08) !important; color: #fff !important; }
html.theme-dark .fact-toast { background: #111a2e !important; border-color: rgba(255,255,255,.08) !important; border-left-color: #3b82f6 !important; }
html.theme-dark .fact-toast-text { color: #e2e8f0 !important; }
html.theme-dark .project-card .card-title a { color: #e2e8f0 !important; }
html.theme-dark .stat-tile .value { color: #e2e8f0 !important; }
html.theme-dark .navbar-page-title { color: #e2e8f0 !important; }
html.theme-dark .app-sidebar .dropdown-menu { background: #111a2e !important; border-color: rgba(255,255,255,.08) !important; }
html.theme-dark .app-sidebar .dropdown-item { color: #e2e8f0 !important; }
html.theme-dark .app-sidebar .dropdown-item:hover { background: rgba(255,255,255,.06) !important; }
html.theme-dark .app-sidebar .dropdown-divider { border-color: rgba(255,255,255,.06) !important; }
html.theme-dark .landing-hero { color: #fff !important; }
html.theme-dark .landing-hero .text-white-50 { color: rgba(255,255,255,.6) !important; }
</style>
</head>
<body>

<?php if ($user): ?>

<nav class="app-navbar">
  <div class="navbar-inner">

    <div class="d-flex align-items-center gap-3">
      <button class="btn-hamburger d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
      </button>
      <a class="navbar-brand" href="<?= $base ?>/index.php">
        <img src="<?= $base ?>/assets/img/logo-full.png" alt="<?= APP_NAME ?>" class="header-logo"
             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
        <span class="header-fallback-mark" style="display:none;"><?= substr(APP_NAME, 0, 2) ?></span>
      </a>
      <span class="navbar-divider d-none d-md-block"></span>
      <div class="d-none d-md-block lh-1">
        <div class="navbar-page-title"><?= e($title) ?></div>
        <div class="navbar-page-sub mt-1"><?= APP_SYSTEM_NAME ?></div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-1">

      <div class="dropdown">
        <a class="nav-icon-link" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
          <i class="bi bi-bell fs-5"></i>
          <?php if ($unread > 0): ?>
            <span class="badge-dot"><?= $unread > 9 ? '9+' : $unread ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow-lg notif-menu">
          <h6 class="dropdown-header d-flex justify-content-between align-items-center">
            <span>Notifications</span>
            <a class="small text-decoration-none" href="<?= $base ?>/notifications.php">View all</a>
          </h6>
          <?php
            $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
            $stmt->execute([$user['id']]);
            $notifs = $stmt->fetchAll();
            if (!$notifs): ?>
              <div class="text-center py-3">
                <i class="bi bi-bell-slash text-muted fs-4"></i>
                <div class="small text-muted mt-2">No notifications yet</div>
              </div>
            <?php else: foreach ($notifs as $n): ?>
              <a class="dropdown-item <?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>" href="<?= $base ?>/<?= e($n['link'] ?? '') ?>">
                <span class="small d-block"><?= e($n['title']) ?></span>
                <span class="small d-block opacity-75"><?= time_ago($n['created_at']) ?></span>
              </a>
            <?php endforeach; endif; ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item small text-center" href="<?= $base ?>/notifications.php">View all notifications</a>
        </div>
      </div>

      <div class="dropdown">
        <a class="nav-icon-link" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="Translate">
          <i class="bi bi-translate fs-5"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg text-center" style="min-width: 230px;" data-bs-auto-close="outside">
          <li><h6 class="dropdown-header"><i class="bi bi-translate me-2"></i>Translate</h6></li>
          <li class="py-3 d-flex justify-content-center">
            <div id="google_translate_element" class="translate-widget"></div>
          </li>
        </ul>
      </div>

      <button class="nav-icon-link" type="button" id="themeToggle" title="Switch theme" aria-label="Switch theme">
        <i class="bi bi-moon-stars fs-5 icon-light"></i>
        <i class="bi bi-sun fs-5 icon-dark"></i>
      </button>

    </div>

  </div>
</nav>

<div class="app-layout">

  <aside class="app-sidebar" id="appSidebar">

    <div class="sidebar-scroll">
      <div class="sidebar-section-title">Navigation</div>
      <ul class="nav flex-column sidebar-nav">
        <?php if ($role === 'admin'): ?>
          <li class="nav-item"><a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= $base ?>/admin/dashboard.php"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'projects' ? 'active' : '' ?>" href="<?= $base ?>/admin/projects.php"><i class="bi bi-kanban"></i><span>Projects</span></a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'clients' ? 'active' : '' ?>" href="<?= $base ?>/admin/clients.php"><i class="bi bi-people"></i><span>Clients</span></a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'quotations' ? 'active' : '' ?>" href="<?= $base ?>/admin/quotations.php"><i class="bi bi-file-earmark-text"></i><span>Quotations</span></a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'personnel' ? 'active' : '' ?>" href="<?= $base ?>/admin/personnel.php"><i class="bi bi-person-badge"></i><span>Personnel</span></a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'materials' ? 'active' : '' ?>" href="<?= $base ?>/admin/materials.php"><i class="bi bi-box-seam"></i><span>Materials</span></a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'invoices' ? 'active' : '' ?>" href="<?= $base ?>/admin/invoices.php"><i class="bi bi-receipt"></i><span>Invoices</span></a></li>
        <?php elseif ($role === 'personnel'): ?>
          <li class="nav-item"><a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= $base ?>/personnel/dashboard.php"><i class="bi bi-grid-1x2"></i><span>My Projects</span></a></li>
        <?php elseif ($role === 'client'): ?>
          <li class="nav-item"><a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= $base ?>/client/dashboard.php"><i class="bi bi-grid-1x2"></i><span>My Projects</span></a></li>
        <?php endif; ?>
      </ul>

      <?php if ($role === 'admin'): ?>
        <div class="sidebar-section-title mt-3">Company</div>
        <ul class="nav flex-column sidebar-nav">
          <li class="nav-item">
            <a class="nav-link <?= $active === 'home' ? 'active' : '' ?>" href="<?= $base ?>/about.php">
              <i class="bi bi-building"></i><span>About &amp; Gallery</span>
            </a>
          </li>
        </ul>
      <?php endif; ?>
    </div>

    <div class="sidebar-user-wrap">
      <div class="dropdown dropend">
        <a class="sidebar-user" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="avatar"><?= e($initials ?: 'U') ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e($user['role_name']) ?></div>
          </div>
          <i class="bi bi-three-dots-vertical ms-auto small"></i>
        </a>
        <ul class="dropdown-menu shadow-lg" style="min-width: 240px;">
          <li class="px-3 py-2">
            <div class="small text-muted">Signed in as</div>
            <div class="fw-semibold small"><?= e($user['full_name']) ?></div>
            <div class="small text-muted"><?= e($user['role_name']) ?></div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= $base ?>/profile.php"><i class="bi bi-person-gear me-2"></i>My profile</a></li>
          <li><a class="dropdown-item" href="<?= $base ?>/user_guide.php"><i class="bi bi-book me-2"></i>User guide</a></li>
          <li><a class="dropdown-item" href="<?= $base ?>/notifications.php"><i class="bi bi-bell me-2"></i>All notifications</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= $base ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
        </ul>
      </div>
    </div>

  </aside>

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <main class="app-main">
    <div class="container-fluid px-3 px-lg-4 py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

<?php else: ?>

<nav class="app-navbar">
  <div class="navbar-inner">
    <a class="navbar-brand" href="<?= $base ?>/index.php">
      <img src="<?= $base ?>/assets/img/logo-full.png" alt="<?= APP_NAME ?>" class="header-logo"
           onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
      <span class="header-fallback-mark" style="display:none;"><?= substr(APP_NAME, 0, 2) ?></span>
    </a>
    <div class="d-flex align-items-center gap-2">
      <a class="nav-icon-link small" href="<?= $base ?>/about.php">About</a>
      <a class="nav-icon-link small" href="<?= $base ?>/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</a>
    </div>
  </div>
</nav>

<main class="flex-grow-1 py-4">
  <div class="container-fluid px-3 px-lg-4">
    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

<?php endif; ?>
<?php
}

function page_footer(): void {
    $user = current_user();
    ?>
    <?php if ($user): ?>
        </div>
      </main>
    </div><!-- /.app-layout -->
    <?php else: ?>
        </div>
    </main>
    <?php endif; ?>

<div class="fact-toast" id="factToast" role="status" aria-live="polite">
  <div class="fact-toast-icon"><i class="bi bi-lightbulb-fill"></i></div>
  <div>
    <div class="fact-toast-title">Did you know?</div>
    <div class="fact-toast-text" id="factToastText"></div>
  </div>
</div>

<footer class="app-footer py-3">
  <div class="container-fluid px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

      <div class="text-muted small">
        © <?= date('Y') ?> <?= APP_NAME ?> · <?= APP_SYSTEM_NAME ?> - Construction Project Management System
      </div>

      <div class="footer-links">
        <a href="<?= BASE_URL ?>/user_guide.php">
          <i class="bi bi-book"></i> User guide
        </a>
        <span class="footer-divider d-none d-sm-block"></span>
        <a href="mailto:<?= e(COMPANY_EMAIL) ?>?subject=RG%20PMS%20Support%20Request">
          <i class="bi bi-envelope-at"></i> Contact RG team
        </a>
        <span class="footer-divider d-none d-md-block"></span>
        <span class="footer-meta d-none d-md-flex">
          <span><i class="bi bi-telephone me-1"></i><?= e(COMPANY_PHONE) ?></span>
          <span><i class="bi bi-envelope me-1"></i><?= e(COMPANY_EMAIL) ?></span>
        </span>
      </div>

    </div>
  </div>
</footer>

<script src="<?= BASE_URL ?>/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</body>
</html>
<?php
}

function page_heading(string $title, string $subtitle, string $actions = ''): void {
    echo '<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">';
    echo '<div><h4 class="mb-0 fw-semibold">' . e($title) . '</h4>';
    if ($subtitle) echo '<div class="text-muted small mt-1">' . e($subtitle) . '</div>';
    echo '</div>';
    if ($actions) echo '<div class="d-flex gap-2">' . $actions . '</div>';
    echo '</div>';
}