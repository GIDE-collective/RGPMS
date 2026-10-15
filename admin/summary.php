<?php
/**
 * Admin - Final Project Summary.
 * Automatically generated at project completion from the information captured
 * throughout the project lifecycle (per the agreed client requirement #16).
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT p.*, c.company_name, c.contact_person, u.full_name AS pm_name
     FROM projects p JOIN clients c ON c.id = p.client_id LEFT JOIN users u ON u.id = p.project_manager_id WHERE p.id = ?'
);
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) { flash_set('danger', 'Project not found.'); redirect('admin/projects.php'); }

$status = effective_project_status($project);
if ($status !== 'completed') {
    flash_set('warning', 'The final summary becomes available once the project is marked as completed.');
    redirect('admin/project_view.php?id=' . $id);
}

// --- Assemble summary data ---
$quotes = $pdo->prepare('SELECT * FROM quotations WHERE project_id = ? ORDER BY issue_date');
$quotes->execute([$id]);
$quoteHistory = $quotes->fetchAll();

$hist = $pdo->prepare(
    'SELECT ph.*, u.full_name AS reporter, s.name AS stage_name FROM progress_history ph
     LEFT JOIN users u ON u.id = ph.reported_by LEFT JOIN project_stages s ON s.id = ph.stage_id
     WHERE ph.project_id = ? ORDER BY ph.created_at'
);
$hist->execute([$id]);
$progressHistory = $hist->fetchAll();

$reports = $pdo->prepare(
    'SELECT sr.*, u.full_name AS reporter FROM site_reports sr LEFT JOIN users u ON u.id = sr.reported_by WHERE sr.project_id = ? ORDER BY sr.report_date'
);
$reports->execute([$id]);
$reportList = $reports->fetchAll();
$delayNotes = array_filter($reportList, fn($r) => !empty($r['delays']));

$team = $pdo->prepare(
    'SELECT pn.name, pn.type, pn.role FROM project_personnel pp JOIN personnel pn ON pn.id = pp.personnel_id WHERE pp.project_id = ? ORDER BY pn.name'
);
$team->execute([$id]);
$teamList = $team->fetchAll();

$mats = $pdo->prepare(
    'SELECT pm.*, m.name FROM project_materials pm JOIN materials m ON m.id = pm.material_id WHERE pm.project_id = ? ORDER BY pm.recorded_at'
);
$mats->execute([$id]);
$matList = $mats->fetchAll();

$inv = $pdo->prepare('SELECT * FROM invoices WHERE project_id = ? ORDER BY issue_date');
$inv->execute([$id]);
$invList = $inv->fetchAll();
$invoicedTotal = array_sum(array_map(fn($i) => (float)$i['amount'], $invList));
$paidTotal = array_sum(array_map(fn($i) => (float)$i['amount'], array_filter($invList, fn($i) => $i['status'] === 'paid')));

$photos = $pdo->prepare('SELECT COUNT(*) FROM project_photos WHERE project_id = ? AND status = "approved"');
$photos->execute([$id]);
$approvedPhotoCount = (int)$photos->fetchColumn();

$durations = $pdo->prepare('SELECT COUNT(*) FROM site_reports WHERE project_id = ?');
$durations->execute([$id]);
$reportCount = (int)$durations->fetchColumn();

$start = $project['start_date'] ? strtotime($project['start_date']) : null;
$end   = $project['actual_completion_date'] ? strtotime($project['actual_completion_date']) : null;
$durationDays = ($start && $end) ? (int)(($end - $start) / 86400) : null;
$onTime = ($start && $end && $project['target_deadline'] && $end <= strtotime($project['target_deadline']));

// Negotiation history from quotations that were re-evaluated
$negotiations = array_values(array_filter($quoteHistory, fn($q) => $q['status'] === 're_evaluation'));

page_header('Final summary: ' . $project['name'], 'projects');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $id ?>"><i class="bi bi-arrow-left me-1"></i>Back to project</a>
    <h4 class="fw-semibold mb-0 mt-1"><i class="bi bi-file-earmark-bar-graph text-warning me-2"></i>Final project summary</h4>
    <div class="text-muted small">Auto-generated on <?= nice_datetime(date('Y-m-d H:i:s')) ?> from data captured during the project · <?= e($project['name']) ?></div>
  </div>
  <button class="btn btn-outline-brand btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / save PDF</button>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-clipboard-data me-2 text-warning"></i>Project overview</h6>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-muted">Client</dt><dd class="col-sm-8"><?= e($project['company_name']) ?> - <?= e($project['contact_person']) ?></dd>
          <dt class="col-sm-4 text-muted">Build type</dt><dd class="col-sm-8"><?= e($project['build_type'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Location</dt><dd class="col-sm-8"><?= e($project['location'] ?? '-') ?></dd>
          <dt class="col-sm-4 text-muted">Square metreage</dt><dd class="col-sm-8"><?= $project['square_meters'] ? number_format((float)$project['square_meters'], 2) . ' m²' : '-' ?></dd>
          <dt class="col-sm-4 text-muted">Start date</dt><dd class="col-sm-8"><?= nice_date($project['start_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Target deadline</dt><dd class="col-sm-8"><?= nice_date($project['target_deadline']) ?></dd>
          <dt class="col-sm-4 text-muted">Actual completion</dt><dd class="col-sm-8"><?= nice_date($project['actual_completion_date']) ?></dd>
          <dt class="col-sm-4 text-muted">Duration</dt>
          <dd class="col-sm-8"><?= $durationDays ? $durationDays . ' days (' . ($onTime ? 'on schedule' : 'beyond target') . ')' : '-' ?></dd>
          <dt class="col-sm-4 text-muted">Final progress</dt><dd class="col-sm-8"><span class="badge text-bg-success">100% - Completed</span></dd>
          <dt class="col-sm-4 text-muted">Project manager</dt><dd class="col-sm-8"><?= e($project['pm_name'] ?? '-') ?></dd>
        </dl>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-file-earmark-text me-2 text-warning"></i>Quotation &amp; negotiation history</h6>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Quotation</th><th>Issued</th><th>Amount (excl.)</th><th>Outcome</th><th>Response date</th></tr></thead>
          <tbody>
            <?php foreach ($quoteHistory as $q):
                $t = quotation_totals((int)$q['id']);
            ?>
            <tr>
              <td class="fw-semibold"><?= e($q['quotation_number']) ?></td>
              <td class="small"><?= nice_date($q['issue_date']) ?></td>
              <td class="small"><?= money($t['total']) ?></td>
              <td><?= quotation_status_badge($q['status']) ?></td>
              <td class="small text-muted"><?= nice_datetime($q['client_response_at']) ?></td>
            </tr>
            <?php if ($q['client_response_note']): ?>
              <tr><td colspan="5" class="small text-muted ps-4"><i class="bi bi-chat-left-quote me-1"></i><?= e($q['client_response_note']) ?></td></tr>
            <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($negotiations): ?>
          <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-arrow-repeat me-1"></i>This project went through <strong><?= count($negotiations) ?></strong> re-evaluation / negotiation round(s) before agreement.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up me-2 text-warning"></i>Progress history</h6>
        <?php if (!$progressHistory): ?><p class="text-muted small mb-0">No progress history recorded.</p><?php endif; ?>
        <div class="timeline small">
          <?php foreach ($progressHistory as $h): ?>
            <div class="d-flex gap-3 py-2 border-bottom">
              <span class="badge text-bg-light text-nowrap"><?= (float)$h['old_percent'] ?>% - <?= (float)$h['new_percent'] ?>%</span>
              <div>
                <div><strong><?= e($h['stage_name'] ?? 'Overall') ?></strong><?= $h['note'] ? ' - ' . e($h['note']) : '' ?></div>
                <div class="text-muted"><?= e($h['reporter'] ?? '-') ?> · <?= nice_datetime($h['created_at']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Delays recorded during the project</h6>
        <?php if (!$delayNotes): ?>
          <p class="text-muted small mb-0"><i class="bi bi-check-circle text-success me-1"></i>No delays were reported on site.</p>
        <?php endif; ?>
        <?php foreach ($delayNotes as $r): ?>
          <div class="d-flex gap-3 py-2 border-bottom">
            <span class="small text-nowrap text-muted"><?= nice_date($r['report_date']) ?></span>
            <div>
              <div class="small"><?= e($r['delays']) ?></div>
              <div class="text-muted small">Reported by <?= e($r['reporter'] ?? '-') ?> (<?= e($r['period']) ?> report)</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-box-seam me-2 text-warning"></i>Materials used</h6>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Material</th><th>Quantity</th><th>Recorded</th></tr></thead>
          <tbody>
            <?php foreach ($matList as $m): ?>
              <tr><td><?= e($m['name']) ?></td><td><?= (float)$m['quantity'] ?> <?= e($m['unit']) ?></td><td class="small text-muted"><?= nice_date($m['recorded_at']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$matList): ?><tr><td colspan="3" class="text-muted small">No materials recorded.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-receipt me-2 text-warning"></i>Invoices</h6>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Invoice</th><th>Issued</th><th>Description</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($invList as $i): ?>
              <tr>
                <td class="fw-semibold"><?= e($i['invoice_number']) ?></td>
                <td class="small"><?= nice_date($i['issue_date']) ?></td>
                <td class="small"><?= e($i['description'] ?? '-') ?></td>
                <td class="text-end small"><?= money($i['amount']) ?></td>
                <td><?= $i['status'] === 'paid' ? badge('Paid', 'success') : badge('Issued', 'warning') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$invList): ?><tr><td colspan="5" class="text-muted small">No invoices issued.</td></tr><?php endif; ?>
            <tr class="table-light">
              <td colspan="3" class="fw-semibold">Totals</td>
              <td class="text-end fw-semibold"><?= money($invoicedTotal) ?></td>
              <td class="small text-muted">Paid: <?= money($paidTotal) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4 text-center">
        <div class="display-6 fw-bold text-success"><i class="bi bi-check-circle-fill"></i></div>
        <h5 class="mt-2 mb-1">Project completed</h5>
        <div class="small text-muted"><?= nice_date($project['actual_completion_date']) ?></div>
        <hr>
        <div class="row text-center small">
          <div class="col-6"><div class="fw-bold fs-5"><?= $durationDays ?? '-' ?></div><div class="text-muted">Days on site</div></div>
          <div class="col-6"><div class="fw-bold fs-5"><?= $reportCount ?></div><div class="text-muted">Site reports</div></div>
          <div class="col-6 mt-3"><div class="fw-bold fs-5"><?= $approvedPhotoCount ?></div><div class="text-muted">Approved photos</div></div>
          <div class="col-6 mt-3"><div class="fw-bold fs-5"><?= count($teamList) ?></div><div class="text-muted">Team members</div></div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-people me-2 text-warning"></i>Personnel involved</h6>
        <?php foreach ($teamList as $t): ?>
          <div class="d-flex justify-content-between py-1 border-bottom small">
            <span><?= e($t['name']) ?> <span class="text-muted">- <?= e($t['role'] ?? '-') ?></span></span>
            <?= $t['type'] === 'subcontractor' ? badge('Sub', 'info') : badge('Int', 'primary') ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$teamList): ?><p class="text-muted small mb-0">No personnel recorded.</p><?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-wallet2 me-2 text-warning"></i>Financial position</h6>
        <div class="d-flex justify-content-between small py-1"><span class="text-muted">Approved budget</span><span><?= money($project['budget']) ?></span></div>
        <div class="d-flex justify-content-between small py-1"><span class="text-muted">Invoiced</span><span><?= money($invoicedTotal) ?></span></div>
        <div class="d-flex justify-content-between small py-1"><span class="text-muted">Paid by client</span><span><?= money($paidTotal) ?></span></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between small fw-semibold"><span>Outstanding</span><span class="<?= ($invoicedTotal - $paidTotal) > 0 ? 'text-danger' : 'text-success' ?>"><?= money(max(0, $invoicedTotal - $paidTotal)) ?></span></div>
      </div>
    </div>
  </div>
</div>

<style>@media print { .navbar, footer, .btn, .small a { display: none !important; } }</style>
<?php page_footer(); ?>
