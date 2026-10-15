<?php
/**
 * RG PMS - Admin: Projects (card grid)
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo = db();
$q   = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// ---- Fetch all projects (with optional text search in SQL) ----
$sql = 'SELECT p.*, c.company_name AS client_name
        FROM projects p
        LEFT JOIN clients c ON c.id = p.client_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE p.name LIKE ? OR p.location LIKE ? OR c.company_name LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY p.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// ---- Apply status filter in PHP using the SAME logic as the badges ----
if ($statusFilter !== '') {
    $projects = array_values(array_filter($projects, function ($p) use ($statusFilter) {
        return effective_project_status($p) === $statusFilter;
    }));
}

page_header('Projects', 'projects');
page_heading(
    'Projects',
    'All active and archived projects',
    '<a href="' . BASE_URL . '/admin/project_new.php" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>New project</a>'
);
?>

<!-- ============ FILTER BAR ============ -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-md-6 col-lg-5">
        <div class="position-relative">
          <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted small"></i>
          <input type="search" name="q" class="form-control ps-5"
                 placeholder="Search by name, client, or location…"
                 value="<?= e($q) ?>">
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <option value="not_yet_commenced" <?= $statusFilter === 'not_yet_commenced' ? 'selected' : '' ?>>Not yet commenced</option>
          <option value="active"            <?= $statusFilter === 'active' ? 'selected' : '' ?>>In progress</option>
          <option value="delayed"           <?= $statusFilter === 'delayed' ? 'selected' : '' ?>>Delayed</option>
          <option value="completed"         <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
        </select>
      </div>
      <div class="col-md-2 col-lg-auto ms-lg-auto d-flex gap-2">
        <?php if ($q !== '' || $statusFilter !== ''): ?>
          <a href="<?= BASE_URL ?>/admin/projects.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Clear
          </a>
        <?php endif; ?>
        <span class="text-muted small align-self-center">
          <?= count($projects) ?> result<?= count($projects) === 1 ? '' : 's' ?>
        </span>
      </div>
    </form>
  </div>
</div>

<!-- ============ PROJECT CARDS ============ -->
<?php if (!$projects): ?>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body text-center py-5">
      <i class="bi bi-kanban display-4 text-muted"></i>
      <p class="text-muted mt-3 mb-1">
        <?= $q !== '' || $statusFilter !== '' ? 'No projects match your filters.' : 'No projects yet.' ?>
      </p>
      <?php if ($q === '' && $statusFilter === ''): ?>
        <a href="<?= BASE_URL ?>/admin/project_new.php" class="btn btn-brand mt-2">
          <i class="bi bi-plus-lg me-1"></i>Create the first project
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>

  <div class="row g-3">
    <?php foreach ($projects as $p):
        $prog   = (float)($p['progress_percent'] ?? 0);
        $status = effective_project_status($p);

        // Progress bar colour based on effective status
        $barColor = match($status) {
            'completed' => 'bg-success',
            'delayed'   => 'bg-danger',
            default     => '',
        };
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="card project-card h-100 border-0 shadow-sm rounded-4">

          <div class="card-body p-4 d-flex flex-column">

            <!-- Header: name + status -->
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <h6 class="card-title mb-0 fw-semibold">
                <a href="<?= BASE_URL ?>/admin/project_view.php?id=<?= (int)$p['id'] ?>"
                   class="text-decoration-none stretched-link-name">
                  <?= e($p['name']) ?>
                </a>
              </h6>
              <?= project_status_badge($status) ?>
            </div>

            <!-- Meta: client, location, deadline -->
            <div class="small text-muted mb-3">
              <?php if (!empty($p['client_name'])): ?>
                <div><i class="bi bi-briefcase me-1"></i><?= e($p['client_name']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p['location'])): ?>
                <div><i class="bi bi-geo-alt me-1"></i><?= e($p['location']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p['target_deadline'])): ?>
                <div><i class="bi bi-flag me-1"></i>Due <?= nice_date($p['target_deadline']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Progress -->
            <div class="mt-auto">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Progress</span>
                <span class="fw-semibold"><?= round($prog) ?>%</span>
              </div>
              <div class="progress mb-3" style="height:6px;">
                <div class="progress-bar <?= $barColor ?>" style="width:<?= $prog ?>%"></div>
              </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2 mt-2">
              <a href="<?= BASE_URL ?>/admin/project_view.php?id=<?= (int)$p['id'] ?>"
                 class="btn btn-sm btn-outline-brand flex-grow-1">
                <i class="bi bi-eye me-1"></i>View
              </a>
              <a href="<?= BASE_URL ?>/admin/project_new.php?id=<?= (int)$p['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?= delete_button('project', (int)$p['id']) ?>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<style>
.project-card {
  transition: transform .15s ease, box-shadow .15s ease;
}
.project-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(15,23,42,.10) !important;
}
.project-card .card-title a {
  color: var(--text-color, #0f172a);
}
.project-card .card-title a:hover {
  color: #3b82f6;
}
html.theme-dark .project-card .card-title a { color: #e2e8f0; }
html.theme-dark .project-card .card-title a:hover { color: #93c5fd; }
</style>

<?php page_footer(); ?>