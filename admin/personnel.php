<?php
/**
 * RG PMS - Admin: Personnel list
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo = db();
$q   = trim($_GET['q'] ?? '');

// Base query - safe columns only
$sql = 'SELECT p.*, u.full_name, u.email, u.phone, r.name AS role_name
        FROM personnel p
        LEFT JOIN users u ON u.id = p.user_id
        LEFT JOIN roles r ON r.id = u.role_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.full_name LIKE ? OR u.email LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like];
}
$sql .= ' ORDER BY u.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personnel = $stmt->fetchAll();

page_header('Personnel', 'personnel');
page_heading(
    'Personnel',
    'Manage your team and their access',
    '<a href="' . BASE_URL . '/admin/personnel_new.php" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>New team member</a>'
);
?>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-brand"></i>All team members <span class="text-muted small fw-normal">(<?= count($personnel) ?>)</span></h6>
    <form class="d-flex" method="get" style="max-width:340px;">
      <input type="search" name="q" class="form-control form-control-sm" placeholder="Search personnel…" value="<?= e($q) ?>">
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Role</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$personnel): ?>
        <tr>
          <td colspan="5" class="text-center py-5">
            <i class="bi bi-inbox display-6 text-muted d-block mb-2"></i>
            <div class="text-muted">No personnel <?= $q ? 'matching your search' : 'yet' ?>.</div>
          </td>
        </tr>
      <?php else: foreach ($personnel as $p):
          $initials = '';
          foreach (preg_split('/\s+/', trim((string)($p['full_name'] ?? ''))) as $part) {
              if ($part !== '') $initials .= strtoupper($part[0]);
          }
          $initials = substr($initials, 0, 2) ?: 'U';
      ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;font-size:.72rem;font-weight:700;">
                <?= e($initials) ?>
              </span>
              <div>
                <div class="fw-semibold"><?= e($p['full_name'] ?? '-') ?></div>
                <div class="small text-muted">Added <?= nice_date($p['created_at'] ?? null) ?></div>
              </div>
            </div>
          </td>
          <td>
            <?php if (!empty($p['email'])): ?>
              <a href="mailto:<?= e($p['email']) ?>" class="text-decoration-none"><?= e($p['email']) ?></a>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td><?= e($p['phone'] ?? '-') ?></td>
          <td>
            <?php if (!empty($p['role_name'])): ?>
              <?= badge($p['role_name'], $p['role_name'] === 'admin' ? 'warning' : 'info') ?>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <a href="<?= BASE_URL ?>/admin/personnel_new.php?id=<?= (int)$p['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?= delete_button('personnel', (int)$p['id']) ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php page_footer(); ?>