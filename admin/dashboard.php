<?php
/**
 * Admin - Project Manager / Administrator dashboard.
 * Projects grouped by status + pending approvals + company-wide material stock.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();

// All projects with client + progress
$projects = $pdo->query(
    'SELECT p.*, c.company_name, c.contact_person
     FROM projects p JOIN clients c ON c.id = p.client_id
     ORDER BY p.created_at DESC'
)->fetchAll();

// Build the 4 dashboard groups (unchanged layout).
// effective_project_status() returns canonical keys; translate them here.
$groups = [
    'not_yet_commenced' => [],
    'in_progress'       => [],
    'delayed'           => [],
    'completed'         => [],
];

foreach ($projects as $p) {
    $canonical = effective_project_status($p);

    // Map canonical → dashboard bucket
    $bucket = match ($canonical) {
        'not_yet_commenced' => 'not_yet_commenced',
        'delayed'           => 'delayed',
        'completed'         => 'completed',
        'cancelled'         => null,           // hide cancelled from dash
        default             => 'in_progress',  // active, on_hold
    };

    if ($bucket !== null) {
        $groups[$bucket][] = $p;
    }
}

// Company-wide material stock
$stock = $pdo->query('SELECT * FROM materials ORDER BY name')->fetchAll();
$lowStock = array_filter($stock, fn($m) => (float)($m['stock_quantity'] ?? 0) <= 10);

// Pending approvals
$pendingPhotos   = (int)$pdo->query('SELECT COUNT(*) FROM project_photos WHERE status = "pending"')->fetchColumn();
$pendingRequests = (int)$pdo->query('SELECT COUNT(*) FROM material_requests WHERE status = "pending"')->fetchColumn();
$openIssues = (int)$pdo->query('SELECT COUNT(*) FROM project_issues WHERE status IN ("open","under_review","escalated")')->fetchColumn();
$quotationResponses = $pdo->query(
    'SELECT q.id, q.quotation_number, q.status, p.name AS project_name, q.client_response_at, q.valid_until
     FROM quotations q JOIN projects p ON p.id = q.project_id
     WHERE q.status IN ("approved", "re_evaluation") AND q.client_response_at IS NOT NULL
     ORDER BY q.client_response_at DESC LIMIT 5'
)->fetchAll();

// Totals
$totalBudget = array_sum(array_column($projects, 'budget'));

page_header('Dashboard', 'dashboard');
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-primary"><i class="bi bi-kanban"></i></div>
      <div><div class="value"><?= count($projects) ?></div><div class="label">Total projects</div></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-success"><i class="bi bi-graph-up-arrow"></i></div>
      <div><div class="value"><?= count($groups['in_progress']) ?></div><div class="label">In progress</div></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="value"><?= count($groups['delayed']) ?></div><div class="label">Delayed</div></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-warning"><i class="bi bi-wallet2"></i></div>
      <div><div class="value small"><?= money($totalBudget) ?></div><div class="label">Total budget</div></div>
    </div></div>
  </div>
</div>

<?php if ($pendingPhotos || $pendingRequests || $openIssues): ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 shadow-sm rounded-4">
  <i class="bi bi-bell fs-4"></i>
  <div class="fw-semibold">Items awaiting your approval:</div>
  <?php if ($pendingPhotos): ?><a class="btn btn-sm btn-outline-warning" href="<?= BASE_URL ?>/admin/photos.php"><i class="bi bi-camera me-1"></i><?= $pendingPhotos ?> progress photo(s)</a><?php endif; ?>
  <?php if ($pendingRequests): ?><a class="btn btn-sm btn-outline-warning" href="<?= BASE_URL ?>/admin/material_requests.php"><i class="bi bi-box-seam me-1"></i><?= $pendingRequests ?> material request(s)</a><?php endif; ?>
  <?php if ($openIssues): ?><span class="btn btn-sm btn-outline-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $openIssues ?> open project issue(s)</span><?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <?php
    $groupMeta = [
        'not_yet_commenced' => ['Not yet commenced', 'secondary', 'bi-hourglass-split'],
        'in_progress'       => ['In progress',       'primary',   'bi-play-circle'],
        'delayed'           => ['Delayed',           'danger',    'bi-exclamation-octagon'],
        'completed'         => ['Completed',         'success',   'bi-check-circle'],
    ];
    foreach ($groupMeta as $key => [$gLabel, $gColor, $gIcon]):
        $items = $groups[$key];
    ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white rounded-top-4 d-flex align-items-center justify-content-between py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi <?= $gIcon ?> text-<?= $gColor ?> me-2"></i><?= $gLabel ?>
          <span class="badge text-bg-<?= $gColor ?> ms-1"><?= count($items) ?></span></h6>
        <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/projects.php?status=<?= $key ?>">View all</a>
      </div>
      <div class="card-body">
        <?php if (!$items): ?>
          <p class="text-muted small mb-0">No projects in this group.</p>
        <?php endif; ?>
        <?php foreach ($items as $p): ?>
          <div class="d-flex flex-wrap align-items-center gap-3 py-2 border-bottom">
            <div class="flex-grow-1" style="min-width:220px">
              <div class="fw-semibold"><a class="text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></div>
              <div class="small mt-1"><a class="text-decoration-none text-muted" href="<?= BASE_URL ?>/admin/project_controls.php?id=<?= $p['id'] ?>"><i class="bi bi-clipboard-data me-1"></i>Project controls</a></div>
              <div class="small text-muted"><?= e($p['company_name']) ?> · <?= e($p['build_type'] ?? '-') ?></div>
            </div>
            <div class="text-end" style="min-width:150px">
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="min-width:80px"><div class="progress-bar <?= (float)$p['progress_percent'] == 100 ? 'bg-success' : 'bg-warning' ?>" style="width:<?= (float)$p['progress_percent'] ?>%"></div></div>
                <span class="small fw-semibold"><?= (float)$p['progress_percent'] ?>%</span>
              </div>
              <div class="small text-muted mt-1">Deadline: <?= nice_date($p['target_deadline']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white rounded-top-4 py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-check2-square text-warning me-2"></i>Quotation responses</h6>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if (!$quotationResponses): ?><li class="list-group-item text-muted small">No client responses yet.</li><?php endif; ?>
          <?php foreach ($quotationResponses as $q): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div class="small">
                <a class="text-decoration-none" href="<?= BASE_URL ?>/admin/quotation_view.php?id=<?= $q['id'] ?>"><?= e($q['quotation_number']) ?></a>
                <div class="text-muted"><?= e($q['project_name']) ?></div>
              </div>
              <?= quotation_status_badge(effective_quotation_status($q)) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-box-seam text-warning me-2"></i>Material stock</h6>
        <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/materials.php">Manage</a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if (!$stock): ?><li class="list-group-item text-muted small">No materials recorded.</li><?php endif; ?>
          <?php foreach ($stock as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
              <span class="small"><?= e($m['name']) ?></span>
              <span class="<?= (float)($m['stock_quantity'] ?? 0) <= 10 ? 'text-danger fw-semibold' : '' ?> small">
                <?= (float)($m['stock_quantity'] ?? 0) ?> <?= e($m['unit'] ?? '') ?>
              </span>
            </li>
          <?php endforeach; ?>
          <?php if ($lowStock): ?>
            <li class="list-group-item small text-danger bg-danger-subtle"><i class="bi bi-exclamation-triangle me-1"></i>Low stock: <?= implode(', ', array_map(fn($m) => $m['name'], $lowStock)) ?></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>