<?php
/**
 * Admin - create an invoice for a project.
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
    $projectId  = (int)($_POST['project_id'] ?? 0);
    $issueDate  = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate    = $_POST['due_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);

    if ($projectId <= 0) $errors[] = 'Select a project.';
    if ($amount <= 0) $errors[] = 'Enter an amount greater than zero.';

    if (!$errors) {
        $number = next_number('RGI-' . date('Y'), 'invoices', 'invoice_number');
        $pdo->prepare('INSERT INTO invoices (project_id, invoice_number, issue_date, due_date, description, amount, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$projectId, $number, $issueDate, $dueDate ?: null, $description ?: null, $amount, $user['id']]);
        notify_client_of_project($projectId, 'New invoice issued', 'Invoice ' . $number . ' (' . money($amount) . ') has been issued for your project.', 'client/invoice_view.php?id=' . $pdo->lastInsertId());
        flash_set('success', 'Invoice ' . $number . ' created and issued to the client.');
        redirect('admin/invoices.php');
    }
}

page_header('New invoice', 'invoices');
page_heading('New invoice', 'Issue an invoice against a project');
?>
<div class="card border-0 shadow-sm rounded-4" style="max-width:640px">
  <div class="card-body p-4">
    <?php if ($errors): ?><div class="alert alert-danger small py-2"><?php foreach ($errors as $er): ?><div><i class="bi bi-x-circle me-1"></i><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label required">Project</label>
        <select name="project_id" class="form-select" required>
          <option value="">- select project -</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ((int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['company_name']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row">
        <div class="col-6 mb-3">
          <label class="form-label required">Issue date</label>
          <input type="date" name="issue_date" class="form-control" required value="<?= e($_POST['issue_date'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-6 mb-3">
          <label class="form-label">Due date</label>
          <input type="date" name="due_date" class="form-control" value="<?= e($_POST['due_date'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <input type="text" name="description" class="form-control" placeholder="e.g. Progress payment 1 - structure" value="<?= e($_POST['description'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label required">Amount (R, incl. VAT)</label>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?= e($_POST['amount'] ?? '') ?>">
      </div>
      <button class="btn btn-brand"><i class="bi bi-check-lg me-1"></i>Issue invoice</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/invoices.php">Cancel</a>
    </form>
  </div>
</div>
<?php page_footer(); ?>
