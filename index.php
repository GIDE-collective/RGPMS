<?php
/**
 * RG Trade and Projects - public landing page
 */
require_once __DIR__ . '/includes/layout.php';
$user = current_user();
page_header('Welcome', 'home');

$dashboardFor = $user ? match ($user['role_name']) {
    'admin'     => 'admin/dashboard.php',
    'personnel' => 'personnel/dashboard.php',
    default     => 'client/dashboard.php',
} : null;

// Preview a few gallery items for the landing page
$previewGallery = [];
try {
    $previewGallery = db()->query(
        'SELECT filename, title FROM gallery ORDER BY created_at DESC LIMIT 4'
    )->fetchAll();
} catch (Exception $e) {
    // gallery table may not exist yet
}
?>

<!-- ================= HERO ================= -->
<section class="landing-hero py-5 mb-5">
  <div class="hero-inner container py-4 py-lg-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="hero-badge anim anim-d2 mb-3">Construction · Civil Engineering · Consulting · Supply</span>
        <div class="anim anim-d2 d-inline-block bg-white rounded-4 p-3 mb-4 shadow-lg">
          <img src="<?= BASE_URL ?>/assets/img/logo-full.png" alt="<?= APP_NAME ?> logo" class="hero-logo">
        </div>
        <h1 class="anim anim-d3 display-5 fw-bold mb-3">Building with <span class="text-brand-light">quality, transparency</span><br>and on-time delivery.</h1>
        <p class="anim anim-d4 lead mb-4 text-white-50" style="max-width:600px">
          RG Trade and Projects is a small, focused construction and civil engineering company.
          We plan, construct and deliver - and keep every client informed at each step through the
          <strong>RG Project Management System (RG PMS)</strong>.
        </p>
        <div class="anim anim-d5 d-flex flex-wrap gap-2 mb-4">
          <?php if ($dashboardFor): ?>
            <a href="<?= BASE_URL ?>/<?= $dashboardFor ?>" class="btn btn-brand btn-lg"><i class="bi bi-speedometer2 me-2"></i>Go to my dashboard</a>
            <a href="<?= BASE_URL ?>/notifications.php" class="btn btn-outline-light btn-lg"><i class="bi bi-bell me-2"></i>Notifications</a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-brand btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</a>
            <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline-light btn-lg"><i class="bi bi-building me-2"></i>About us</a>
          <?php endif; ?>
        </div>
        <div class="anim anim-d6 d-flex flex-wrap gap-2">
          <span class="hero-chip"><i class="bi bi-bricks text-brand-light"></i>Construction</span>
          <span class="hero-chip"><i class="bi bi-signpost-2 text-brand-light"></i>Civil engineering</span>
          <span class="hero-chip"><i class="bi bi-clipboard-check text-brand-light"></i>Consulting</span>
          <span class="hero-chip"><i class="bi bi-box-seam text-brand-light"></i>Materials supply</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= STATS ================= -->
<section class="stat-band mb-5">
  <div class="container">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="row g-3">
          <div class="col-6 col-lg-3 stat"><div class="n">4</div><div class="l">Team members</div></div>
          <div class="col-6 col-lg-3 stat"><div class="n">4</div><div class="l">Services offered</div></div>
          <div class="col-6 col-lg-3 stat"><div class="n">1</div><div class="l">System for everything</div></div>
          <div class="col-6 col-lg-3 stat"><div class="n">24/7</div><div class="l">Client portal access</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= SERVICES ================= -->
<section class="mb-5">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="landing-section-title">What we do</h2>
      <p class="landing-section-sub mx-auto">From the first foundation to the final coat of paint - one team, four core services.</p>
    </div>
    <div class="row g-4">
      <?php
      $services = [
        ['icon' => 'bi-bricks',         'title' => 'Construction',      'text' => 'Residential, commercial and industrial construction delivered by an experienced small team with strict quality control on every stage.'],
        ['icon' => 'bi-signpost-2',     'title' => 'Civil Engineering', 'text' => 'Earthworks, concrete works, site preparation and civil infrastructure for developments of all sizes.'],
        ['icon' => 'bi-clipboard-check','title' => 'Consulting',        'text' => 'Professional advice on project planning, materials selection and construction methods - before you commit.'],
        ['icon' => 'bi-box-seam',       'title' => 'Materials Supply',  'text' => 'Sourcing and supply of construction materials and solutions, delivered to site on time.'],
      ];
      foreach ($services as $s): ?>
        <div class="col-md-6 col-lg-3">
          <div class="card service-tile shadow-sm">
            <div class="card-body">
              <div class="icon mb-2"><i class="bi <?= $s['icon'] ?>"></i></div>
              <h6 class="fw-semibold"><?= $s['title'] ?></h6>
              <p class="text-muted small mb-0"><?= $s['text'] ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= GALLERY PREVIEW (advertisement) ================= -->
<?php if ($previewGallery): ?>
<section class="mb-5">
  <div class="container">
    <div class="text-center mb-4">
      <span class="hero-badge" style="background:rgba(59,130,246,.15);color:#3b82f6;border:1px solid rgba(59,130,246,.35);display:inline-block;padding:.35rem .9rem;border-radius:999px;font-size:.75rem;font-weight:600;letter-spacing:.5px;text-transform:uppercase;">From the site</span>
      <h2 class="landing-section-title mt-3">See our work in action</h2>
      <p class="landing-section-sub mx-auto">A glimpse of what we build - visit the gallery to see more.</p>
    </div>
    <div class="row g-3 mb-4">
      <?php foreach ($previewGallery as $g):
          $src = BASE_URL . '/uploads/gallery/' . rawurlencode($g['filename']);
      ?>
        <div class="col-6 col-md-3">
          <a href="<?= BASE_URL ?>/about.php#gallery" class="d-block gallery-preview">
            <img src="<?= $src ?>" alt="<?= e($g['title']) ?>" loading="lazy">
            <div class="gallery-preview-title"><?= e($g['title']) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center">
      <a href="<?= BASE_URL ?>/about.php#gallery" class="btn btn-brand">
        <i class="bi bi-images me-2"></i>View full gallery
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= LIFECYCLE ================= -->
<section class="mb-5">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="landing-section-title">How a project runs with RG PMS</h2>
      <p class="landing-section-sub mx-auto">Every project follows a clear, transparent lifecycle - tracked in one system from enquiry to completion.</p>
    </div>
    <div class="row g-3">
      <?php
      $steps = [
        ['1', 'Enquiry &amp; capture', 'Client information and project details are captured in the system.'],
        ['2', 'Quotation &amp; decision', 'A priced quotation is sent. The client approves it - or requests re-evaluation and a negotiation meeting.'],
        ['3', 'Team assigned', 'Internal employees and subcontractors are assigned to the project with clear roles.'],
        ['4', 'Site work &amp; updates', 'Personnel submit site reports, material requests, progress percentages and photographs.'],
        ['5', 'Manager reviews', 'Material requests and photographs are reviewed and approved before reaching the client.'],
        ['6', 'Completion &amp; summary', 'The project closes with an automatically generated final summary of the full lifecycle.'],
      ];
      foreach ($steps as [$num, $title, $text]): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card step-card">
            <div class="card-body">
              <span class="step-num mb-2"><?= $num ?></span>
              <h6 class="fw-semibold mt-2 mb-1"><?= $title ?></h6>
              <p class="small text-muted mb-0"><?= $text ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= COMPANY / PROCESS ================= -->
<section class="mb-5">
  <div class="container">
    <div class="row g-4 align-items-stretch">
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4 p-lg-5">
            <span class="badge text-bg-primary mb-2">Established May 2025</span>
            <h2 class="landing-section-title">Who we are</h2>
            <p class="text-muted">RG Trade and Projects provides construction services, civil engineering and construction material supply. The company operates with a four-person core team: two Project Managers (one specialising in civil engineering), a Quantity Surveyor and an Administrator.</p>
            <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline-brand btn-sm">Learn more about RG Trade and Projects</a>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4 p-lg-5">
            <h2 class="landing-section-title">How we keep clients informed</h2>
            <div class="row g-3">
              <div class="col-md-6"><div class="small"><strong><i class="bi bi-people text-brand me-1"></i>Progress meetings</strong><br><span class="text-muted">The project team and client review progress, risks and decisions together.</span></div></div>
              <div class="col-md-6"><div class="small"><strong><i class="bi bi-file-earmark-bar-graph text-brand me-1"></i>Monthly reporting</strong><br><span class="text-muted">Programme and financial reports provide a monthly view of project performance.</span></div></div>
              <div class="col-md-6"><div class="small"><strong><i class="bi bi-wallet2 text-brand me-1"></i>Commercial dashboard</strong><br><span class="text-muted">Budget, commitments, actual spend and cashflow are tracked in the project controls.</span></div></div>
              <div class="col-md-6"><div class="small"><strong><i class="bi bi-exclamation-triangle text-brand me-1"></i>Urgent matters</strong><br><span class="text-muted">Issues needing a client decision are escalated through the project communication process.</span></div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
.gallery-preview {
  display: block;
  border-radius: 14px;
  overflow: hidden;
  position: relative;
  aspect-ratio: 4/3;
  box-shadow: 0 4px 14px rgba(15,23,42,.08);
  transition: transform .18s ease, box-shadow .18s ease;
  text-decoration: none;
}
.gallery-preview:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(15,23,42,.16);
}
.gallery-preview img {
  width: 100%; height: 100%;
  object-fit: cover; display: block;
  transition: transform .3s ease;
}
.gallery-preview:hover img { transform: scale(1.06); }
.gallery-preview-title {
  position: absolute;
  left: 0; right: 0; bottom: 0;
  padding: 30px 12px 10px;
  background: linear-gradient(to top, rgba(15,23,42,.85), rgba(15,23,42,0));
  color: #fff;
  font-size: .78rem;
  font-weight: 600;
  text-align: left;
}
</style>

<?php page_footer(); ?>