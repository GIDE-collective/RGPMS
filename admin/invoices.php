<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo = db();
$invoices = $pdo->query(
    'SELECT i.*, p.name AS project_name, c.company_name
     FROM invoices i JOIN projects p ON p.id = i.project_id JOIN clients c ON c.id = p.client_id
     ORDER BY i.issue_date DESC'
)->fetchAll();

page_header('Invoices', 'invoices');
page_heading('Invoices', 'Invoice generation for clients',
    '<a class="btn btn-brand btn-sm" href="' . BASE_URL . '/admin/invoice_new.php"><i class="bi bi-plus-lg me-1"></i>New invoice</a>');
?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Number</th><th>Project</th><th>Client</th><th>Issued</th><th>Due</th><th>Amount</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$invoices): ?><tr><td colspan="8" class="text-muted small text-center py-4">No invoices yet.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $inv): ?>
        <tr>
          <td class="fw-semibold"><?= e($inv['invoice_number']) ?></td>
          <td><a class="text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $inv['project_id'] ?>&tab=invoices"><?= e($inv['project_name']) ?></a></td>
          <td class="small"><?= e($inv['company_name']) ?></td>
          <td class="small"><?= nice_date($inv['issue_date']) ?></td>
          <td class="small"><?= nice_date($inv['due_date']) ?></td>
          <td class="small"><?= money($inv['amount']) ?></td>
          <td><?= $inv['status'] === 'paid' ? badge('Paid', 'success') : badge('Issued', 'warning') ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/admin/invoice_view.php?id=<?= $inv['id'] ?>"><i class="bi bi-eye me-1"></i>View</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/admin/invoice_view.php?id=<?= $inv['id'] ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>PDF</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_footer(); ?>
