<?php
/**
 * Admin - company-wide materials stock management.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $unit = trim($_POST['unit'] ?? 'unit');
        $qty  = (float)($_POST['stock_quantity'] ?? 0);
        if ($name !== '') {
            try {
                $pdo->prepare('INSERT INTO materials (name, unit, stock_quantity) VALUES (?, ?, ?)')
                    ->execute([$name, $unit, $qty]);
                flash_set('success', 'Material added.');
            } catch (PDOException $ex) {
                flash_set('danger', 'A material with this name already exists.');
            }
        }
    } elseif ($action === 'adjust') {
        $id = (int)($_POST['material_id'] ?? 0);
        $qty = (float)($_POST['stock_quantity'] ?? 0);
        $pdo->prepare('UPDATE materials SET stock_quantity = ? WHERE id = ?')->execute([$qty, $id]);
        flash_set('success', 'Stock level updated.');
    }
    redirect('admin/materials.php');
}

$materials = $pdo->query('SELECT * FROM materials ORDER BY name')->fetchAll();
$totalValue = 0; // value requires cost tracking - out of scope, stock qty shown only

page_header('Materials', 'materials');
page_heading('Materials', 'Company-wide stock visibility');
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-2 text-warning"></i>Add material</h6>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label required">Material name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Cement (50kg)" required>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label required">Unit</label>
              <input type="text" name="unit" class="form-control" placeholder="bags / m3 / units" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Opening stock</label>
              <input type="number" step="0.01" name="stock_quantity" class="form-control" value="0">
            </div>
          </div>
          <button class="btn btn-brand w-100">Add material</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-warning"></i>Stock levels</h6>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Material</th><th>Unit</th><th class="text-end">Stock</th><th>Level</th><th class="text-end">Adjust</th></tr></thead>
          <tbody>
            <?php if (!$materials): ?><tr><td colspan="5" class="text-muted small text-center py-4">No materials recorded.</td></tr><?php endif; ?>
            <?php foreach ($materials as $m): $low = (float)$m['stock_quantity'] <= 10; ?>
            <tr>
              <td class="fw-semibold"><?= e($m['name']) ?></td>
              <td class="small text-muted"><?= e($m['unit']) ?></td>
              <td class="text-end fw-semibold <?= $low ? 'text-danger' : '' ?>"><?= (float)$m['stock_quantity'] ?></td>
              <td>
                <div class="d-flex align-items-center gap-2" style="max-width:200px">
                  <div class="progress flex-grow-1"><div class="progress-bar <?= $low ? 'bg-danger' : 'bg-success' ?>" style="width:<?= min(100, (float)$m['stock_quantity']) ?>%"></div></div>
                  <?php if ($low): ?><i class="bi bi-exclamation-triangle text-danger" title="Low stock"></i><?php endif; ?>
                </div>
              </td>
              <td class="text-end">
                <form method="post" class="d-inline-flex gap-1">
                  <?= csrf_field() ?><input type="hidden" name="action" value="adjust"><input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                  <input type="number" step="0.01" name="stock_quantity" class="form-control form-control-sm" style="width:100px" value="<?= (float)$m['stock_quantity'] ?>">
                  <button class="btn btn-sm btn-outline-brand">Set</button>
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
<?php page_footer(); ?>
