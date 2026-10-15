<?php
/**
 * RG PMS - Centralised admin delete handler.
 * Accepts POST: type, id, redirect
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dashboard.php');
}
csrf_check();

$type     = trim($_POST['type'] ?? '');
$id       = (int)($_POST['id'] ?? 0);

// ---- Normalize redirect target down to a clean relative path ----
$raw = trim($_POST['redirect'] ?? '');

// Strip scheme + host
if (preg_match('#^https?://[^/]+(.*)$#', $raw, $m)) {
    $raw = $m[1];
}
// Strip BASE_URL prefix if present
if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($raw, BASE_URL)) {
    $raw = substr($raw, strlen(BASE_URL));
}
// Remove leading slash
$redirect = ltrim($raw, '/');

// Fallback / safety: block anything weird (protocol-relative, external, empty)
if ($redirect === '' || str_contains($redirect, '://') || str_starts_with($redirect, '//')) {
    $redirect = 'admin/dashboard.php';
}

if (!$id || !$type) {
    flash_set('error', 'Invalid delete request.');
    redirect($redirect);
}

$pdo = db();

// Whitelist of deletable resources - table name
$allowed = [
    'project'          => 'projects',
    'client'           => 'clients',
    'quotation'        => 'quotations',
    'invoice'          => 'invoices',
    'material'         => 'materials',
    'material_request' => 'material_requests',
    'personnel'        => 'personnel',
    'photo'            => 'photos',
    'gallery'          => 'gallery',
    'notification'     => 'notifications',
    'meeting'          => 'meetings',
];

if (!isset($allowed[$type])) {
    flash_set('error', 'Unknown resource type.');
    redirect($redirect);
}

$table = $allowed[$type];

try {
    $pdo->beginTransaction();

    // Cascade children first
    switch ($type) {
        case 'project':
            foreach (['quotation_items','invoice_items','material_requests','photos','meetings','cashflows','programmes','issues_escalations','monthly_reports'] as $t) {
                try { $pdo->prepare("DELETE FROM `$t` WHERE project_id = ?")->execute([$id]); } catch (Exception $e) {}
            }
            foreach (['quotations','invoices'] as $t) {
                try { $pdo->prepare("DELETE FROM `$t` WHERE project_id = ?")->execute([$id]); } catch (Exception $e) {}
            }
            try { $pdo->prepare("DELETE FROM project_personnel WHERE project_id = ?")->execute([$id]); } catch (Exception $e) {}
            break;

        case 'quotation':
            try { $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$id]); } catch (Exception $e) {}
            break;

        case 'invoice':
            try { $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]); } catch (Exception $e) {}
            break;

        case 'client':
            try { $pdo->prepare("UPDATE projects SET client_id = NULL WHERE client_id = ?")->execute([$id]); } catch (Exception $e) {}
            break;

        case 'personnel':
            // Also detach/delete the user account if linked
            try {
                $uid = $pdo->prepare("SELECT user_id FROM personnel WHERE id = ? LIMIT 1");
                $uid->execute([$id]);
                $userId = $uid->fetchColumn();
                if ($userId) {
                    // Only delete the user if they are NOT an admin
                    $check = $pdo->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
                    $check->execute([$userId]);
                    if ($check->fetchColumn() !== 'admin') {
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                    }
                }
            } catch (Exception $e) {}
            break;
    }

    // Delete the record itself
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    $label = ucfirst(str_replace('_', ' ', $type));
    flash_set('success', "{$label} deleted.");
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error', 'Delete failed: ' . $e->getMessage());
}

redirect($redirect);