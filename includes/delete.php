<?php
/**
 * RG PMS - Delete button helper
 */

/**
 * Render a delete button that POSTs to admin/delete.php with confirmation.
 */
function delete_button(string $type, int $id, string $label = '', string $size = 'sm'): string {
    // Build a relative path (no BASE_URL, no scheme/host) so redirect()
    // can safely prepend BASE_URL without doubling it up.
    $uri = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';

    // Strip scheme + host if present
    if (preg_match('#^https?://[^/]+(.*)$#', $uri, $m)) {
        $uri = $m[1];
    }
    // Strip BASE_URL prefix if present
    if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
        $uri = substr($uri, strlen(BASE_URL));
    }
    $redirect = ltrim($uri, '/');
    if ($redirect === '') $redirect = 'admin/dashboard.php';

    $confirm = 'Delete this ' . str_replace('_', ' ', $type) . '? This cannot be undone.';

    ob_start(); ?>
    <form method="post" action="<?= BASE_URL ?>/admin/delete.php" class="d-inline"
          onsubmit="return confirm('<?= e($confirm) ?>');">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <button type="submit" class="btn btn-<?= $size ?> btn-outline-danger" title="Delete">
            <i class="bi bi-trash"></i><?= $label ? ' ' . e($label) : '' ?>
        </button>
    </form>
    <?php
    return ob_get_clean();
}