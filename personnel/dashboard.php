<?php
/**
 * Personnel - dashboard: projects/tasks assigned to the logged-in staff member.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('personnel');
$user = current_user();

$pdo = db();

// Find personnel record(s) linked to this user account
$myPersonnel = $pdo->prepare('SELECT * FROM personnel WHERE user_id = ?');
$myPersonnel->execute([$user['id']]);
$personnelRows = $myPersonnel->fetchAll();
$personnelIds = array_map(fn($p) => (int)$p['id'], $personnelRows);

$projects = [];
if ($personnelIds) {
    $in = implode(',', array_fill(0, count($personnelIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT p.*, c.company_name, c.contact_person
         FROM project_personnel pp
         JOIN projects p ON p.id = pp.project_id
         JOIN clients c ON c.id = p.client_id
         WHERE pp.personnel_id IN ($in)
         ORDER BY p.created_at DESC"
    );
    $stmt->execute($personnelIds);
    $projects = $stmt->fetchAll();
}

// My recent reports / requests / photos
$myReports = $pdo->prepare(
    'SELECT sr.*, p.name AS project_name FROM site_reports sr JOIN projects p ON p.id = sr.project_id WHERE sr.reported_by = ? ORDER BY sr.created_at DESC LIMIT 5'
);
$myReports->execute([$user['id']]);
$reports = $myReports->fetchAll();

$myReqs = $pdo->prepare(
    'SELECT mr.*, m.name AS material_name, p.name AS project_name FROM material_requests mr JOIN materials m ON m.id = mr.material_id JOIN projects p ON p.id = mr.project_id WHERE mr.requested_by = ? ORDER BY mr.created_at DESC LIMIT 5'
);
$myReqs->execute([$user['id']]);
$requests = $myReqs->fetchAll();

page_header('My projects', 'dashboard');
page_heading('My projects', 'Welcome back, ' . $user['full_name']);
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-primary"><i class="bi bi-kanban"></i></div>
      <div><div class="value"><?= count($projects) ?></div><div class="label">My projects</div></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-success"><i class="bi bi-journal-text"></i></div>
      <div><div class="value"><?= count($reports) ?></div><div class="label">Recent reports</div></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-warning"><i class="bi bi-box-seam"></i></div>
      <div><div class="value"><?= count($requests) ?></div><div class="label">Material requests</div></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-info"><i class="bi bi-camera"></i></div>
      <div><div class="value"><?= count($projects) ?></div><div class="label">Photo uploads available</div></div>
    </div></div>
  </div>
</div>

<?php if (!$projects): ?>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body text-center py-5">
      <i class="bi bi-inbox display-5 text-muted"></i>
      <p class="text-muted mt-3 mb-0">You are not assigned to any projects yet. Contact your Project Manager.</p>
    </div>
  </div>
<?php endif; ?>

<div class="row g-4">
  <?php foreach ($projects as $p): $st = effective_project_status($p); ?>
  <div class="col-md-6 col-xl-4">
    <div class="card project-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="card-title mb-0"><a class="text-decoration-none" href="<?= BASE_URL ?>/personnel/project_view.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></h6>
          <?= project_status_badge($st) ?>
        </div>
        <div class="small text-muted mb-3"><i class="bi bi-geo-alt me-1"></i><?= e($p['location'] ?? '-') ?><br><i class="bi bi-flag me-1"></i>Deadline: <?= nice_date($p['target_deadline']) ?></div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Progress</span><span class="fw-semibold"><?= (float)$p['progress_percent'] ?>%</span></div>
        <div class="progress mb-3"><div class="progress-bar bg-warning" style="width:<?= (float)$p['progress_percent'] ?>%"></div></div>
        <a class="btn btn-sm btn-outline-brand w-100" href="<?= BASE_URL ?>/personnel/project_view.php?id=<?= $p['id'] ?>">Open project <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mt-1">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-warning"></i>My recent site reports</h6></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Date</th><th>Project</th><th>Delays</th></tr></thead>
          <tbody>
            <?php if (!$reports): ?><tr><td colspan="3" class="text-muted small text-center py-3">No reports yet.</td></tr><?php endif; ?>
            <?php foreach ($reports as $r): ?>
              <tr><td class="small"><?= nice_date($r['report_date']) ?></td><td class="small"><?= e($r['project_name']) ?></td><td><?= $r['delays'] ? badge('Yes', 'danger') : badge('None', 'success') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-warning"></i>My material requests</h6></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Material</th><th>Project</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$requests): ?><tr><td colspan="3" class="text-muted small text-center py-3">No requests yet.</td></tr><?php endif; ?>
            <?php foreach ($requests as $r): ?>
              <tr><td class="small"><?= e($r['material_name']) ?> (<?= (float)$r['quantity'] ?> <?= e($r['unit']) ?>)</td><td class="small"><?= e($r['project_name']) ?></td><td><?= match ($r['status']) { 'approved' => badge('Approved', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending', 'warning') } ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
