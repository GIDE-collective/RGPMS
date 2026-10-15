<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$user = current_user();

// "Mark all read" quick action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    csrf_check();
    $upd = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $upd->execute([$user['id']]);
    flash_set('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

// Mark individual as read when opened via link
if (isset($_GET['read'])) {
    $upd = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $upd->execute([(int)$_GET['read'], $user['id']]);
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
$notifs = $stmt->fetchAll();

page_header('Notifications', 'notifications');
page_heading('Notifications', 'Everything that needs your attention',
    '<form method="post" class="d-inline">' . csrf_field() . '<input type="hidden" name="action" value="mark_all_read">'
    . '<button class="btn btn-outline-brand btn-sm"><i class="bi bi-check2-all me-1"></i>Mark all read</button></form>');
?>
<div class="card border-0 shadow-sm rounded-4">
  <ul class="list-group list-group-flush">
    <?php if (!$notifs): ?>
      <li class="list-group-item text-muted small py-4 text-center">You have no notifications.</li>
    <?php endif; ?>
    <?php foreach ($notifs as $n): ?>
      <li class="list-group-item d-flex gap-3 align-items-start <?= $n['is_read'] ? 'opacity-75' : 'bg-light-subtle' ?>">
        <i class="bi bi-bell fs-5 text-warning mt-1"></i>
        <div class="flex-grow-1">
          <div class="<?= $n['is_read'] ? '' : 'fw-semibold' ?>"><?= e($n['title']) ?></div>
          <?php if ($n['message']): ?><div class="small text-muted"><?= e($n['message']) ?></div><?php endif; ?>
          <div class="small text-muted"><?= nice_datetime($n['created_at']) ?></div>
        </div>
        <?php if ($n['link']): ?>
          <a class="btn btn-sm btn-outline-brand" href="<?= BASE_URL ?>/<?= e($n['link']) ?>">Open</a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php page_footer(); ?>
