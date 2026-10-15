<?php
/**
 * Client - project portal view.
 * Shows: project info, personnel (names & roles), approved photographs (uploader + time),
 * overall progress %, project documents, quotations and invoices.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('client');
$user = current_user();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// Ownership check
$cstmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ?');
$cstmt->execute([$user['id']]);
$clientIds = array_map(fn($r) => (int)$r['id'], $cstmt->fetchAll());
if (!$clientIds) { http_response_code(403); die('<h3 style="font-family:sans-serif;text-align:center;margin-top:3rem">403 - No access</h3>'); }
$in = implode(',', array_fill(0, count($clientIds), '?'));

$stmt = $pdo->prepare("SELECT p.*, u.full_name AS pm_name FROM projects p LEFT JOIN users u ON u.id = p.project_manager_id WHERE p.id = ? AND p.client_id IN ($in)");
$stmt->execute(array_merge([$id], $clientIds));
$project = $stmt->fetch();
if (!$project) { flash_set('danger', 'Project not found.'); redirect('client/dashboard.php'); }

$tab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'photos', 'documents', 'quotations', 'invoices'];
if (!in_array($tab, $validTabs, true)) $tab = 'overview';

// ---- Detect personnel columns so queries work regardless of schema ----
$pCols       = $pdo->query("SHOW COLUMNS FROM personnel")->fetchAll(PDO::FETCH_COLUMN);
$hasSpecialty = in_array('specialty', $pCols);
$hasType      = in_array('type', $pCols);
$roleSelect = $hasSpecialty ? 'pn.specialty' : "''";
$typeSelect = $hasType      ? 'pn.type'      : "'internal'";

// Team (names from users, role from specialty)
$team = $pdo->prepare(
    "SELECT u.full_name AS name, $roleSelect AS role, $typeSelect AS type
     FROM project_personnel pp
     JOIN personnel pn ON pn.id = pp.personnel_id
     LEFT JOIN users u ON u.id = pn.user_id
     WHERE pp.project_id = ?
     ORDER BY u.full_name"
);
$team->execute([$id]);
$teamList = $team->fetchAll();

// Approved photos only
$photos = $pdo->prepare('SELECT ph.*, u.full_name AS uploader FROM project_photos ph LEFT JOIN users u ON u.id = ph.uploaded_by WHERE ph.project_id = ? AND ph.status = "approved" ORDER BY ph.approved_at DESC');
$photos->execute([$id]);
$photoList = $photos->fetchAll();

$docs = $pdo->prepare('SELECT * FROM documents WHERE project_id = ? ORDER BY uploaded_at DESC');
$docs->execute([$id]);
$docList = $docs->fetchAll();

$quotes = $pdo->prepare('SELECT * FROM quotations WHERE project_id = ? ORDER BY issue_date DESC');
$quotes->execute([$id]);
$quoteList = $quotes->fetchAll();

$inv = $pdo->prepare('SELECT * FROM invoices WHERE project_id = ? ORDER BY issue_date DESC');
$inv->execute([$id]);
$invList = $inv->fetchAll();

$status = effective_project_status($project);

page_header('Project: ' . $project['name'], 'dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/client/dashboard.php"><i class="bi bi-arrow-left me-1"></i>My projects</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($project['name']) ?> <?= project_status_badge($status) ?></h4>
    <div class="text-muted small"><?= e($project['location'] ?? '') ?> · Project manager: <?= e($project['pm_name'] ?? '-') ?></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-primary"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="flex-grow-1">
        <div class="value"><?= (float)$project['progress_percent'] ?>%</div><div class="label">Overall progress</div>
        <div class="progress mt-2"><div class="progress-bar" style="width:<?= (float)$project['progress_percent'] ?>%"></div></div>
      </div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-primary"><i class="bi bi-people"></i></div>
      <div><div class="value"><?= count($teamList) ?></div><div class="label">People on your project</div></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card stat-tile shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="icon text-bg-success"><i class="bi bi-camera"></i></div>
      <div><div class="value"><?= count($photoList) ?></div><div class="label">Approved photos</div></div>
    </div></div>
  </div>
</div>

<ul class="nav nav-tabs mt-3 mb-4">
  <?php
  $tabLabels = [
    'overview'   => ['Overview', 'bi-clipboard-data'],
    'controls'   => ['Reports & controls', 'bi-clipboard-data'],
    'photos'     => ['Progress photos (' . count($photoList) . ')', 'bi-camera'],
    'documents'  => ['Documents (' . count($docList) . ')', 'bi-folder2-open'],
    'quotations' => ['Quotations (' . count($quoteList) . ')', 'bi-file-earmark-text'],
    'invoices'   => ['Invoices (' . count($invList) . ')', 'bi-receipt'],
  ];
  foreach ($tabLabels as $key => [$label, $icon]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= $key === 'controls' ? BASE_URL . '/client/project_controls.php?id=' . $id : '?id=' . $id . '&tab=' . $key ?>"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-brand"></i>Project information</h6>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-muted">Build type</dt><dd class="col-sm-8"><?= e($project['build_type'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Square metreage</dt><dd class="col-sm-8"><?= $project['square_meters'] ? number_format((float)$project['square_meters'], 2) . ' m²' : '-' ?></dd>
          <dt class="col-sm-4 text-muted">Start date</dt><dd class="col-sm-8"><?= nice_date($project['start_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Target completion</dt><dd class="col-sm-8"><?= nice_date($project['target_deadline']) ?></dd>
          <dt class="col-sm-4 text-muted">Actual completion</dt><dd class="col-sm-8"><?= nice_date($project['actual_completion_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Description</dt><dd class="col-sm-8"><?= nl2br(e($project['description'] ?? '-')) ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-people me-2 text-brand"></i>Who is working on your project</h6>
        <?php if (!$teamList): ?><p class="text-muted small">The team will be confirmed once work begins.</p><?php endif; ?>
        <?php foreach ($teamList as $t): ?>
          <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
            <span><?= e($t['name'] ?: '—') ?></span>
            <span class="text-muted">
              <?= e($t['role'] ?: '-') ?>
              <?php if (($t['type'] ?? 'internal') === 'subcontractor'): ?>
                <?= badge('Subcontractor', 'info') ?>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
        <hr>
        <p class="small text-muted mb-0"><i class="bi bi-chat-left-text me-1"></i>Need an update? The team posts approved photographs and progress here - no phone calls needed.</p>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'photos'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-camera me-2 text-brand"></i>Approved progress photographs</h6>
  </div>
  <div class="card-body">
    <?php if (!$photoList): ?>
      <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>No approved photographs yet. Photographs are reviewed by the Project Manager before they appear here.</p>
    <?php endif; ?>
    <div class="row g-3">
      <?php foreach ($photoList as $ph): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <a href="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" target="_blank">
              <img src="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" class="card-img-top photo-thumb" alt="<?= e($ph['caption'] ?? '') ?>">
            </a>
            <div class="card-body p-2">
              <div class="small fw-semibold"><?= e($ph['caption'] ?? 'No caption') ?></div>
              <div class="small text-muted">Uploaded by <?= e($ph['uploader']) ?> on <?= nice_datetime($ph['uploaded_at']) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'documents'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-folder2-open me-2 text-brand"></i>Project documents</h6></div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Document</th><th>Category</th><th>Added</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$docList): ?><tr><td colspan="4" class="text-muted small text-center py-4">No documents shared yet.</td></tr><?php endif; ?>
        <?php foreach ($docList as $d): ?>
          <tr>
            <td><i class="bi bi-file-earmark me-1 text-brand"></i><?= e($d['name']) ?></td>
            <td><?= badge($d['category'], 'secondary') ?></td>
            <td class="small text-muted"><?= nice_datetime($d['uploaded_at']) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/<?= e($d['file_path']) ?>" target="_blank"><i class="bi bi-download me-1"></i>Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'quotations'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2 text-brand"></i>Quotations</h6></div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Number</th><th>Issued</th><th>Valid until</th><th>Total</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$quoteList): ?><tr><td colspan="6" class="text-muted small text-center py-4">No quotations yet.</td></tr><?php endif; ?>
        <?php foreach ($quoteList as $q): $totals = quotation_totals((int)$q['id']); $qStatus = effective_quotation_status($q); ?>
          <tr>
            <td class="fw-semibold"><?= e($q['quotation_number']) ?></td>
            <td class="small"><?= nice_date($q['issue_date']) ?></td>
            <td class="small"><?= nice_date($q['valid_until']) ?></td>
            <td class="small"><?= money($totals['total']) ?></td>
            <td><?= quotation_status_badge($qStatus) ?></td>
            <td class="text-end">
              <?php if ($qStatus === 'sent'): ?>
                <a class="btn btn-sm btn-brand" href="<?= BASE_URL ?>/client/quotation_view.php?id=<?= $q['id'] ?>"><i class="bi bi-pencil me-1"></i>Respond now</a>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/client/quotation_view.php?id=<?= $q['id'] ?>"><i class="bi bi-eye me-1"></i>View</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'invoices'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-brand"></i>Invoices</h6></div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Number</th><th>Issued</th><th>Due</th><th>Description</th><th>Amount</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$invList): ?><tr><td colspan="7" class="text-muted small text-center py-4">No invoices yet.</td></tr><?php endif; ?>
        <?php foreach ($invList as $i): ?>
          <tr>
            <td class="fw-semibold"><?= e($i['invoice_number']) ?></td>
            <td class="small"><?= nice_date($i['issue_date']) ?></td>
            <td class="small"><?= nice_date($i['due_date']) ?></td>
            <td class="small"><?= e($i['description'] ?? '-') ?></td>
            <td class="small"><?= money($i['amount']) ?></td>
            <td><?= $i['status'] === 'paid' ? badge('Paid', 'success') : badge('Issued', 'warning') ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/client/invoice_view.php?id=<?= $i['id'] ?>"><i class="bi bi-eye me-1"></i>View</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/client/invoice_view.php?id=<?= $i['id'] ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php page_footer(); ?>