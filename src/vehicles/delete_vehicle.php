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
    $spdo = service_db();

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
    $spdo->beginTransaction();

    // Best effort: remove related data if present (ignore missing tables)
    // Service data lives in service_db()
    try {
        // Delete service items via join on service_entries filtered by vehicle_id
        $spdo->prepare('DELETE si FROM service_items si JOIN service_entries se ON se.id = si.entry_id WHERE se.vehicle_id = ?')->execute([$id]);
        $spdo->prepare('DELETE FROM service_entries WHERE vehicle_id = ?')->execute([$id]);
    } catch (Throwable $ignored) {}

    // Other related tables that live with vehicles in vehicle_db()
    $relatedVehTables = [
        'documents', 'reminders', 'fuel_logs', 'parts_inventory', 'inspections', 'notes', 'vehicle_tags'
    ];
    foreach ($relatedVehTables as $tbl) {
        try {
            $pdo->prepare("DELETE FROM `$tbl` WHERE vehicle_id = ?")->execute([$id]);
        } catch (Throwable $ignored) {
            // Table may not exist yet; continue
        }
    }

    // Delete vehicle row
    $pdo->prepare('DELETE FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1')->execute([$id, $uid, $uid]);

    $pdo->commit();
    $spdo->commit();

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
    if (isset($spdo) && $spdo && $spdo->inTransaction()) {
        $spdo->rollBack();
    }
    error_log('Vehicle delete failed: ' . $e->getMessage());
    $_SESSION['form_errors'] = [t('vehicle_delete_failed'), t('technical_error_prefix') . ' ' . $e->getMessage()];
    header('Location: /dashboard');
    exit;
}
