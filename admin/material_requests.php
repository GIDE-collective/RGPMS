<?php
/**
 * Admin - review material requests: Approve / Reject.
 * Approved requests are recorded against the project (and stock adjusted).
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    $stmt = $pdo->prepare(
        'SELECT mr.*, m.name AS material_name, m.stock_quantity AS stock
         FROM material_requests mr JOIN materials m ON m.id = mr.material_id WHERE mr.id = ?'
    );
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action === 'approve') {
            $pdo->prepare('UPDATE material_requests SET status = "approved", reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $requestId]);
            // Record against project + reduce company stock
            $pdo->prepare('INSERT INTO project_materials (project_id, material_id, quantity, unit) VALUES (?, ?, ?, ?)')
                ->execute([$req['project_id'], $req['material_id'], $req['quantity'], $req['unit']]);
            $pdo->prepare('UPDATE materials SET stock_quantity = stock_quantity - ? WHERE id = ?')
                ->execute([$req['quantity'], $req['material_id']]);
            notify_client_of_project($req['project_id'], 'Material request approved',
                'Material request for ' . $req['material_name'] . ' has been approved and will be procured.', 'client/project_view.php?id=' . $req['project_id']);
            flash_set('success', 'Request #' . $requestId . ' approved and recorded against the project.');
        } elseif ($action === 'reject') {
            $pdo->prepare('UPDATE material_requests SET status = "rejected", reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $requestId]);
            flash_set('success', 'Request #' . $requestId . ' rejected.');
        }
    }
    redirect('admin/material_requests.php' . (!empty($_POST['project_id']) ? '?project_id=' . (int)$_POST['project_id'] : ''));
}

$sql = 'SELECT mr.*, m.name AS material_name, m.unit AS material_unit, u.full_name AS requested_by_name, rv.full_name AS reviewer_name, p.name AS project_name, c.company_name
        FROM material_requests mr
        JOIN materials m ON m.id = mr.material_id
        JOIN projects p ON p.id = mr.project_id
        JOIN clients c ON c.id = p.client_id
        LEFT JOIN users u ON u.id = mr.requested_by
        LEFT JOIN users rv ON rv.id = mr.reviewed_by';
$params = [];
if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected'], true)) {
    $sql .= ' WHERE mr.status = ?'; $params[] = $_GET['status'];
}
if (!empty($_GET['project_id'])) {
    $sql .= (strpos($sql, 'WHERE') !== false ? ' AND' : ' WHERE') . ' mr.project_id = ?'; $params[] = (int)$_GET['project_id'];
}
$sql .= ' ORDER BY (mr.status = "pending") DESC, mr.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

page_header('Material requests', 'materials');
page_heading('Material requests', 'Request - review - Approved / Pending / Rejected - recorded against project');
?>
<div class="mb-3 d-flex gap-2 flex-wrap">
  <a class="btn btn-sm <?= empty($_GET['status']) ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= BASE_URL ?>/admin/material_requests.php">All</a>
  <a class="btn btn-sm <?= ($_GET['status'] ?? '') === 'pending' ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= BASE_URL ?>/admin/material_requests.php?status=pending">Pending</a>
  <a class="btn btn-sm <?= ($_GET['status'] ?? '') === 'approved' ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= BASE_URL ?>/admin/material_requests.php?status=approved">Approved</a>
  <a class="btn btn-sm <?= ($_GET['status'] ?? '') === 'rejected' ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= BASE_URL ?>/admin/material_requests.php?status=rejected">Rejected</a>
</div>

<div class="card border-0 shadow-sm rounded-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>#</th><th>Project</th><th>Material</th><th>Qty</th><th>Reason</th><th>Requested by</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$requests): ?><tr><td colspan="8" class="text-muted small text-center py-4">No material requests found.</td></tr><?php endif; ?>
        <?php foreach ($requests as $r): ?>
        <tr class="<?= $r['status'] === 'pending' ? 'table-warning' : '' ?>">
          <td class="small text-muted">#<?= $r['id'] ?></td>
          <td><a class="text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $r['project_id'] ?>&tab=materials"><?= e($r['project_name']) ?></a><div class="small text-muted"><?= e($r['company_name']) ?></div></td>
          <td><?= e($r['material_name']) ?></td>
          <td><?= (float)$r['quantity'] ?> <?= e($r['unit']) ?></td>
          <td class="small"><?= e($r['reason'] ?? '-') ?></td>
          <td class="small"><?= e($r['requested_by_name'] ?? '-') ?><div class="text-muted"><?= nice_datetime($r['created_at']) ?></div></td>
          <td><?= match ($r['status']) { 'approved' => badge('Approved', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending', 'warning') } ?></td>
          <td class="text-end text-nowrap">
            <?php if ($r['status'] === 'pending'): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="approve">
                <input type="hidden" name="project_id" value="<?= $r['project_id'] ?>">
                <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
              </form>
              <form method="post" class="d-inline" data-confirm="Reject this material request?">
                <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="reject">
                <input type="hidden" name="project_id" value="<?= $r['project_id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
              </form>
            <?php elseif ($r['reviewed_at']): ?>
              <span class="small text-muted">by <?= e($r['reviewer_name'] ?? '-') ?> · <?= nice_datetime($r['reviewed_at']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_footer(); ?>
