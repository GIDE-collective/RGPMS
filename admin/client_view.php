<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT c.*, u.email AS login_email FROM clients c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { flash_set('danger', 'Client not found.'); redirect('admin/clients.php'); }

$projects = db()->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC');
$projects->execute([$id]);
$projectList = $projects->fetchAll();

page_header('Client: ' . $client['company_name'], 'clients');
page_heading(e($client['company_name']), 'Client profile',
    '<a class="btn btn-outline-brand btn-sm" href="' . BASE_URL . '/admin/project_new.php?client_id=' . $id . '"><i class="bi bi-plus-lg me-1"></i>New project</a>');
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill me-2 text-warning"></i>Contact details</h6>
        <dl class="row small mb-0">
          <dt class="col-sm-5 text-muted">Company</dt><dd class="col-sm-7"><?= e($client['company_name']) ?></dd>
          <dt class="col-sm-5 text-muted">Contact</dt><dd class="col-sm-7"><?= e($client['contact_person']) ?></dd>
          <dt class="col-sm-5 text-muted">Phone</dt><dd class="col-sm-7"><?= e($client['phone'] ?? '-') ?></dd>
          <dt class="col-sm-5 text-muted">Email</dt><dd class="col-sm-7"><?= e($client['email'] ?? '-') ?></dd>
          <dt class="col-sm-5 text-muted">Address</dt><dd class="col-sm-7"><?= e($client['address'] ?? '-') ?></dd>
          <dt class="col-sm-5 text-muted">Portal login</dt>
          <dd class="col-sm-7"><?= $client['login_email'] ? e($client['login_email']) : badge('No login', 'secondary') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-white rounded-top-4 py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-kanban me-2 text-warning"></i>Projects (<?= count($projectList) ?>)</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Project</th><th>Status</th><th>Progress</th><th>Deadline</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <?php if (!$projectList): ?><tr><td colspan="5" class="text-muted small text-center py-4">No projects for this client yet.</td></tr><?php endif; ?>
            <?php foreach ($projectList as $p): ?>
            <tr>
              <td><a class="text-decoration-none fw-semibold" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></td>
              <td><?= project_status_badge(effective_project_status($p)) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="min-width:80px"><div class="progress-bar bg-warning" style="width:<?= (float)$p['progress_percent'] ?>%"></div></div>
                  <span class="small"><?= (float)$p['progress_percent'] ?>%</span>
                </div>
              </td>
              <td class="small"><?= nice_date($p['target_deadline']) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $p['id'] ?>"><i class="bi bi-eye me-1"></i>View</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
