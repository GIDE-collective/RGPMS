<?php
/**
 * Admin - create a quotation with line items, quantities, rates and VAT.
 * Quotation can be saved as draft or sent to the client immediately.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();
$projects = $pdo->query(
    'SELECT p.id, p.name, c.company_name FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC'
)->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $projectId   = (int)($_POST['project_id'] ?? 0);
    $issueDate   = $_POST['issue_date'] ?? date('Y-m-d');
    $validUntil  = $_POST['valid_until'] ?? '';
    $vatRate     = (float)($_POST['vat_rate'] ?? 15);
    $notes       = trim($_POST['notes'] ?? '');
    $sendNow     = isset($_POST['send_now']);
    $descs       = $_POST['desc'] ?? [];
    $qties       = $_POST['qty'] ?? [];
    $units       = $_POST['unit'] ?? [];
    $rates       = $_POST['rate'] ?? [];

    if ($projectId <= 0) $errors[] = 'Select a project.';
    if (empty($descs)) $errors[] = 'Add at least one line item.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $number = next_number('RGQ-' . date('Y'), 'quotations', 'quotation_number');
            $ins = $pdo->prepare(
                'INSERT INTO quotations (project_id, quotation_number, issue_date, valid_until, notes, vat_rate, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $status = $sendNow ? 'sent' : 'draft';
            $ins->execute([$projectId, $number, $issueDate, $validUntil ?: null, $notes, $vatRate, $status, $user['id']]);
            $qid = (int)$pdo->lastInsertId();

            $insI = $pdo->prepare('INSERT INTO quotation_items (quotation_id, description, quantity, unit, rate, amount) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($descs as $i => $desc) {
                $desc = trim((string)$desc);
                if ($desc === '') continue;
                $qty  = (float)($qties[$i] ?? 1);
                $unit = trim((string)($units[$i] ?? ''));
                $rate = (float)($rates[$i] ?? 0);
                $insI->execute([$qid, $desc, $qty ?: 1, $unit, $rate, $qty * $rate]);
            }
            $pdo->commit();

            if ($sendNow) {
                notify_client_of_project($projectId, 'New quotation ready for review',
                    'Quotation ' . $number . ' has been issued for your project. Please approve it or request re-evaluation.',
                    'client/quotation_view.php?id=' . $qid);
                flash_set('success', 'Quotation ' . $number . ' created and sent to the client.');
            } else {
                flash_set('success', 'Quotation ' . $number . ' saved as draft.');
            }
            redirect('admin/quotation_view.php?id=' . $qid);
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Could not save the quotation.';
        }
    }
}

page_header('New quotation', 'quotations');
page_heading('New quotation', 'Prepare line items, quantities and rates');
?>
<div class="card border-0 shadow-sm rounded-4" style="max-width:960px">
  <div class="card-body p-4">
    <?php if ($errors): ?><div class="alert alert-danger small py-2"><?php foreach ($errors as $er): ?><div><i class="bi bi-x-circle me-1"></i><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post" id="quoteForm">
      <?= csrf_field() ?>
      <div class="row">
        <div class="col-md-5 mb-3">
          <label class="form-label required">Project</label>
          <select name="project_id" class="form-select" required>
            <option value="">- select project -</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>" <?= ((int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['company_name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label required">Issue date</label>
          <input type="date" name="issue_date" class="form-control" required value="<?= e($_POST['issue_date'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-2 mb-3">
          <label class="form-label">Valid until</label>
          <input type="date" name="valid_until" class="form-control" value="<?= e($_POST['valid_until'] ?? '') ?>">
        </div>
        <div class="col-md-2 mb-3">
          <label class="form-label">VAT %</label>
          <input type="number" step="0.01" name="vat_rate" class="form-control" value="<?= e($_POST['vat_rate'] ?? '15.00') ?>">
        </div>
      </div>

      <label class="form-label fw-semibold">Line items</label>
      <div class="table-responsive">
        <table class="table align-middle" id="itemsTable">
          <thead><tr><th style="min-width:260px">Description</th><th style="width:90px">Quantity</th><th style="width:110px">Unit</th><th style="width:130px">Rate (R)</th><th style="width:130px">Amount</th><th style="width:44px"></th></tr></thead>
          <tbody>
            <?php if (empty($_POST['desc'])): ?>
            <tr class="item-row">
              <td><input type="text" name="desc[]" class="form-control form-control-sm" placeholder="e.g. Excavation and foundations"></td>
              <td><input type="number" name="qty[]" class="form-control form-control-sm qty" min="0" step="0.01" value="1"></td>
              <td><input type="text" name="unit[]" class="form-control form-control-sm" placeholder="m2 / lump sum"></td>
              <td><input type="number" name="rate[]" class="form-control form-control-sm rate" min="0" step="0.01" value="0"></td>
              <td class="amount-cell small text-muted">R 0.00</td>
              <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
            </tr>
            <?php else: foreach ($_POST['desc'] as $i => $desc): ?>
            <tr class="item-row">
              <td><input type="text" name="desc[]" class="form-control form-control-sm" value="<?= e($desc) ?>"></td>
              <td><input type="number" name="qty[]" class="form-control form-control-sm qty" min="0" step="0.01" value="<?= e($_POST['qty'][$i] ?? 1) ?>"></td>
              <td><input type="text" name="unit[]" class="form-control form-control-sm" value="<?= e($_POST['unit'][$i] ?? '') ?>"></td>
              <td><input type="number" name="rate[]" class="form-control form-control-sm rate" min="0" step="0.01" value="<?= e($_POST['rate'][$i] ?? 0) ?>"></td>
              <td class="amount-cell small text-muted">R 0.00</td>
              <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="addRow"><i class="bi bi-plus-lg me-1"></i>Add line item</button>

      <div class="row justify-content-end g-2 mb-4">
        <div class="col-md-3 text-end">
          <div class="small text-muted">Subtotal</div><div class="fw-bold" id="subtotalLabel">R 0.00</div>
          <div class="small text-muted">VAT (15.00%)</div><div class="fw-bold" id="vatLabel">R 0.00</div>
          <hr class="my-1">
          <div class="small fw-semibold">Total</div><div class="fw-bold fs-5" id="totalLabel">R 0.00</div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Notes / terms</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-brand" name="send_now" value="1"><i class="bi bi-send me-1"></i>Save &amp; send to client</button>
        <button class="btn btn-outline-secondary" name="send_now" value="0"><i class="bi bi-save me-1"></i>Save as draft</button>
        <a class="btn btn-outline-secondary ms-auto" href="<?= BASE_URL ?>/admin/quotations.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var tbody = document.querySelector('#itemsTable tbody');

  function recalc() {
    var sub = 0;
    tbody.querySelectorAll('.item-row').forEach(function (row) {
      var qty = parseFloat(row.querySelector('.qty').value) || 0;
      var rate = parseFloat(row.querySelector('.rate').value) || 0;
      var amt = qty * rate;
      row.querySelector('.amount-cell').textContent = 'R ' + amt.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      sub += amt;
    });
    var vatRate = parseFloat(document.querySelector('[name=vat_rate]').value) || 0;
    var vat = sub * vatRate / 100;
    var fmt = function (n) { return 'R ' + n.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
    document.getElementById('subtotalLabel').textContent = fmt(sub);
    document.getElementById('vatLabel').textContent = fmt(vat);
    document.getElementById('totalLabel').textContent = fmt(sub + vat);
  }

  document.getElementById('addRow').addEventListener('click', function () {
    var tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML =
      '<td><input type="text" name="desc[]" class="form-control form-control-sm" placeholder="e.g. Roofing"></td>' +
      '<td><input type="number" name="qty[]" class="form-control form-control-sm qty" min="0" step="0.01" value="1"></td>' +
      '<td><input type="text" name="unit[]" class="form-control form-control-sm" placeholder="m2"></td>' +
      '<td><input type="number" name="rate[]" class="form-control form-control-sm rate" min="0" step="0.01" value="0"></td>' +
      '<td class="amount-cell small text-muted">R 0.00</td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(tr);
    bindRow(tr);
    recalc();
  });

  function bindRow(tr) {
    tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); recalc(); });
    tr.querySelectorAll('input').forEach(function (inp) { inp.addEventListener('input', recalc); });
  }
  tbody.querySelectorAll('.item-row').forEach(bindRow);
  document.querySelector('[name=vat_rate]').addEventListener('input', recalc);
  recalc();
});
</script>
<?php page_footer(); ?>
