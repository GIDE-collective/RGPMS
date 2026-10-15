<?php
/**
 * Admin - progress photograph approval queue.
 * Photos become visible to the client only after approval.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action'] ?? '';
    $photoId  = (int)($_POST['photo_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM project_photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if ($photo) {
        if ($action === 'approve') {
            $pdo->prepare('UPDATE project_photos SET status = "approved", approved_by = ?, approved_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $photoId]);
            // Notify the client - new approved photograph available
            notify_client_of_project($photo['project_id'], 'New progress photograph approved',
                'A new approved progress photograph is available for your project.',
                'client/project_view.php?id=' . $photo['project_id'] . '#photos');
            flash_set('success', 'Photograph approved and now visible to the client.');
        } elseif ($action === 'reject') {
            $pdo->prepare('UPDATE project_photos SET status = "rejected", approved_by = ?, approved_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $photoId]);
            flash_set('success', 'Photograph rejected.');
        }
    }
    redirect('admin/photos.php');
}

$sql = 'SELECT ph.*, u.full_name AS uploader, p.name AS project_name, c.company_name
        FROM project_photos ph
        JOIN projects p ON p.id = ph.project_id
        JOIN clients c ON c.id = p.client_id
        LEFT JOIN users u ON u.id = ph.uploaded_by';
$params = [];
if (($_GET['status'] ?? '') === 'pending') { $sql .= ' WHERE ph.status = "pending"'; }
$sql .= ' ORDER BY (ph.status = "pending") DESC, ph.uploaded_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$photos = $stmt->fetchAll();

$pendingCount = (int)$pdo->query('SELECT COUNT(*) FROM project_photos WHERE status = "pending"')->fetchColumn();

page_header('Photo approvals', 'projects');
page_heading('Photo approvals', 'Review photographs before they become visible to clients');
?>
<div class="alert alert-info rounded-4 small d-flex align-items-center gap-2">
  <i class="bi bi-shield-check"></i>
  <span><strong><?= $pendingCount ?></strong> photograph(s) awaiting approval. Only approved photographs appear in the client portal.</span>
</div>

<div class="row g-4">
  <?php if (!$photos): ?><div class="col-12"><div class="card border-0 shadow-sm rounded-4"><div class="card-body text-muted small text-center py-5">No photographs found.</div></div></div><?php endif; ?>
  <?php foreach ($photos as $ph): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card project-card h-100">
      <a href="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" target="_blank">
        <img src="<?= BASE_URL ?>/<?= e($ph['file_path']) ?>" class="card-img-top photo-thumb" alt="<?= e($ph['caption'] ?? '') ?>">
      </a>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <div class="fw-semibold small"><?= e($ph['caption'] ?? 'No caption') ?></div>
          <?= match ($ph['status']) { 'approved' => badge('Approved', 'success'), 'rejected' => badge('Rejected', 'danger'), default => badge('Pending', 'warning') } ?>
        </div>
        <div class="small text-muted mb-2">
          Project: <a class="text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $ph['project_id'] ?>&tab=photos"><?= e($ph['project_name']) ?></a> (<?= e($ph['company_name']) ?>)<br>
          Uploaded by <strong><?= e($ph['uploader']) ?></strong> on <?= nice_datetime($ph['uploaded_at']) ?>
        </div>
        <?php if ($ph['status'] === 'pending'): ?>
          <div class="d-flex gap-2">
            <form method="post" class="flex-grow-1">
              <?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= $ph['id'] ?>"><input type="hidden" name="action" value="approve">
              <button class="btn btn-success btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Approve</button>
            </form>
            <form method="post" data-confirm="Reject this photograph?">
              <?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= $ph['id'] ?>"><input type="hidden" name="action" value="reject">
              <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i></button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php page_footer(); ?>
