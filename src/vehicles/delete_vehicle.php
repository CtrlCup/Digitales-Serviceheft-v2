<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard');
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? '')) {
    $_SESSION['form_errors'] = [t('csrf_invalid')];
    header('Location: /dashboard');
    exit;
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: /dashboard');
    exit;
}

try {
    $pdo = vehicle_db();

    // Verify ownership and get image path
    $stmt = $pdo->prepare('SELECT profile_image FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1');
    $stmt->execute([$id, $uid, $uid]);
    $row = $stmt->fetch();
    if (!$row) {
        $_SESSION['form_errors'] = [t('vehicle_not_found_or_forbidden')];
        header('Location: /dashboard');
        exit;
    }
    $imageRel = (string)($row['profile_image'] ?? '');

    $pdo->beginTransaction();

    // Best effort: remove related data if present (ignore missing tables)
    $relatedTables = [
        'service_entries', 'service_items', 'documents', 'reminders', 'fuel_logs',
        'parts_inventory', 'inspections', 'notes', 'vehicle_tags'
    ];
    foreach ($relatedTables as $tbl) {
        try {
            $pdo->prepare("DELETE FROM `$tbl` WHERE vehicle_id = ?")->execute([$id]);
        } catch (Throwable $ignored) {
            // Table may not exist yet; continue
        }
    }

    // Delete vehicle row
    $pdo->prepare('DELETE FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1')->execute([$id, $uid, $uid]);

    $pdo->commit();

    // Delete image file after commit (best effort)
    if (!empty($imageRel)) {
        // Only allow deletion inside our uploads dir for safety
        $uploadsBase = realpath(__DIR__ . '/../../assets/files/uploads/vehicles');
        $imageAbs = realpath(__DIR__ . '/../../' . ltrim($imageRel, '/'));
        if ($uploadsBase && $imageAbs && strpos($imageAbs, $uploadsBase) === 0 && is_file($imageAbs)) {
            @unlink($imageAbs);
        }
    }

    header('Location: /dashboard');
    exit;
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Vehicle delete failed: ' . $e->getMessage());
    $_SESSION['form_errors'] = [t('vehicle_delete_failed'), t('technical_error_prefix') . ' ' . $e->getMessage()];
    header('Location: /dashboard');
    exit;
}
