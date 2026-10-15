<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo = db();
$quotes = $pdo->query(
    'SELECT q.*, p.name AS project_name, c.company_name
     FROM quotations q
     JOIN projects p ON p.id = q.project_id
     JOIN clients c ON c.id = p.client_id
     ORDER BY q.created_at DESC'
)->fetchAll();
foreach ($quotes as &$q) { $q['_totals'] = quotation_totals((int)$q['id']); }
unset($q);

page_header('Quotations', 'quotations');
page_heading('Quotations', 'Create, send and track client responses',
    '<a class="btn btn-brand btn-sm" href="' . BASE_URL . '/admin/quotation_new.php"><i class="bi bi-plus-lg me-1"></i>New quotation</a>');
?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Number</th><th>Project</th><th>Client</th><th>Issued</th><th>Total</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$quotes): ?><tr><td colspan="7" class="text-muted small text-center py-4">No quotations yet.</td></tr><?php endif; ?>
        <?php foreach ($quotes as $q): ?>
        <tr>
          <td class="fw-semibold"><?= e($q['quotation_number']) ?></td>
          <td><a class="text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $q['project_id'] ?>&tab=quotations"><?= e($q['project_name']) ?></a></td>
          <td class="small"><?= e($q['company_name']) ?></td>
          <td class="small"><?= nice_date($q['issue_date']) ?></td>
          <td class="small"><?= money($q['_totals']['total']) ?></td>
          <td><?= quotation_status_badge($q['status']) ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/admin/quotation_view.php?id=<?= $q['id'] ?>"><i class="bi bi-eye me-1"></i>View</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/admin/quotation_view.php?id=<?= $q['id'] ?>&pdf=1"><i class="bi bi-file-pdf me-1"></i>PDF</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_footer(); ?>
