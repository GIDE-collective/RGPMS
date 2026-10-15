<?php
/**
 * Client - invoice view + PDF download.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('client');
$user = current_user();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$cstmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ?');
$cstmt->execute([$user['id']]);
$clientIds = array_map(fn($r) => (int)$r['id'], $cstmt->fetchAll());
if (!$clientIds) { http_response_code(403); die('<h3 style="font-family:sans-serif;text-align:center;margin-top:3rem">403 - No access</h3>'); }
$in = implode(',', array_fill(0, count($clientIds), '?'));

$stmt = $pdo->prepare(
    "SELECT i.*, p.name AS project_name, p.id AS project_id, c.company_name
     FROM invoices i JOIN projects p ON p.id = i.project_id JOIN clients c ON c.id = p.client_id
     WHERE i.id = ? AND p.client_id IN ($in)"
);
$stmt->execute(array_merge([$id], $clientIds));
$inv = $stmt->fetch();
if (!$inv) { flash_set('danger', 'Invoice not found.'); redirect('client/dashboard.php'); }

if (isset($_GET['pdf'])) {
    require_once __DIR__ . '/../includes/pdf.php';
    render_document_pdf([
        ['description' => $inv['description'] ?? 'Invoice for project works', 'quantity' => 1, 'unit' => 'lump sum', 'rate' => $inv['amount'], 'amount' => $inv['amount']],
    ], [
        'doc_label'   => 'INVOICE',
        'doc_number'  => $inv['invoice_number'],
        'project_name'=> $inv['project_name'],
        'client_name' => $inv['company_name'] . ' - ' . $user['full_name'],
        'issue_date'  => $inv['issue_date'],
        'due_date'    => $inv['due_date'],
        'status'      => strtoupper($inv['status']),
        'notes'       => 'Payment can be arranged with RG Trade and Projects. Bank details are provided separately.',
        'vat_rate'    => 0,
        'subtotal'    => $inv['amount'],
        'vat'         => 0,
        'total'       => $inv['amount'],
    ]);
}

page_header('Invoice ' . $inv['invoice_number'], 'dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/client/project_view.php?id=<?= $inv['project_id'] ?>&tab=invoices"><i class="bi bi-arrow-left me-1"></i>Back to project</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($inv['invoice_number']) ?> <?= $inv['status'] === 'paid' ? badge('Paid', 'success') : badge('Issued', 'warning') ?></h4>
  </div>
  <a class="btn btn-outline-brand btn-sm" href="<?= BASE_URL ?>/client/invoice_view.php?id=<?= $id ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
</div>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-body p-4">
    <div class="row">
      <div class="col-md-6">
        <div class="small text-muted">Billed to</div>
        <div class="fw-semibold"><?= e($inv['company_name']) ?></div>
        <div class="small"><?= e($user['full_name']) ?></div>
      </div>
      <div class="col-md-6 text-md-end">
        <div class="small text-muted">Project</div>
        <div class="fw-semibold"><?= e($inv['project_name']) ?></div>
        <div class="small text-muted">Issued: <?= nice_date($inv['issue_date']) ?></div>
        <div class="small text-muted">Due: <?= nice_date($inv['due_date']) ?></div>
      </div>
    </div>
    <hr>
    <div class="row">
      <div class="col-md-8">
        <div class="small text-muted">Description</div>
        <div><?= e($inv['description'] ?? '-') ?></div>
      </div>
      <div class="col-md-4 text-md-end">
        <div class="small text-muted">Amount (incl. VAT)</div>
        <div class="fw-bold fs-4 text-warning"><?= money($inv['amount']) ?></div>
      </div>
    </div>
    <hr>
    <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>For payment arrangements, please contact <?= APP_NAME ?> at <?= e(COMPANY_PHONE) ?> / <?= e(COMPANY_EMAIL) ?>.</p>
  </div>
</div>
<?php page_footer(); ?>
