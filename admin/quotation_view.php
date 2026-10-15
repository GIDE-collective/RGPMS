<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare(
    'SELECT q.*, p.name AS project_name, p.id AS project_id, c.company_name, c.contact_person, c.address
     FROM quotations q
     JOIN projects p ON p.id = q.project_id
     JOIN clients c ON c.id = p.client_id
     WHERE q.id = ?'
);
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) { flash_set('danger', 'Quotation not found.'); redirect('admin/quotations.php'); }

$totals = quotation_totals($id);

// PDF download
if (isset($_GET['pdf'])) {
    require_once __DIR__ . '/../includes/pdf.php';
    render_document_pdf($totals['items'], [
        'doc_label'   => 'QUOTATION',
        'doc_number'  => $quote['quotation_number'],
        'project_name'=> $quote['project_name'],
        'client_name' => $quote['company_name'] . ' - ' . $quote['contact_person'],
        'issue_date'  => $quote['issue_date'],
        'valid_until' => $quote['valid_until'],
        'status'      => strtoupper(str_replace('_', ' ', $quote['status'])),
        'notes'       => $quote['notes'],
        'vat_rate'    => $totals['vat_rate'],
        'subtotal'    => $totals['subtotal'],
        'vat'         => $totals['vat'],
        'total'       => $totals['total'],
    ]);
}

page_header('Quotation ' . $quote['quotation_number'], 'quotations');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/quotations.php"><i class="bi bi-arrow-left me-1"></i>All quotations</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($quote['quotation_number']) ?> <?= quotation_status_badge($quote['status']) ?></h4>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-brand btn-sm" href="<?= BASE_URL ?>/admin/quotation_view.php?id=<?= $id ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $quote['project_id'] ?>&tab=quotations">Project</a>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between gap-3">
    <div>
      <div class="small text-muted">Project</div>
      <div class="fw-semibold"><?= e($quote['project_name']) ?></div>
      <div class="small text-muted"><?= e($quote['company_name']) ?> · <?= e($quote['contact_person']) ?></div>
    </div>
    <div>
      <div class="small text-muted">Issue date</div><div class="fw-semibold"><?= nice_date($quote['issue_date']) ?></div>
    </div>
    <div>
      <div class="small text-muted">Valid until</div><div class="fw-semibold"><?= nice_date($quote['valid_until']) ?></div>
    </div>
    <div>
      <div class="small text-muted">Total (incl. VAT)</div><div class="fw-bold fs-5 text-warning"><?= money($totals['total']) ?></div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-center">Unit</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($totals['items'] as $item): ?>
        <tr>
          <td><?= e($item['description']) ?></td>
          <td class="text-end"><?= number_format((float)$item['quantity'], 2) ?></td>
          <td class="text-center"><?= e($item['unit']) ?></td>
          <td class="text-end"><?= money($item['rate']) ?></td>
          <td class="text-end"><?= money($item['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="4" class="text-end small text-muted">Subtotal</td><td class="text-end"><?= money($totals['subtotal']) ?></td></tr>
        <tr><td colspan="4" class="text-end small text-muted">VAT (<?= rtrim(rtrim(number_format($totals['vat_rate'], 2), '0'), '.') ?>%)</td><td class="text-end"><?= money($totals['vat']) ?></td></tr>
        <tr><td colspan="4" class="text-end fw-semibold">Total</td><td class="text-end fw-bold"><?= money($totals['total']) ?></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if ($quote['notes']): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
  <div class="card-body p-4"><h6 class="fw-semibold mb-2"><i class="bi bi-sticky me-2 text-warning"></i>Notes / terms</h6><p class="small mb-0"><?= nl2br(e($quote['notes'])) ?></p></div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-body p-4">
    <h6 class="fw-semibold mb-3"><i class="bi bi-chat-square-text me-2 text-warning"></i>Client response</h6>
    <?php if ($quote['status'] === 'sent'): ?>
      <p class="text-muted small mb-0"><i class="bi bi-hourglass-split me-1"></i>Sent to the client - awaiting their response (approve or request re-evaluation).</p>
    <?php elseif ($quote['status'] === 'approved'): ?>
      <div class="alert alert-success rounded-3 small mb-0">
        <i class="bi bi-check-circle me-2"></i>Approved by the client on <strong><?= nice_datetime($quote['client_response_at']) ?></strong>.
        This project is now <strong>fit for commencement</strong>. Payment discussion happens with RG Trade and Projects outside the system.
        <?php if ($quote['client_response_note']): ?><div class="mt-1">Client note: “<?= e($quote['client_response_note']) ?>”</div><?php endif; ?>
      </div>
    <?php elseif ($quote['status'] === 're_evaluation'): ?>
      <div class="alert alert-warning rounded-3 small mb-0">
        <i class="bi bi-arrow-repeat me-2"></i>The client requested <strong>re-evaluation and a negotiation meeting</strong> on <strong><?= nice_datetime($quote['client_response_at']) ?></strong>.
        <?php if ($quote['client_response_note']): ?><div class="mt-1">Client note: “<?= e($quote['client_response_note']) ?>”</div><?php endif; ?>
        <div class="mt-2"><a class="btn btn-sm btn-brand" href="<?= BASE_URL ?>/admin/quotation_new.php?project_id=<?= $quote['project_id'] ?>"><i class="bi bi-plus-lg me-1"></i>Prepare revised quotation</a></div>
      </div>
    <?php else: ?>
      <p class="text-muted small mb-0">This quotation is still a draft. Send it to the client when it is final.</p>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
