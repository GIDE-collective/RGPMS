<?php
/**
 * Admin - project detail & management hub.
 * Tabs: Overview, Personnel, Documents, Materials, Photos, Progress & Reports, Quotations, Invoices, Summary.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT p.*, c.company_name, c.contact_person, c.email AS client_email,
            u.full_name AS pm_name
     FROM projects p
     JOIN clients c ON c.id = p.client_id
     LEFT JOIN users u ON u.id = p.project_manager_id
     WHERE p.id = ?'
);
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) { flash_set('danger', 'Project not found.'); redirect('admin/projects.php'); }

$tab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'personnel', 'documents', 'materials', 'photos', 'progress', 'quotations', 'invoices', 'summary'];
if (!in_array($tab, $validTabs, true)) $tab = 'overview';

// ---- Detect personnel columns so the queries work regardless of schema ----
$pCols       = $pdo->query("SHOW COLUMNS FROM personnel")->fetchAll(PDO::FETCH_COLUMN);
$hasSpecialty = in_array('specialty', $pCols);
$hasType      = in_array('type', $pCols);

$roleSelect = $hasSpecialty ? 'pn.specialty' : "''";
$typeSelect = $hasType      ? 'pn.type'      : "'internal'";

/* ---------------- POST handlers ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $status = normalise_project_status($_POST['status'] ?? 'not_yet_commenced');
        $upd = $pdo->prepare('UPDATE projects SET status = ? WHERE id = ?');
        $upd->execute([$status, $id]);
        if ($status === 'completed') {
            $upd2 = $pdo->prepare('UPDATE projects SET actual_completion_date = ? WHERE id = ? AND actual_completion_date IS NULL');
            $upd2->execute([date('Y-m-d'), $id]);
            $upd3 = $pdo->prepare('UPDATE projects SET progress_percent = 100 WHERE id = ?');
            $upd3->execute([$id]);
            notify_client_of_project($id, 'Project completed 🎉', 'Your project "' . $project['name'] . '" has been marked complete. The final project summary is now available.', 'client/project_view.php?id=' . $id);
        } else {
            notify_client_of_project($id, 'Project status updated', 'Status for "' . $project['name'] . '" changed to: ' . str_replace('_', ' ', $status), 'client/project_view.php?id=' . $id);
        }
        flash_set('success', 'Project status updated.');
        redirect('admin/project_view.php?id=' . $id);

    } elseif ($action === 'update_budget') {
        // Negative values are clamped to 0
        $budget = max(0, (float)($_POST['budget'] ?? 0));
        $upd = $pdo->prepare('UPDATE projects SET budget = ? WHERE id = ?');
        $upd->execute([$budget, $id]);
        flash_set('success', 'Budget updated.');
        redirect('admin/project_view.php?id=' . $id . '&tab=overview');

    } elseif ($action === 'assign_personnel') {
        $selected = array_map('intval', $_POST['personnel_ids'] ?? []);
        $pdo->prepare('DELETE FROM project_personnel WHERE project_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO project_personnel (project_id, personnel_id) VALUES (?, ?)');
        foreach ($selected as $pid) { if ($pid > 0) $ins->execute([$id, $pid]); }
        notify_client_of_project($id, 'Project team updated', 'The team assigned to "' . $project['name'] . '" has been updated.', 'client/project_view.php?id=' . $id);
        flash_set('success', 'Personnel assignment updated.');
        redirect('admin/project_view.php?id=' . $id . '&tab=personnel');

    } elseif ($action === 'add_stage') {
        $name = trim($_POST['stage_name'] ?? '');
        if ($name !== '') {
            $pdo->prepare('INSERT INTO project_stages (project_id, name) VALUES (?, ?)')->execute([$id, $name]);
            recompute_project_progress($id);
            flash_set('success', 'Stage added.');
        }
        redirect('admin/project_view.php?id=' . $id . '&tab=progress');

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
        redirect('admin/project_view.php?id=' . $id . '&tab=progress');

    } elseif ($action === 'upload_document') {
        $name = trim($_POST['doc_name'] ?? '');
        $cat  = $_POST['doc_category'] ?? 'other';
        try {
            $path = handle_upload($_FILES['doc_file'], 'documents', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'zip']);
            $pdo->prepare('INSERT INTO documents (project_id, name, category, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $name !== '' ? $name : basename($_FILES['doc_file']['name']), $cat, $path, $user['id']]);
            notify_client_of_project($id, 'New project document', 'A document has been added to your project: ' . ($name ?: 'Document'), 'client/project_view.php?id=' . $id);
            flash_set('success', 'Document uploaded.');
        } catch (RuntimeException $ex) {
            flash_set('danger', $ex->getMessage());
        }
        redirect('admin/project_view.php?id=' . $id . '&tab=documents');

    } elseif ($action === 'delete_document') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $d = $pdo->prepare('SELECT file_path FROM documents WHERE id = ? AND project_id = ?');
        $d->execute([$docId, $id]);
        if ($fp = $d->fetchColumn()) {
            @unlink(__DIR__ . '/../' . $fp);
            $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$docId]);
        }
        flash_set('success', 'Document removed.');
        redirect('admin/project_view.php?id=' . $id . '&tab=documents');

    } elseif ($action === 'record_material') {
        $materialId = (int)($_POST['material_id'] ?? 0);
        $qty = (float)($_POST['quantity'] ?? 0);
        if ($materialId > 0 && $qty > 0) {
            $m = $pdo->prepare('SELECT unit FROM materials WHERE id = ?');
            $m->execute([$materialId]);
            $unit = $m->fetchColumn() ?: 'unit';
            $pdo->prepare('INSERT INTO project_materials (project_id, material_id, quantity, unit) VALUES (?, ?, ?, ?)')
                ->execute([$id, $materialId, $qty, $unit]);
            $pdo->prepare('UPDATE materials SET stock_quantity = stock_quantity - ? WHERE id = ?')->execute([$qty, $materialId]);
            flash_set('success', 'Material recorded against project.');
        }
        redirect('admin/project_view.php?id=' . $id . '&tab=materials');
    }
}

/* ---------------- Data ---------------- */

// All personnel — pull names from users, role from specialty
$personnelAll = $pdo->query(
    "SELECT pn.id, u.full_name AS name, $roleSelect AS role, $typeSelect AS type
     FROM personnel pn
     LEFT JOIN users u ON u.id = pn.user_id
     ORDER BY u.full_name"
)->fetchAll();

$assignedIds = $pdo->prepare('SELECT personnel_id FROM project_personnel WHERE project_id = ?');
$assignedIds->execute([$id]);
$assigned = array_map(fn($r) => (int)$r['personnel_id'], $assignedIds->fetchAll());

$assignedRows = $pdo->prepare(
    "SELECT pp.*, u.full_name AS name, $roleSelect AS role, $typeSelect AS type
     FROM project_personnel pp
     JOIN personnel pn ON pn.id = pp.personnel_id
     LEFT JOIN users u ON u.id = pn.user_id
     WHERE pp.project_id = ?
     ORDER BY u.full_name"
);
$assignedRows->execute([$id]);
$team = $assignedRows->fetchAll();

$docs = $pdo->prepare('SELECT * FROM documents WHERE project_id = ? ORDER BY uploaded_at DESC');
$docs->execute([$id]);
$docList = $docs->fetchAll();

$materialsAll = $pdo->query('SELECT * FROM materials ORDER BY name')->fetchAll();
$projMaterials = $pdo->prepare('SELECT pm.*, m.name FROM project_materials pm JOIN materials m ON m.id = pm.material_id WHERE pm.project_id = ? ORDER BY pm.recorded_at DESC');
$projMaterials->execute([$id]);
$projMatList = $projMaterials->fetchAll();

$reqs = $pdo->prepare(
    'SELECT mr.*, m.name AS material_name, u.full_name AS requested_by_name FROM material_requests mr
     JOIN materials m ON m.id = mr.material_id LEFT JOIN users u ON u.id = mr.requested_by
     WHERE mr.project_id = ? ORDER BY mr.created_at DESC'
);
$reqs->execute([$id]);
$reqList = $reqs->fetchAll();

$photos = $pdo->prepare('SELECT ph.*, u.full_name AS uploader FROM project_photos ph LEFT JOIN users u ON u.id = ph.uploaded_by WHERE ph.project_id = ? ORDER BY ph.uploaded_at DESC');
$photos->execute([$id]);
$photoList = $photos->fetchAll();

$stages = project_stages($id);
$hist = $pdo->prepare(
    'SELECT ph.*, u.full_name AS reporter, s.name AS stage_name FROM progress_history ph
     LEFT JOIN users u ON u.id = ph.reported_by LEFT JOIN project_stages s ON s.id = ph.stage_id
     WHERE ph.project_id = ? ORDER BY ph.created_at DESC LIMIT 20'
);
$hist->execute([$id]);
$histList = $hist->fetchAll();

$reports = $pdo->prepare('SELECT sr.*, u.full_name AS reporter FROM site_reports sr LEFT JOIN users u ON u.id = sr.reported_by WHERE sr.project_id = ? ORDER BY sr.report_date DESC');
$reports->execute([$id]);
$reportList = $reports->fetchAll();

$quotes = $pdo->prepare('SELECT * FROM quotations WHERE project_id = ? ORDER BY issue_date DESC');
$quotes->execute([$id]);
$quoteList = $quotes->fetchAll();

$invList = $pdo->prepare('SELECT * FROM invoices WHERE project_id = ? ORDER BY issue_date DESC');
$invList->execute([$id]);
$invoices = $invList->fetchAll();

$status = effective_project_status($project);
$totalMatQty = array_sum(array_map(fn($r) => (float)$r['quantity'], $projMatList));
$spendVsBudget = $totalMatQty;
$invoicedTotal = array_sum(array_map(fn($i) => (float)$i['amount'], $invoices));

page_header('Project: ' . $project['name'], 'projects');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/projects.php"><i class="bi bi-arrow-left me-1"></i>All projects</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($project['name']) ?> <?= project_status_badge($status) ?></h4>
    <div class="text-muted small"><?= e($project['company_name']) ?> · <?= e($project['build_type'] ?? '-') ?> · <?= e($project['location'] ?? '') ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-brand btn-sm" href="<?= BASE_URL ?>/admin/project_controls.php?id=<?= $id ?>"><i class="bi bi-clipboard-data me-1"></i>Project controls</a>
    <a class="btn btn-outline-brand btn-sm" href="<?= BASE_URL ?>/admin/quotation_new.php?project_id=<?= $id ?>"><i class="bi bi-file-earmark-text me-1"></i>New quotation</a>
    <a class="btn btn-outline-brand btn-sm" href="<?= BASE_URL ?>/admin/invoice_new.php?project_id=<?= $id ?>"><i class="bi bi-receipt me-1"></i>New invoice</a>
    <?php if ($status === 'completed'): ?>
      <a class="btn btn-brand btn-sm" href="<?= BASE_URL ?>/admin/summary.php?id=<?= $id ?>"><i class="bi bi-file-earmark-bar-graph me-1"></i>Final summary</a>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs mt-3 mb-4">
  <?php
  $tabLabels = [
    'overview'   => ['Overview', 'bi-clipboard-data'],
    'controls'   => ['Controls & Reports', 'bi-clipboard-data'],
    'personnel'  => ['Personnel (' . count($team) . ')', 'bi-person-badge'],
    'documents'  => ['Documents (' . count($docList) . ')', 'bi-folder2-open'],
    'materials'  => ['Materials', 'bi-box-seam'],
    'photos'     => ['Photos (' . count($photoList) . ')', 'bi-camera'],
    'progress'   => ['Progress', 'bi-graph-up'],
    'quotations' => ['Quotations (' . count($quoteList) . ')', 'bi-file-earmark-text'],
    'invoices'   => ['Invoices (' . count($invoices) . ')', 'bi-receipt'],
    'summary'    => ['Final summary', 'bi-file-earmark-bar-graph'],
  ];
  foreach ($tabLabels as $key => [$label, $icon]): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= $key === 'controls' ? BASE_URL . '/admin/project_controls.php?id=' . $id : '?id=' . $id . '&tab=' . $key ?>"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-brand"></i>Project information</h6>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-muted">Client</dt><dd class="col-sm-8"><?= e($project['company_name']) ?> (<?= e($project['contact_person']) ?>)</dd>
          <dt class="col-sm-4 text-muted">Project manager</dt><dd class="col-sm-8"><?= e($project['pm_name'] ?? 'Not assigned') ?></dd>
          <dt class="col-sm-4 text-muted">Build type</dt><dd class="col-sm-8"><?= e($project['build_type'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Square metreage</dt><dd class="col-sm-8"><?= $project['square_meters'] ? number_format((float)$project['square_meters'], 2) . ' m²' : '-' ?></dd>
          <dt class="col-sm-4 text-muted">Location</dt><dd class="col-sm-8"><?= e($project['location'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Start date</dt><dd class="col-sm-8"><?= nice_date($project['start_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Target deadline</dt><dd class="col-sm-8"><?= nice_date($project['target_deadline']) ?></dd>
          <dt class="col-sm-4 text-muted">Actual completion</dt><dd class="col-sm-8"><?= nice_date($project['actual_completion_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Overall progress</dt>
          <dd class="col-sm-8">
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="max-width:220px"><div class="progress-bar" style="width:<?= (float)$project['progress_percent'] ?>%"></div></div>
              <strong><?= (float)$project['progress_percent'] ?>%</strong>
            </div>
          </dd>
        </dl>
        <?php if ($project['description']): ?><hr><p class="small mb-0 text-muted"><?= nl2br(e($project['description'])) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-wallet2 me-2 text-brand"></i>Budget</h6>
        <div class="row g-3 text-center">
          <div class="col-4"><div class="fw-bold fs-5"><?= money($project['budget']) ?></div><div class="small text-muted">Approved budget</div></div>
          <div class="col-4"><div class="fw-bold fs-5"><?= money($invoicedTotal) ?></div><div class="small text-muted">Invoiced to date</div></div>
          <div class="col-4"><div class="fw-bold fs-5 text-<?= ($invoicedTotal > $project['budget']) ? 'danger' : 'success' ?>"><?= money(max(0, $project['budget'] - $invoicedTotal)) ?></div><div class="small text-muted">Remaining</div></div>
        </div>
        <hr>
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="update_budget">
          <div class="col-md-5">
            <label class="form-label small">Update budget (R)</label>
            <input type="number" step="0.01" min="0" name="budget" class="form-control" value="<?= (float)$project['budget'] ?>" required>
            <div class="form-text">Negative values are not allowed.</div>
          </div>
          <div class="col-md-3"><button class="btn btn-outline-brand w-100">Save budget</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat me-2 text-brand"></i>Project status</h6>
        <form method="post" class="d-flex gap-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="update_status">
          <select name="status" class="form-select">
            <option value="not_yet_commenced" <?= $status === 'not_yet_commenced' ? 'selected' : '' ?>>Not yet commenced</option>
            <option value="active"            <?= $status === 'active'            ? 'selected' : '' ?>>In progress</option>
            <option value="delayed"           <?= $status === 'delayed'           ? 'selected' : '' ?>>Delayed</option>
            <option value="completed"         <?= $status === 'completed'         ? 'selected' : '' ?>>Completed</option>
          </select>
          <button class="btn btn-brand">Update</button>
        </form>
        <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Projects marked <em>In progress</em> past their deadline are auto-flagged as <strong>Delayed</strong>.</p>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-person-badge me-2 text-brand"></i>Team on this project</h6>
        <?php if (!$team): ?><p class="text-muted small">No personnel assigned yet.</p><?php endif; ?>
        <?php foreach ($team as $t): ?>
          <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
            <span class="small"><?= e($t['name'] ?: '—') ?> <?php if (!empty($t['role'])): ?><span class="text-muted">- <?= e($t['role']) ?></span><?php endif; ?></span>
            <?php if (($t['type'] ?? 'internal') === 'subcontractor'): ?>
              <?= badge('Subcontractor', 'info') ?>
            <?php else: ?>
              <?= badge('Internal', 'primary') ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <a class="small mt-2 d-inline-block" href="?id=<?= $id ?>&tab=personnel">Manage team <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-box-seam me-2 text-brand"></i>Materials used on this project</h6>
        <?php if (!$projMatList): ?><p class="text-muted small">No materials recorded yet.</p><?php endif; ?>
        <?php foreach ($projMatList as $m): ?>
          <div class="d-flex justify-content-between py-1 border-bottom small">
            <span><?= e($m['name']) ?></span><span><?= (float)$m['quantity'] ?> <?= e($m['unit']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'personnel'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-person-plus me-2 text-brand"></i>Assign personnel to project</h6>
        <?php if (!$personnelAll): ?>
          <p class="text-muted small">No personnel records yet. <a href="<?= BASE_URL ?>/admin/personnel_new.php">Create one first</a>.</p>
        <?php else: ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="assign_personnel">
          <div class="list-group mb-3">
            <?php foreach ($personnelAll as $p): ?>
              <label class="list-group-item d-flex gap-2 align-items-center small">
                <input class="form-check-input mt-0" type="checkbox" name="personnel_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $assigned, true) ? 'checked' : '' ?>>
                <span class="flex-grow-1"><?= e($p['name'] ?: '—') ?> <?php if (!empty($p['role'])): ?><span class="text-muted">- <?= e($p['role']) ?></span><?php endif; ?></span>
                <?php if (($p['type'] ?? 'internal') === 'subcontractor'): ?>
                  <?= badge('Sub', 'info') ?>
                <?php else: ?>
                  <?= badge('Int', 'primary') ?>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-brand w-100">Save assignment</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-brand"></i>Assigned team</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Name</th><th>Type</th><th>Role</th><th>Assigned</th></tr></thead>
          <tbody>
            <?php if (!$team): ?><tr><td colspan="4" class="text-muted small text-center py-4">No personnel assigned.</td></tr><?php endif; ?>
            <?php foreach ($team as $t): ?>
              <tr>
                <td class="fw-semibold"><?= e($t['name'] ?: '—') ?></td>
                <td><?= ($t['type'] ?? 'internal') === 'subcontractor' ? badge('Subcontractor', 'info') : badge('Internal', 'primary') ?></td>
                <td class="small"><?= e($t['role'] ?? '-') ?></td>
                <td class="small text-muted"><?= !empty($t['assigned_at']) ? nice_datetime($t['assigned_at']) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'documents'): ?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-upload me-2 text-brand"></i>Upload document</h6>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?><input type="hidden" name="action" value="upload_document">
          <div class="mb-3">
            <label class="form-label">Document name</label>
            <input type="text" name="doc_name" class="form-control" placeholder="e.g. Approved building plan">
          </div>
          <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="doc_category" class="form-select">
              <option value="plan">Building plan</option>
              <option value="quotation">Quotation</option>
              <option value="invoice">Invoice</option>
              <option value="permit">Permit</option>
              <option value="cost_report">Cost report</option>
              <option value="programme">Programme</option>
              <option value="commercial_dashboard">Commercial dashboard</option>
              <option value="meeting_minutes">Meeting minutes</option>
              <option value="activity_schedule">Activity schedule</option>
              <option value="material_tracking">Material tracking register</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">File</label>
            <input type="file" name="doc_file" class="form-control" required>
          </div>
          <button class="btn btn-brand w-100">Upload</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-folder2-open me-2 text-brand"></i>Project documents</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Name</th><th>Category</th><th>Uploaded</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <?php if (!$docList): ?><tr><td colspan="4" class="text-muted small text-center py-4">No documents yet.</td></tr><?php endif; ?>
            <?php foreach ($docList as $d): ?>
              <tr>
                <td><i class="bi bi-file-earmark me-1 text-brand"></i><?= e($d['name']) ?></td>
                <td><?= badge($d['category'], 'secondary') ?></td>
                <td class="small text-muted"><?= nice_datetime($d['uploaded_at']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/<?= e($d['file_path']) ?>" target="_blank"><i class="bi bi-download me-1"></i>Open</a>
                  <form method="post" class="d-inline" data-confirm="Delete this document?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_document"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'materials'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-box-arrow-in-down me-2 text-brand"></i>Record material issued to project</h6>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="record_material">
          <div class="mb-3">
            <label class="form-label">Material</label>
            <select name="material_id" class="form-select" required>
              <option value="">- select -</option>
              <?php foreach ($materialsAll as $m): ?>
                <option value="<?= $m['id'] ?>"><?= e($m['name']) ?> (stock: <?= (float)$m['stock_quantity'] ?> <?= e($m['unit']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
          </div>
          <button class="btn btn-brand w-100">Record against project</button>
        </form>
        <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Stock is reduced automatically. Use the <a href="<?= BASE_URL ?>/admin/material_requests.php">Material Requests</a> page to approve personnel requests first.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-brand"></i>Materials used on this project</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Material</th><th>Quantity</th><th>Recorded</th></tr></thead>
          <tbody>
            <?php if (!$projMatList): ?><tr><td colspan="3" class="text-muted small text-center py-4">No materials recorded.</td></tr><?php endif; ?>
            <?php foreach ($projMatList as $m): ?>
              <tr><td><?= e($m['name']) ?></td><td><?= (float)$m['quantity'] ?> <?= e($m['unit']) ?></td><td class="small text-muted"><?= nice_datetime($m['recorded_at']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-inbox me-2 text-brand"></i>Material requests for this project</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>#</th><th>Material</th><th>Qty</th><th>Requested by</th><th>Status</th><th class="text-end">Action</th></tr></thead>
          <tbody>
            <?php if (!$reqList): ?><tr><td colspan="6" class="text-muted small text-center py-4">No material requests.</td></tr><?php endif; ?>
            <?php foreach ($reqList as $r): ?>
              <tr>
                <td class="small text-muted">#<?= $r['id'] ?></td>
                <td><?= e($r['material_name']) ?></td>
                <td><?= (float)$r['quantity'] ?> <?= e($r['unit']) ?></td>
                <td class="small"><?= e($r['requested_by_name'] ?? '-') ?></td>
                <td><?= match ($r['status']) { 'approved' => badge('Approved', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending', 'warning') } ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/admin/material_requests.php?project_id=<?= $id ?>">Review</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'photos'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3 d-flex justify-content-between align-items-center">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-camera me-2 text-brand"></i>Progress photographs</h6>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/photos.php">Approval queue</a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php if (!$photoList): ?><p class="text-muted small mb-0">No photographs uploaded yet.</p><?php endif; ?>
      <?php foreach ($photoList as $ph): ?>
        <div class="col-md-4 col-lg-3">
          <div class="card h-100">
            <a href="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" target="_blank">
              <img src="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" class="card-img-top photo-thumb" alt="<?= e($ph['caption'] ?? '') ?>">
            </a>
            <div class="card-body p-2">
              <div class="small"><?= e($ph['caption'] ?? 'No caption') ?></div>
              <div class="small text-muted"><?= e($ph['uploader']) ?> · <?= nice_datetime($ph['uploaded_at']) ?></div>
              <div class="mt-1"><?= match ($ph['status']) { 'approved' => badge('Approved - visible to client', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending approval', 'warning') } ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'progress'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-2 text-brand"></i>Add construction stage</h6>
        <form method="post" class="d-flex gap-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_stage">
          <input type="text" name="stage_name" class="form-control" placeholder="e.g. Roofing" required>
          <button class="btn btn-brand text-nowrap">Add</button>
        </form>
      </div>
    </div>
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up me-2 text-brand"></i>Stage progress (overall = average)</h6>
        <?php foreach ($stages as $st): ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
              <span class="fw-semibold"><?= e($st['name']) ?></span><span><?= (float)$st['progress_percent'] ?>%</span>
            </div>
            <div class="progress mb-2"><div class="progress-bar" style="width:<?= (float)$st['progress_percent'] ?>%"></div></div>
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
          <span>Overall project progress</span><span class="text-brand"><?= (float)$project['progress_percent'] ?>%</span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-brand"></i>Progress history</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Stage</th><th>Change</th><th>Note</th><th>Who</th><th>When</th></tr></thead>
          <tbody>
            <?php if (!$histList): ?><tr><td colspan="5" class="text-muted small text-center py-4">No progress updates yet.</td></tr><?php endif; ?>
            <?php foreach ($histList as $h): ?>
              <tr>
                <td class="small"><?= e($h['stage_name'] ?? 'Overall') ?></td>
                <td><span class="badge text-bg-light"><?= (float)$h['old_percent'] ?>% - <?= (float)$h['new_percent'] ?>%</span></td>
                <td class="small"><?= e($h['note'] ?? '-') ?></td>
                <td class="small"><?= e($h['reporter'] ?? '-') ?></td>
                <td class="small text-muted"><?= nice_datetime($h['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-brand"></i>Site reports</h6></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Date</th><th>Period</th><th>Completed work</th><th>Delays</th><th>Reported by</th></tr></thead>
          <tbody>
            <?php if (!$reportList): ?><tr><td colspan="5" class="text-muted small text-center py-4">No site reports yet.</td></tr><?php endif; ?>
            <?php foreach ($reportList as $r): ?>
              <tr>
                <td class="small text-nowrap"><?= nice_date($r['report_date']) ?></td>
                <td><?= badge($r['period'], 'info') ?></td>
                <td class="small"><?= mb_strimwidth(e($r['completed_work'] ?? '-'), 0, 80, '…') ?></td>
                <td class="small"><?= $r['delays'] ? badge('Has delays', 'danger') : badge('None', 'success') ?></td>
                <td class="small"><?= e($r['reporter'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'quotations'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3 d-flex justify-content-between align-items-center">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2 text-brand"></i>Quotations for this project</h6>
    <a class="btn btn-brand btn-sm" href="<?= BASE_URL ?>/admin/quotation_new.php?project_id=<?= $id ?>"><i class="bi bi-plus-lg me-1"></i>New quotation</a>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Number</th><th>Issued</th><th>Valid until</th><th>Total</th><th>Status</th><th>Client response</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$quoteList): ?><tr><td colspan="7" class="text-muted small text-center py-4">No quotations yet.</td></tr><?php endif; ?>
        <?php foreach ($quoteList as $q):
            $totals = quotation_totals((int)$q['id']);
        ?>
          <tr>
            <td class="fw-semibold"><?= e($q['quotation_number']) ?></td>
            <td class="small"><?= nice_date($q['issue_date']) ?></td>
            <td class="small"><?= nice_date($q['valid_until']) ?></td>
            <td class="small"><?= money($totals['total']) ?></td>
            <td><?= quotation_status_badge(effective_quotation_status($q)) ?></td>
            <td class="small text-muted"><?= $q['client_response_at'] ? nice_datetime($q['client_response_at']) : '-' ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/admin/quotation_view.php?id=<?= $q['id'] ?>"><i class="bi bi-eye me-1"></i>View</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/admin/quotation_view.php?id=<?= $q['id'] ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'invoices'): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white rounded-top-4 py-3 d-flex justify-content-between align-items-center">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-brand"></i>Invoices for this project</h6>
    <a class="btn btn-brand btn-sm" href="<?= BASE_URL ?>/admin/invoice_new.php?project_id=<?= $id ?>"><i class="bi bi-plus-lg me-1"></i>New invoice</a>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Number</th><th>Issued</th><th>Due</th><th>Description</th><th>Amount</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$invoices): ?><tr><td colspan="7" class="text-muted small text-center py-4">No invoices yet.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td class="fw-semibold"><?= e($inv['invoice_number']) ?></td>
            <td class="small"><?= nice_date($inv['issue_date']) ?></td>
            <td class="small"><?= nice_date($inv['due_date']) ?></td>
            <td class="small"><?= e($inv['description'] ?? '-') ?></td>
            <td class="small"><?= money($inv['amount']) ?></td>
            <td><?= $inv['status'] === 'paid' ? badge('Paid', 'success') : badge('Issued', 'warning') ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/admin/invoice_view.php?id=<?= $inv['id'] ?>"><i class="bi bi-eye me-1"></i>View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'summary'): ?>
  <?php if ($status === 'completed'): ?>
    <div class="text-center py-5">
      <i class="bi bi-file-earmark-bar-graph display-4 text-brand"></i>
      <h5 class="mt-3">This project is complete</h5>
      <p class="text-muted small">The final project summary is assembled automatically from everything recorded during the project.</p>
      <a class="btn btn-brand" href="<?= BASE_URL ?>/admin/summary.php?id=<?= $id ?>"><i class="bi bi-eye me-1"></i>View final summary</a>
    </div>
  <?php else: ?>
    <div class="alert alert-info rounded-4 small"><i class="bi bi-info-circle me-2"></i>The final project summary is generated automatically once the project is marked as <strong>Completed</strong>. It will include progress history, delays, quotation negotiation history, materials used, invoices and more.</div>
  <?php endif; ?>
<?php endif; ?>

<?php page_footer(); ?>