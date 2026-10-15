<?php
/**
 * RG PMS - Admin: Create / Edit project
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo     = db();
$editId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit  = $editId > 0;
$errors  = [];

$proj = [
    'id'               => 0,
    'name'             => '',
    'client_id'        => '',
    'location'         => '',
    'description'      => '',
    'target_deadline'  => '',
    'budget'           => '',
    'status'           => 'not_yet_commenced',
    'progress_percent' => 0,
];

// ---- Column detection ----
$pCols = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
$hasBudget = in_array('budget', $pCols);
$hasDesc   = in_array('description', $pCols);
$hasStatus = in_array('status', $pCols);
$hasProg   = in_array('progress_percent', $pCols);

// ---- Load existing ----
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('error', 'Project not found.');
        redirect('admin/projects.php');
    }
    $proj = array_merge($proj, $row);
    $proj['status'] = normalise_project_status($proj['status'] ?? 'not_yet_commenced');
}

// ---- Clients for dropdown ----
$clients = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name ASC')->fetchAll();

// ---- Handle submit ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $proj['name']             = trim($_POST['name'] ?? '');
    $proj['client_id']        = (int)($_POST['client_id'] ?? 0);
    $proj['location']         = trim($_POST['location'] ?? '');
    $proj['description']      = trim($_POST['description'] ?? '');
    $proj['target_deadline']  = trim($_POST['target_deadline'] ?? '');
    $proj['budget']           = trim($_POST['budget'] ?? '');
    $proj['status']           = normalise_project_status($_POST['status'] ?? 'not_yet_commenced');
    $proj['progress_percent'] = max(0, min(100, (float)($_POST['progress_percent'] ?? 0)));

    if ($proj['name'] === '')      $errors[] = 'Project name is required.';
    if (!$proj['client_id'])       $errors[] = 'Please select a client.';

    if (!$errors) {
        try {
            $fields = ['name' => $proj['name'], 'client_id' => $proj['client_id'] ?: null];
            if (in_array('location', $pCols))        $fields['location']        = $proj['location'] ?: null;
            if ($hasDesc)                            $fields['description']     = $proj['description'] ?: null;
            if (in_array('target_deadline', $pCols)) $fields['target_deadline'] = $proj['target_deadline'] ?: null;
            if ($hasBudget)                          $fields['budget']          = is_numeric($proj['budget']) ? $proj['budget'] : 0;
            if ($hasStatus)                          $fields['status']          = $proj['status'];
            if ($hasProg)                            $fields['progress_percent'] = $proj['progress_percent'];

            if ($isEdit) {
                $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
                $sql = "UPDATE projects SET $set WHERE id = ?";
                $pdo->prepare($sql)->execute([...array_values($fields), $editId]);
                flash_set('success', 'Project updated.');
            } else {
                // array_keys() gives the column names, array_values() gives the params
                $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
                $ph   = implode(', ', array_fill(0, count($fields), '?'));
                $sql  = "INSERT INTO projects ($cols) VALUES ($ph)";
                $pdo->prepare($sql)->execute(array_values($fields));
                flash_set('success', 'Project created.');
            }

            redirect('admin/projects.php');
        } catch (Exception $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

// ---- Only 4 statuses ----
$statusOptions = [
    'not_yet_commenced' => 'Not yet commenced',
    'active'            => 'In progress',
    'delayed'           => 'Delayed',
    'completed'         => 'Completed',
];

page_header($isEdit ? 'Edit project' : 'New project', 'projects');
page_heading(
    $isEdit ? 'Edit project' : 'New project',
    $isEdit ? 'Update this project\'s details' : 'Create a new project'
);
?>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4 p-lg-5">

        <?php if ($errors): ?>
          <div class="alert alert-danger small">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label required">Project name</label>
            <input type="text" name="name" class="form-control" required
                   value="<?= e($proj['name']) ?>">
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required">Client</label>
              <select name="client_id" class="form-select" required>
                <option value="">- Select client -</option>
                <?php foreach ($clients as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$proj['client_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['company_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Location</label>
              <input type="text" name="location" class="form-control"
                     value="<?= e($proj['location']) ?>">
            </div>
          </div>

          <div class="row g-3 mt-0">
            <div class="col-md-6">
              <label class="form-label">Target deadline</label>
              <input type="date" name="target_deadline" class="form-control"
                     value="<?= e($proj['target_deadline'] ? substr($proj['target_deadline'], 0, 10) : '') ?>">
            </div>
            <?php if ($hasBudget): ?>
              <div class="col-md-6">
                <label class="form-label">Budget (R)</label>
                <input type="number" step="0.01" min="0" name="budget" class="form-control"
                       value="<?= e($proj['budget']) ?>">
              </div>
            <?php endif; ?>
          </div>

          <?php if ($hasStatus || $hasProg): ?>
            <div class="row g-3 mt-0">
              <?php if ($hasStatus): ?>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                    <?php foreach ($statusOptions as $key => $label): ?>
                      <option value="<?= e($key) ?>" <?= $proj['status'] === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">
                    An <em>In progress</em> project past its deadline is auto-flagged as <em>Delayed</em>.
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($hasProg): ?>
                <div class="col-md-6">
                  <label class="form-label">Progress (%)</label>
                  <input type="number" min="0" max="100" step="1" name="progress_percent" class="form-control"
                         value="<?= (int)$proj['progress_percent'] ?>">
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($hasDesc): ?>
            <div class="mb-4 mt-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="4"><?= e($proj['description']) ?></textarea>
            </div>
          <?php else: ?>
            <div class="mb-4 mt-3"></div>
          <?php endif; ?>

          <div class="d-flex justify-content-between">
            <a href="<?= BASE_URL ?>/admin/projects.php" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
            <button class="btn btn-brand">
              <i class="bi bi-check2-circle me-2"></i><?= $isEdit ? 'Save changes' : 'Create project' ?>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>