<?php
/**
 * RG PMS - Admin: Clients list
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo = db();
$q   = trim($_GET['q'] ?? '');

$sql = 'SELECT c.*,
               (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS project_count
        FROM clients c';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE c.company_name LIKE ? OR c.contact_person LIKE ? OR c.email LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY c.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

page_header('Clients', 'clients');
page_heading(
    'Clients',
    'Manage client accounts and portal access',
    '<a href="' . BASE_URL . '/admin/client_new.php" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>New client</a>'
);
?>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-brand"></i>All clients <span class="text-muted small fw-normal">(<?= count($clients) ?>)</span></h6>
    <form class="d-flex" method="get" style="max-width:340px;">
      <input type="search" name="q" class="form-control form-control-sm" placeholder="Search clients…" value="<?= e($q) ?>">
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Company</th>
          <th>Contact</th>
          <th>Email</th>
          <th>Phone</th>
          <th class="text-center">Projects</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$clients): ?>
        <tr>
          <td colspan="6" class="text-center py-5">
            <i class="bi bi-inbox display-6 text-muted d-block mb-2"></i>
            <div class="text-muted">No clients <?= $q ? 'matching your search' : 'yet' ?>.</div>
          </td>
        </tr>
      <?php else: foreach ($clients as $c): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($c['company_name'] ?? '-') ?></div>
            <div class="small text-muted">Added <?= nice_date($c['created_at'] ?? null) ?></div>
          </td>
          <td><?= e($c['contact_person'] ?? '-') ?></td>
          <td>
            <?php if (!empty($c['email'])): ?>
              <a href="mailto:<?= e($c['email']) ?>" class="text-decoration-none"><?= e($c['email']) ?></a>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td><?= e($c['phone'] ?? '-') ?></td>
          <td class="text-center">
            <span class="badge text-bg-light border"><?= (int)($c['project_count'] ?? 0) ?></span>
          </td>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <a href="<?= BASE_URL ?>/admin/client_view.php?id=<?= (int)$c['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <a href="<?= BASE_URL ?>/admin/client_new.php?id=<?= (int)$c['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?= delete_button('client', (int)$c['id']) ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php page_footer(); ?>