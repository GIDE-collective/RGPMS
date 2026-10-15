<?php
/**
 * Personnel - project workspace.
 * Tabs: Overview | Site reports | Material requests | Progress | Photos
 * Each tab carries its submission form + history.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('personnel');
$user = current_user();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// Verify assignment
$myPersonnel = $pdo->prepare('SELECT * FROM personnel WHERE user_id = ?');
$myPersonnel->execute([$user['id']]);
$personnelRows = $myPersonnel->fetchAll();
$personnelIds = array_map(fn($p) => (int)$p['id'], $personnelRows);

$assigned = false;
if ($personnelIds) {
    $in = implode(',', array_fill(0, count($personnelIds), '?'));
    $chk = $pdo->prepare("SELECT COUNT(*) FROM project_personnel WHERE project_id = ? AND personnel_id IN ($in)");
    $chk->execute(array_merge([$id], $personnelIds));
    $assigned = (int)$chk->fetchColumn() > 0;
}
if (!$assigned) { http_response_code(403); die('<div style="font-family:sans-serif;padding:2rem;text-align:center;margin-top:3rem"><h3>403</h3><p>You are not assigned to this project.</p><p><a href="' . BASE_URL . '/personnel/dashboard.php">Back to my projects</a></p></div>'); }

$stmt = $pdo->prepare('SELECT p.*, c.company_name, u.full_name AS pm_name FROM projects p JOIN clients c ON c.id = p.client_id LEFT JOIN users u ON u.id = p.project_manager_id WHERE p.id = ?');
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) { flash_set('danger', 'Project not found.'); redirect('personnel/dashboard.php'); }

$tab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'reports', 'requests', 'progress', 'photos'];
if (!in_array($tab, $validTabs, true)) $tab = 'overview';

/* ---------------- POST handlers ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_report') {
        $date = $_POST['report_date'] ?? date('Y-m-d');
        $period = ($_POST['period'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
        $pdo->prepare('INSERT INTO site_reports (project_id, reported_by, report_date, period, completed_work, materials_used, delays, site_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $user['id'], $date, $period,
                trim($_POST['completed_work'] ?? ''),
                trim($_POST['materials_used'] ?? ''),
                trim($_POST['delays'] ?? ''),
                trim($_POST['site_notes'] ?? '')]);
        flash_set('success', 'Site report submitted.');
        redirect('personnel/project_view.php?id=' . $id . '&tab=reports');

    } elseif ($action === 'add_request') {
        $materialId = (int)($_POST['material_id'] ?? 0);
        $qty = (float)($_POST['quantity'] ?? 0);
        if ($materialId > 0 && $qty > 0) {
            $m = $pdo->prepare('SELECT unit FROM materials WHERE id = ?');
            $m->execute([$materialId]);
            $unit = $m->fetchColumn() ?: 'unit';
            $pdo->prepare('INSERT INTO material_requests (project_id, requested_by, material_id, quantity, unit, reason) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $user['id'], $materialId, $qty, $unit, trim($_POST['reason'] ?? '')]);
            notify((int)$project['project_manager_id'] ?: 1, 'New material request', 'A material request has been submitted by ' . $user['full_name'] . ' for "' . $project['name'] . '".', 'admin/material_requests.php');
            flash_set('success', 'Material request submitted for review.');
        }
        redirect('personnel/project_view.php?id=' . $id . '&tab=requests');

    } elseif ($action === 'update_stage') {
        $stageId = (int)($_POST['stage_id'] ?? 0);
        $pct = max(0, min(100, (float)($_POST['pct'] ?? 0)));
        $note = trim($_POST['note'] ?? '');
        $s = $pdo->prepare('SELECT progress_percent FROM project_stages WHERE id = ? AND project_id = ?');
        $s->execute([$stageId, $id]);
        $old = (float)$s->fetchColumn();
        $upd = $pdo->prepare('UPDATE project_stages SET progress_percent = ? WHERE id = ?');
        $upd->execute([$pct, $stageId]);
        $pdo->prepare('INSERT INTO progress_history (project_id, stage_id, reported_by, old_percent, new_percent, note) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $stageId, $user['id'], $old, $pct, $note]);
        recompute_project_progress($id);
        flash_set('success', 'Stage progress updated to ' . $pct . '%.');
        redirect('personnel/project_view.php?id=' . $id . '&tab=progress');

    } elseif ($action === 'upload_photo') {
        try {
            $path = handle_upload($_FILES['photo_file'], 'photos', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $pdo->prepare('INSERT INTO project_photos (project_id, uploaded_by, file_path, caption, status) VALUES (?, ?, ?, ?, "pending")')
                ->execute([$id, $user['id'], $path, trim($_POST['caption'] ?? '')]);
            notify((int)$project['project_manager_id'] ?: 1, 'Progress photograph awaiting approval', $user['full_name'] . ' uploaded a photograph for "' . $project['name'] . '".', 'admin/photos.php');
            flash_set('success', 'Photograph uploaded. It will be visible to the client after the Project Manager approves it.');
        } catch (RuntimeException $ex) {
            flash_set('danger', $ex->getMessage());
        }
        redirect('personnel/project_view.php?id=' . $id . '&tab=photos');
    }
}

/* ---------------- Data ---------------- */
$stages = project_stages($id);
$reports = $pdo->prepare('SELECT sr.*, u.full_name AS reporter FROM site_reports sr LEFT JOIN users u ON u.id = sr.reported_by WHERE sr.project_id = ? ORDER BY sr.report_date DESC');
$reports->execute([$id]);
$reportList = $reports->fetchAll();

$materials = $pdo->query('SELECT * FROM materials ORDER BY name')->fetchAll();
$reqs = $pdo->prepare('SELECT mr.*, m.name AS material_name FROM material_requests mr JOIN materials m ON m.id = mr.material_id WHERE mr.project_id = ? AND mr.requested_by = ? ORDER BY mr.created_at DESC');
$reqs->execute([$id, $user['id']]);
$reqList = $reqs->fetchAll();

$photos = $pdo->prepare('SELECT * FROM project_photos WHERE project_id = ? AND uploaded_by = ? ORDER BY uploaded_at DESC');
$photos->execute([$id, $user['id']]);
$photoList = $photos->fetchAll();

$status = effective_project_status($project);

page_header('Project: ' . $project['name'], 'dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/personnel/dashboard.php"><i class="bi bi-arrow-left me-1"></i>My projects</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($project['name']) ?> <?= project_status_badge($status) ?></h4>
    <div class="text-muted small"><?= e($project['company_name']) ?> · <?= e($project['location'] ?? '') ?> · PM: <?= e($project['pm_name'] ?? '-') ?></div>
  </div>
  <div class="fw-bold fs-5">Overall progress: <span class="text-warning"><?= (float)$project['progress_percent'] ?>%</span></div>
</div>

<ul class="nav nav-tabs mt-3 mb-4">
  <?php
  $tabLabels = [
    'overview' => ['Overview', 'bi-clipboard-data'],
    'reports'  => ['Site reports (' . count($reportList) . ')', 'bi-journal-text'],
    'requests' => ['Material requests (' . count($reqList) . ')', 'bi-box-seam'],
    'progress' => ['Progress', 'bi-graph-up'],
    'photos'   => ['Photos (' . count($photoList) . ')', 'bi-camera'],
  ];
  foreach ($tabLabels as $key => [$label, $icon]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=<?= $key ?>"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-warning"></i>Project information</h6>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-muted">Build type</dt><dd class="col-sm-8"><?= e($project['build_type'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Square metreage</dt><dd class="col-sm-8"><?= $project['square_meters'] ? number_format((float)$project['square_meters'], 2) . ' m²' : '-' ?></dd>
          <dt class="col-sm-4 text-muted">Start date</dt><dd class="col-sm-8"><?= nice_date($project['start_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Target deadline</dt><dd class="col-sm-8"><?= nice_date($project['target_deadline']) ?></dd>
          <dt class="col-sm-4 text-muted">Description</dt><dd class="col-sm-8"><?= nl2br(e($project['description'] ?? '-')) ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-lightning me-2 text-warning"></i>Quick actions</h6>
        <div class="d-grid gap-2">
          <a class="btn btn-outline-brand" href="?id=<?= $id ?>&tab=reports"><i class="bi bi-journal-plus me-2"></i>Submit site report</a>
          <a class="btn btn-outline-brand" href="?id=<?= $id ?>&tab=requests"><i class="bi bi-box-seam me-2"></i>Request material</a>
          <a class="btn btn-outline-brand" href="?id=<?= $id ?>&tab=progress"><i class="bi bi-graph-up me-2"></i>Update stage progress</a>
          <a class="btn btn-outline-brand" href="?id=<?= $id ?>&tab=photos"><i class="bi bi-camera me-2"></i>Upload progress photo</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'reports'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-journal-plus me-2 text-warning"></i>New site report</h6>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_report">
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label required">Report date</label>
              <input type="date" name="report_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 mb-3">
              <label class="form-label required">Period</label>
              <select name="period" class="form-select">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Completed work</label>
            <textarea name="completed_work" class="form-control" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Materials used</label>
            <textarea name="materials_used" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Delays (e.g. weather)</label>
            <textarea name="delays" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Site notes</label>
            <textarea name="site_notes" class="form-control" rows="2"></textarea>
          </div>
          <button class="btn btn-brand w-100">Submit report</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-warning"></i>Report history</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Date</th><th>Period</th><th>Completed work</th><th>Delays</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php if (!$reportList): ?><tr><td colspan="5" class="text-muted small text-center py-4">No reports yet.</td></tr><?php endif; ?>
            <?php foreach ($reportList as $r): ?>
              <tr>
                <td class="small text-nowrap"><?= nice_date($r['report_date']) ?></td>
                <td><?= badge($r['period'], 'info') ?></td>
                <td class="small"><?= mb_strimwidth(e($r['completed_work'] ?? '-'), 0, 70, '…') ?></td>
                <td><?= $r['delays'] ? badge('Yes', 'danger') : badge('None', 'success') ?></td>
                <td class="small text-muted"><?= nice_datetime($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'requests'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-box-seam me-2 text-warning"></i>Request material</h6>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_request">
          <div class="mb-3">
            <label class="form-label required">Material</label>
            <select name="material_id" class="form-select" required>
              <option value="">- select material -</option>
              <?php foreach ($materials as $m): ?>
                <option value="<?= $m['id'] ?>"><?= e($m['name']) ?> (stock: <?= (float)$m['stock_quantity'] ?> <?= e($m['unit']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label required">Quantity required</label>
            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason / shortage detail</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Running low for the first-floor pour"></textarea>
          </div>
          <button class="btn btn-brand w-100">Submit request</button>
        </form>
        <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>The Project Manager reviews every request and sets it to Approved or Rejected.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-inbox me-2 text-warning"></i>My requests for this project</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>#</th><th>Material</th><th>Qty</th><th>Reason</th><th>Status</th><th>Reviewed</th></tr></thead>
          <tbody>
            <?php if (!$reqList): ?><tr><td colspan="6" class="text-muted small text-center py-4">No requests yet.</td></tr><?php endif; ?>
            <?php foreach ($reqList as $r): ?>
              <tr>
                <td class="small text-muted">#<?= $r['id'] ?></td>
                <td><?= e($r['material_name']) ?></td>
                <td><?= (float)$r['quantity'] ?> <?= e($r['unit']) ?></td>
                <td class="small"><?= e($r['reason'] ?? '-') ?></td>
                <td><?= match ($r['status']) { 'approved' => badge('Approved', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending', 'warning') } ?></td>
                <td class="small text-muted"><?= nice_datetime($r['reviewed_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'progress'): ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up me-2 text-warning"></i>Update stage progress</h6>
        <?php foreach ($stages as $st): ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold"><?= e($st['name']) ?></span><span><?= (float)$st['progress_percent'] ?>%</span></div>
            <div class="progress mb-2"><div class="progress-bar bg-warning" style="width:<?= (float)$st['progress_percent'] ?>%"></div></div>
            <form method="post" class="d-flex gap-2">
              <?= csrf_field() ?><input type="hidden" name="action" value="update_stage"><input type="hidden" name="stage_id" value="<?= $st['id'] ?>">
              <input type="number" name="pct" class="form-control form-control-sm" min="0" max="100" value="<?= (float)$st['progress_percent'] ?>" style="max-width:90px">
              <input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)">
              <button class="btn btn-sm btn-outline-brand text-nowrap">Update</button>
            </form>
          </div>
        <?php endforeach; ?>
        <hr>
        <div class="d-flex justify-content-between fw-semibold">
          <span>Overall project progress</span><span class="text-warning"><?= (float)$project['progress_percent'] ?>%</span>
        </div>
        <hr>
        <h6 class="fw-semibold mb-2"><i class="bi bi-clock-history me-2 text-warning"></i>Progress history</h6>
        <?php
          $ph = $pdo->prepare('SELECT ph.*, s.name AS stage_name FROM progress_history ph LEFT JOIN project_stages s ON s.id = ph.stage_id WHERE ph.project_id = ? ORDER BY ph.created_at DESC LIMIT 15');
          $ph->execute([$id]);
          $phList = $ph->fetchAll();
          if (!$phList): ?><p class="text-muted small mb-0">No progress updates yet.</p>
          <?php else: foreach ($phList as $h): ?>
          <div class="d-flex gap-2 py-1 border-bottom small">
            <span class="text-nowrap text-muted"><?= nice_date($h['created_at']) ?></span>
            <span><strong><?= e($h['stage_name'] ?? 'Overall') ?></strong>: <?= (float)$h['old_percent'] ?>% - <?= (float)$h['new_percent'] ?>%<?= $h['note'] ? ' - ' . e($h['note']) : '' ?></span>
          </div>
          <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-warning"></i>How it works</h6></div>
      <div class="card-body">
        <p class="small text-muted mb-2">Construction stages on this project:</p>
        <ul class="small text-muted mb-0">
          <li>Update the <strong>percentage complete</strong> for each stage you are working on.</li>
          <li>Add a short note (e.g. “ring beam cast”) so the progress history is clear.</li>
          <li>The overall project progress is calculated automatically as the average of all stages.</li>
          <li>Every update is recorded with your name and the time, and feeds the final project summary.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'photos'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-camera me-2 text-warning"></i>Upload progress photograph</h6>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?><input type="hidden" name="action" value="upload_photo">
          <div class="mb-3">
            <label class="form-label">Caption</label>
            <input type="text" name="caption" class="form-control" placeholder="e.g. First floor brickwork - north wall">
          </div>
          <div class="mb-3">
            <label class="form-label required">Photo (JPG, PNG, GIF, WebP - max 8 MB)</label>
            <input type="file" name="photo_file" class="form-control" accept="image/*" required>
          </div>
          <button class="btn btn-brand w-100"><i class="bi bi-cloud-arrow-up me-1"></i>Upload</button>
        </form>
        <p class="small text-muted mt-2 mb-0"><i class="bi bi-shield-check me-1"></i>Your upload is stamped with your name and time. The client will only see it after the Project Manager approves it.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-images me-2 text-warning"></i>My uploads</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <?php if (!$photoList): ?><p class="text-muted small mb-0">No photographs uploaded yet.</p><?php endif; ?>
          <?php foreach ($photoList as $ph): ?>
            <div class="col-md-4">
              <div class="card h-100">
                <a href="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" target="_blank">
                  <img src="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" class="card-img-top photo-thumb" alt="<?= e($ph['caption'] ?? '') ?>">
                </a>
                <div class="card-body p-2">
                  <div class="small"><?= e($ph['caption'] ?? 'No caption') ?></div>
                  <div class="small text-muted"><?= nice_datetime($ph['uploaded_at']) ?></div>
                  <div class="mt-1"><?= match ($ph['status']) { 'approved' => badge('Approved', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending approval', 'warning') } ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php page_footer(); ?>
