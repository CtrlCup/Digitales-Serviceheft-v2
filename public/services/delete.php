<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
require_auth();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        throw new RuntimeException(t('csrf_invalid'));
    }

    $user = current_user();
    $uid = (int)($user['id'] ?? 0);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new InvalidArgumentException('Invalid ID');
    }

    $spdo = service_db();
    $vpdo = vehicle_db();

    // Load entry
    $stmt = $spdo->prepare('SELECT id, vehicle_id, total_amount, odometer_km FROM service_entries WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $entry = $stmt->fetch();
    if (!$entry) {
        throw new RuntimeException(t('service_not_found') ?? 'Service not found');
    }
    $vehicleId = (int)$entry['vehicle_id'];

    // Ownership check via vehicles table
    $v = $vpdo->prepare('SELECT id, user_id FROM vehicles WHERE id = ? LIMIT 1');
    $v->execute([$vehicleId]);
    $veh = $v->fetch();
    if (!$veh || (int)($veh['user_id'] ?? 0) !== $uid) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    // Delete items first (if any), then entry
    try { $spdo->prepare('DELETE FROM service_items WHERE entry_id = ?')->execute([$id]); } catch (Throwable $ignored) {}
    $spdo->prepare('DELETE FROM service_entries WHERE id = ?')->execute([$id]);

    // Recalculate vehicle total_spent (best-effort)
    try {
        $sumStmt = $spdo->prepare('SELECT SUM(se.total_amount) AS s FROM service_entries se WHERE se.vehicle_id = ?');
        $sumStmt->execute([$vehicleId]);
        $row = $sumStmt->fetch();
        $sum = (float)($row['s'] ?? 0.0);
        $vpdo->prepare('UPDATE vehicles SET total_spent = ? WHERE id = ?')->execute([$sum, $vehicleId]);
    } catch (Throwable $ignored) {}

    $_SESSION['form_success'] = [t('service_deleted_success')];
    header('Location: /vehicles/view.php?id=' . urlencode((string)$vehicleId));
    exit;
} catch (Throwable $e) {
    @error_log('[services/delete] ' . $e->getMessage());
    $_SESSION['form_errors'] = [$e->getMessage()];
    $redirect = '/dashboard';
    if (!empty($entry['vehicle_id'])) { $redirect = '/vehicles/view.php?id=' . urlencode((string)$entry['vehicle_id']); }
    header('Location: ' . $redirect);
    exit;
}
