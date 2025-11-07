<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

if (empty($_FILES['file']['name'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_file']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'upload_error']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_type']);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'too_large']);
    exit;
}

$uploadsDir = __DIR__ . '/../../assets/files/uploads/vehicles';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}
$basename = 'veh_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
$targetAbs = $uploadsDir . '/' . $basename;
if (!move_uploaded_file($file['tmp_name'], $targetAbs)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'move_failed']);
    exit;
}

$rel = '/assets/files/uploads/vehicles/' . $basename;
echo json_encode(['ok' => true, 'path' => $rel]);
