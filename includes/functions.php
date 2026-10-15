<?php
/**
 * RG PMS - Shared helper functions
 */
require_once __DIR__ . '/../config/db.php';

/* ---------------------------------------------------------- *
 * Flash messages
 * ---------------------------------------------------------- */
function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* ---------------------------------------------------------- *
 * CSRF protection
 * ---------------------------------------------------------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_check(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        die('Invalid security token. Please go back, refresh the page and try again.');
    }
}

/* ---------------------------------------------------------- *
 * Notifications
 * ---------------------------------------------------------- */
function notify(int $userId, string $title, string $message, ?string $link = null): void {
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $title, $message, $link]);
}

function notify_client_of_project(int $projectId, string $title, string $message, ?string $link = null): void {
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT c.user_id FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ? AND c.user_id IS NOT NULL'
    );
    $stmt->execute([$projectId]);
    while ($row = $stmt->fetch()) {
        notify((int)$row['user_id'], $title, $message, $link);
    }
}

function unread_notification_count(int $userId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/* ---------------------------------------------------------- *
 * Formatting
 * ---------------------------------------------------------- */
function money($amount): string {
    return APP_CURRENCY . ' ' . number_format((float)$amount, 2, '.', ',');
}

function nice_date(?string $date): string {
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : '-';
}

function nice_datetime(?string $datetime): string {
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    return $ts ? date('d M Y H:i', $ts) : '-';
}

function time_ago(?string $datetime): string {
    if (!$datetime) return '-';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' days ago';
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function badge(string $text, string $color = 'secondary'): string {
    return '<span class="badge text-bg-' . $color . '">' . e($text) . '</span>';
}

/* ---------------------------------------------------------- *
 * PROJECT STATUS
 * ---------------------------------------------------------- */

/** Normalise any project status variant to a canonical key. */
function normalise_project_status(?string $status): string {
    $status = strtolower(trim((string)$status));
    $aliases = [
        'active'            => ['active', 'in_progress', 'in progress', 'ongoing', 'started'],
        'on_hold'           => ['on_hold', 'on hold', 'paused', 'suspended'],
        'not_yet_commenced' => ['not_yet_commenced', 'not yet commenced', 'pending', 'planning', 'new'],
        'delayed'           => ['delayed', 'overdue', 'late'],
        'completed'         => ['completed', 'complete', 'done', 'finished', 'closed'],
        'cancelled'         => ['cancelled', 'canceled', 'void', 'abandoned'],
    ];

    foreach ($aliases as $canonical => $list) {
        if (in_array($status, $list, true)) return $canonical;
    }
    return $status === '' ? 'active' : $status;
}

function project_status_badge(string $status): string {
    $canonical = normalise_project_status($status);

    $map = [
        'not_yet_commenced' => ['Not yet commenced', 'secondary'],
        'active'            => ['In progress',       'primary'],
        'in_progress'       => ['In progress',       'primary'],
        'on_hold'           => ['On hold',           'warning'],
        'delayed'           => ['Delayed',           'danger'],
        'completed'         => ['Completed',         'success'],
        'cancelled'         => ['Cancelled',         'dark'],
    ];

    [$label, $color] = $map[$canonical] ?? [ucfirst($status ?: 'Unknown'), 'secondary'];
    return badge($label, $color);
}

/**
 * Effective project status.
 * Uses the stored status as the source of truth. Auto-flags "delayed" only when
 * the record is active AND past deadline AND admin hasn't manually overridden.
 */
function effective_project_status(array $project): string {
    $canonical = normalise_project_status($project['status'] ?? 'active');

    if (in_array($canonical, ['completed', 'cancelled', 'on_hold'], true)) {
        return $canonical;
    }

    if ($canonical === 'active'
        && !empty($project['target_deadline'])
        && strtotime($project['target_deadline']) < strtotime(date('Y-m-d'))) {
        return 'delayed';
    }

    return $canonical;
}

/* ---------------------------------------------------------- *
 * QUOTATION STATUS
 * ---------------------------------------------------------- */

/** Normalise any quotation status variant to a canonical key. */
function normalise_quotation_status(?string $status): string {
    $status = strtolower(trim((string)$status));
    $aliases = [
        'draft'         => ['draft', 'new', 'pending', 'created'],
        'sent'          => ['sent', 'awaiting', 'awaiting response', 'awaiting_response', 'issued', 'delivered'],
        'approved'      => ['approved', 'accepted', 'signed', 'won'],
        'rejected'      => ['rejected', 'declined', 'refused', 'lost'],
        're_evaluation' => ['re_evaluation', 're-evaluation', 'revision', 'revision requested', 'changes requested', 'negotiation'],
        'expired'       => ['expired', 'lapsed', 'overdue'],
        'cancelled'     => ['cancelled', 'canceled', 'void', 'withdrawn'],
    ];

    foreach ($aliases as $canonical => $list) {
        if (in_array($status, $list, true)) return $canonical;
    }
    return $status === '' ? 'draft' : $status;
}

function quotation_status_badge(string $status): string {
    $canonical = normalise_quotation_status($status);

    $map = [
        'draft'         => ['Draft',                       'secondary'],
        'sent'          => ['Sent — awaiting response',    'primary'],
        'approved'      => ['Approved',                    'success'],
        'rejected'      => ['Rejected',                    'danger'],
        're_evaluation' => ['Re-evaluation requested',     'warning'],
        'expired'       => ['Expired',                     'dark'],
        'cancelled'     => ['Cancelled',                   'dark'],
    ];

    [$label, $color] = $map[$canonical] ?? [ucfirst($status ?: 'Unknown'), 'secondary'];
    return badge($label, $color);
}

/**
 * Effective quotation status.
 * Uses the stored status as the source of truth. Auto-flags "expired" only
 * when the quotation is still awaiting response (sent) AND past valid_until.
 * Terminal statuses (approved / rejected / cancelled) are never overridden.
 */
function effective_quotation_status(array $quotation): string {
    $canonical = normalise_quotation_status($quotation['status'] ?? 'draft');

    // Terminal statuses — never auto-overridden
    if (in_array($canonical, ['approved', 'rejected', 'cancelled', 'expired', 're_evaluation'], true)) {
        return $canonical;
    }

    // Auto-expire a still-pending quotation that passed its validity date
    if ($canonical === 'sent'
        && !empty($quotation['valid_until'])
        && strtotime($quotation['valid_until']) < strtotime(date('Y-m-d'))) {
        return 'expired';
    }

    return $canonical;
}

/* ---------------------------------------------------------- *
 * Project progress
 * ---------------------------------------------------------- */
function recompute_project_progress(int $projectId): float {
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT AVG(progress_percent) AS avg FROM project_stages WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $avg = round((float)$stmt->fetchColumn(), 2);
    $upd = $pdo->prepare('UPDATE projects SET progress_percent = ? WHERE id = ?');
    $upd->execute([$avg, $projectId]);
    return $avg;
}

function project_stages(int $projectId): array {
    $stmt = db()->prepare('SELECT * FROM project_stages WHERE project_id = ? ORDER BY id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------- *
 * Quotation totals
 * ---------------------------------------------------------- */
function quotation_items(int $quotationId): array {
    $stmt = db()->prepare('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id');
    $stmt->execute([$quotationId]);
    return $stmt->fetchAll();
}

function quotation_totals(int $quotationId): array {
    $items = quotation_items($quotationId);
    $subtotal = array_sum(array_column($items, 'amount'));
    $q = db()->prepare('SELECT vat_rate FROM quotations WHERE id = ?');
    $q->execute([$quotationId]);
    $vatRate = (float)$q->fetchColumn();
    $vat     = $subtotal * $vatRate / 100;
    return ['items' => $items, 'subtotal' => $subtotal, 'vat_rate' => $vatRate, 'vat' => $vat, 'total' => $subtotal + $vat];
}

/* ---------------------------------------------------------- *
 * File uploads
 * ---------------------------------------------------------- */
function handle_upload(array $file, string $subdir, array $allowed): string {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file was uploaded.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('File exceeds the maximum allowed size of ' . round(MAX_UPLOAD_SIZE / 1048576) . ' MB.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('File type "' . $ext . '" is not allowed. Allowed: ' . implode(', ', $allowed) . '.');
    }

    if (strpos($subdir, 'photo') !== false) {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }
    }

    $dir = $subdir === 'documents' ? UPLOAD_DOCS_DIR : UPLOAD_PHOTOS_DIR;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $storedName = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file. Check folder permissions.');
    }
    return 'uploads/' . $subdir . '/' . $storedName;
}

/* ---------------------------------------------------------- *
 * Numbers & misc
 * ---------------------------------------------------------- */
function next_number(string $prefix, string $table, string $column): string {
    $stmt = db()->prepare("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    if (!$last) return $prefix . '-0001';
    $num = (int)substr($last, strrpos($last, '-') + 1);
    return $prefix . '-' . str_pad((string)($num + 1), 4, '0', STR_PAD_LEFT);
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}