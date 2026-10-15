<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare(
    'SELECT i.*, p.name AS project_name, p.id AS project_id, c.company_name, c.contact_person, c.address
     FROM invoices i JOIN projects p ON p.id = i.project_id JOIN clients c ON c.id = p.client_id WHERE i.id = ?'
);
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { flash_set('danger', 'Invoice not found.'); redirect('admin/invoices.php'); }

if (isset($_GET['pdf'])) {
    require_once __DIR__ . '/../includes/pdf.php';
    render_document_pdf([
        ['description' => $inv['description'] ?? 'Invoice for project works', 'quantity' => 1, 'unit' => 'lump sum', 'rate' => $inv['amount'], 'amount' => $inv['amount']],
    ], [
        'doc_label'   => 'INVOICE',
        'doc_number'  => $inv['invoice_number'],
        'project_name'=> $inv['project_name'],
        'client_name' => $inv['company_name'] . ' - ' . $inv['contact_person'],
        'issue_date'  => $inv['issue_date'],
        'due_date'    => $inv['due_date'],
        'status'      => strtoupper($inv['status']),
        'notes'       => 'Invoice amount includes VAT where applicable.',
        'vat_rate'    => 0,
        'subtotal'    => $inv['amount'],
        'vat'         => 0,
        'total'       => $inv['amount'],
    ]);
}

// Mark paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    csrf_check();
    $pdo->prepare('UPDATE invoices SET status = "paid" WHERE id = ?')->execute([$id]);
    notify_client_of_project($inv['project_id'], 'Invoice paid', 'Invoice ' . $inv['invoice_number'] . ' has been marked as paid. Thank you!', 'client/invoice_view.php?id=' . $id);
    flash_set('success', 'Invoice marked as paid.');
    redirect('admin/invoice_view.php?id=' . $id);
}

page_header('Invoice ' . $inv['invoice_number'], 'invoices');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/invoices.php"><i class="bi bi-arrow-left me-1"></i>All invoices</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($inv['invoice_number']) ?> <?= $inv['status'] === 'paid' ? badge('Paid', 'success') : badge('Issued', 'warning') ?></h4>
  </div>
  <div class="d-flex gap-2">
    <?php if ($inv['status'] !== 'paid'): ?>
      <form method="post" class="d-inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="mark_paid">
        <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Mark as paid</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-outline-brand btn-sm" href="<?= BASE_URL ?>/admin/invoice_view.php?id=<?= $id ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $inv['project_id'] ?>&tab=invoices">Project</a>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-body p-4">
    <div class="row">
      <div class="col-md-6">
        <div class="small text-muted">Billed to</div>
        <div class="fw-semibold"><?= e($inv['company_name']) ?></div>
        <div class="small"><?= e($inv['contact_person']) ?></div>
        <div class="small text-muted"><?= e($inv['address'] ?? '') ?></div>
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
        <div class="small text-muted">Status: <?= e($inv['status']) ?></div>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
