<?php
/**
 * Client - view & respond to a quotation.
 * Two response options: Approve, or Request Re-evaluation and Negotiation Meeting.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('client');
$user = current_user();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// Ownership + link to project
$cstmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ?');
$cstmt->execute([$user['id']]);
$clientIds = array_map(fn($r) => (int)$r['id'], $cstmt->fetchAll());
if (!$clientIds) { http_response_code(403); die('<h3 style="font-family:sans-serif;text-align:center;margin-top:3rem">403 - No access</h3>'); }
$in = implode(',', array_fill(0, count($clientIds), '?'));

$stmt = $pdo->prepare(
    "SELECT q.*, p.name AS project_name, p.id AS project_id, c.company_name
     FROM quotations q
     JOIN projects p ON p.id = q.project_id
     JOIN clients c ON c.id = p.client_id
     WHERE q.id = ? AND p.client_id IN ($in)"
);
$stmt->execute(array_merge([$id], $clientIds));
$quote = $stmt->fetch();
if (!$quote) { flash_set('danger', 'Quotation not found.'); redirect('client/dashboard.php'); }

$totals = quotation_totals($id);

// PDF download
if (isset($_GET['pdf'])) {
    require_once __DIR__ . '/../includes/pdf.php';
    render_document_pdf($totals['items'], [
        'doc_label'   => 'QUOTATION',
        'doc_number'  => $quote['quotation_number'],
        'project_name'=> $quote['project_name'],
        'client_name' => $quote['company_name'] . ' - ' . $user['full_name'],
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

// Response handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $decision = $_POST['decision'] ?? '';
    $note = trim($_POST['note'] ?? '');
    if ($quote['status'] === 'sent' && in_array($decision, ['approve', 're_evaluation'], true)) {
        $pdo->prepare('UPDATE quotations SET status = ?, client_response_at = NOW(), client_response_note = ? WHERE id = ?')
            ->execute([$decision, $note ?: null, $id]);

        // Notify the Project Manager/admin
        $pmId = (int)$pdo->query('SELECT COALESCE(project_manager_id, 1) FROM projects WHERE id = ' . (int)$quote['project_id'])->fetchColumn();
        if ($decision === 'approve') {
            notify($pmId, 'Quotation approved by client', 'Quotation ' . $quote['quotation_number'] . ' was approved by ' . $user['full_name'] . '. The project is fit for commencement.', 'admin/quotation_view.php?id=' . $id);
        } else {
            notify($pmId, 'Quotation re-evaluation requested', $user['full_name'] . ' requested re-evaluation and a negotiation meeting for quotation ' . $quote['quotation_number'] . '.', 'admin/quotation_view.php?id=' . $id);
        }
        flash_set('success', $decision === 'approve'
            ? 'Thank you! You have approved the quotation. RG Trade and Projects will contact you to arrange the payment discussion and commencement.'
            : 'Your request for re-evaluation has been submitted. RG Trade and Projects will contact you to arrange a negotiation meeting.');
        redirect('client/quotation_view.php?id=' . $id);
    } else {
        flash_set('danger', 'This quotation can no longer be responded to.');
        redirect('client/quotation_view.php?id=' . $id);
    }
}

page_header('Quotation ' . $quote['quotation_number'], 'dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/client/project_view.php?id=<?= $quote['project_id'] ?>&tab=quotations"><i class="bi bi-arrow-left me-1"></i>Back to project</a>
    <h4 class="fw-semibold mb-0 mt-1"><?= e($quote['quotation_number']) ?> <?= quotation_status_badge($quote['status']) ?></h4>
    <div class="text-muted small"><?= e($quote['project_name']) ?> · Issued <?= nice_date($quote['issue_date']) ?> · Valid until <?= nice_date($quote['valid_until']) ?></div>
  </div>
  <a class="btn btn-outline-brand btn-sm" href="<?= BASE_URL ?>/client/quotation_view.php?id=<?= $id ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
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
        <tr><td colspan="4" class="text-end fw-semibold">Total</td><td class="text-end fw-bold fs-5"><?= money($totals['total']) ?></td></tr>
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
    <?php if ($quote['status'] === 'sent'): ?>
      <h6 class="fw-semibold mb-3"><i class="bi bi-pencil-square me-2 text-warning"></i>Your decision</h6>
      <p class="small text-muted mb-3">Please respond to this quotation. You may approve it, or request re-evaluation and a negotiation meeting.</p>

      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Comment / message (optional)</label>
          <textarea name="note" class="form-control" rows="3" placeholder="Anything you would like the team to know (e.g. terms to discuss)."></textarea>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" name="decision" value="approve" class="btn btn-success px-4" data-confirm="Approve this quotation and mark the project fit for commencement?">
            <i class="bi bi-check-circle me-2"></i>Approve quotation
          </button>
          <button type="submit" name="decision" value="re_evaluation" class="btn btn-outline-warning px-4" data-confirm="Request re-evaluation and a negotiation meeting?">
            <i class="bi bi-arrow-repeat me-2"></i>Request re-evaluation &amp; negotiation meeting
          </button>
        </div>
        <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Approving the quotation marks the project as fit for commencement. Payment arrangements are discussed with RG Trade and Projects separately.</p>
      </form>
    <?php elseif ($quote['status'] === 'approved'): ?>
      <div class="alert alert-success rounded-3 small mb-0">
        <i class="bi bi-check-circle me-2"></i>You approved this quotation on <strong><?= nice_datetime($quote['client_response_at']) ?></strong>.
        <?php if ($quote['client_response_note']): ?><div class="mt-1">Your comment: “<?= e($quote['client_response_note']) ?>”</div><?php endif; ?>
        <div class="mt-2">The project is fit for commencement. RG Trade and Projects will arrange the payment discussion with you.</div>
      </div>
    <?php elseif ($quote['status'] === 're_evaluation'): ?>
      <div class="alert alert-warning rounded-3 small mb-0">
        <i class="bi bi-arrow-repeat me-2"></i>You requested <strong>re-evaluation and a negotiation meeting</strong> on <strong><?= nice_datetime($quote['client_response_at']) ?></strong>.
        <?php if ($quote['client_response_note']): ?><div class="mt-1">Your comment: “<?= e($quote['client_response_note']) ?>”</div><?php endif; ?>
        <div class="mt-2">RG Trade and Projects will be in touch to schedule the meeting. A revised quotation may follow.</div>
      </div>
    <?php else: ?>
      <p class="text-muted small mb-0">This quotation has not been sent for your response yet.</p>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
