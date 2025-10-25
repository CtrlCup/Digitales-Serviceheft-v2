<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

// Must be logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = current_user();
$type = $_GET['type'] ?? ''; // 'username' or 'email'
$value = trim($_GET['value'] ?? '');

if (!in_array($type, ['username', 'email'], true) || empty($value)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    $pdo = db();
    
    // Check if value is taken by someone else (not by current user)
    if ($type === 'username') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    }
    
    $stmt->execute([$value, (int)$user['id']]);
    $exists = $stmt->fetch();
    
    echo json_encode([
        'available' => !$exists,
        'type' => $type,
        'value' => $value
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
