<?php
/**
 * Client - dashboard: their projects + status overview.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('client');
$user = current_user();

$pdo = db();

// Client record for this user
$cstmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ?');
$cstmt->execute([$user['id']]);
$clientIds = array_map(fn($r) => (int)$r['id'], $cstmt->fetchAll());

$projects = [];
if ($clientIds) {
    $in = implode(',', array_fill(0, count($clientIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE client_id IN ($in) ORDER BY created_at DESC");
    $stmt->execute($clientIds);
    $projects = $stmt->fetchAll();
}

// Recent notifications
$notifs = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
$notifs->execute([$user['id']]);
$notifList = $notifs->fetchAll();

// Open quotation awaiting response
$openQuotes = [];
if ($projects) {
    $pidIn = implode(',', array_map(fn($p) => (int)$p['id'], $projects));
    $oq = $pdo->query("SELECT q.*, p.name AS project_name FROM quotations q JOIN projects p ON p.id = q.project_id WHERE q.status = 'sent' AND q.project_id IN ($pidIn) ORDER BY q.issue_date DESC");
    $openQuotes = $oq->fetchAll();
}

page_header('My projects', 'dashboard');
page_heading('My projects', 'Welcome, ' . $user['full_name']);
?>

<?php if ($openQuotes): ?>
  <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 rounded-4 shadow-sm">
    <i class="bi bi-file-earmark-text fs-4"></i>
    <div class="fw-semibold">Quotation(s) awaiting your response:</div>
    <?php foreach ($openQuotes as $q): ?>
      <a class="btn btn-sm btn-warning" href="<?= BASE_URL ?>/client/quotation_view.php?id=<?= $q['id'] ?>"><?= e($q['quotation_number']) ?> - <?= e($q['project_name']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <?php if (!$projects): ?>
    <div class="col-12">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body text-center py-5">
          <i class="bi bi-inbox display-5 text-muted"></i>
          <p class="text-muted mt-3 mb-1">You have no active projects yet.</p>
          <p class="small text-muted mb-3">When RG Trade and Projects registers a project for you, it will appear here.</p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($projects as $p): $st = effective_project_status($p); ?>
  <div class="col-md-6 col-xl-4">
    <div class="card project-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="card-title mb-0"><a class="text-decoration-none" href="<?= BASE_URL ?>/client/project_view.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></h6>
          <?= project_status_badge($st) ?>
        </div>
        <div class="small text-muted mb-3">
          <i class="bi bi-geo-alt me-1"></i><?= e($p['location'] ?? '-') ?><br>
          <i class="bi bi-flag me-1"></i>Target completion: <?= nice_date($p['target_deadline']) ?>
        </div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Progress</span><span class="fw-semibold"><?= (float)$p['progress_percent'] ?>%</span></div>
        <div class="progress mb-3"><div class="progress-bar bg-warning" style="width:<?= (float)$p['progress_percent'] ?>%"></div></div>
        <a class="btn btn-sm btn-outline-brand w-100" href="<?= BASE_URL ?>/client/project_view.php?id=<?= $p['id'] ?>">View project <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($notifList): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3 d-flex justify-content-between align-items-center">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-bell me-2 text-warning"></i>Latest updates</h6>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/notifications.php">View all</a>
  </div>
  <ul class="list-group list-group-flush">
    <?php foreach ($notifList as $n): ?>
      <li class="list-group-item d-flex gap-3 align-items-start">
        <i class="bi bi-bell fs-5 text-warning mt-1"></i>
        <div class="flex-grow-1">
          <div class="small fw-semibold"><?= e($n['title']) ?></div>
          <div class="small text-muted"><?= e($n['message'] ?? '') ?></div>
          <div class="small text-muted"><?= time_ago($n['created_at']) ?></div>
        </div>
        <?php if ($n['link']): ?><a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/<?= e($n['link']) ?>">Open</a><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php page_footer(); ?>
