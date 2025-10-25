<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

// Generate challenge
$challenge = generate_webauthn_challenge();
store_webauthn_challenge($challenge);

// Get domain for WebAuthn
$rpId = defined('APP_DOMAIN') && APP_DOMAIN ? APP_DOMAIN : ($_SERVER['HTTP_HOST'] ?? 'localhost');
// Remove port from HTTP_HOST if present
$rpId = explode(':', $rpId)[0];

// Create options for credential request
$options = [
    'challenge' => $challenge,
    'timeout' => 60000,
    'rpId' => $rpId,
    'userVerification' => 'preferred'
];

echo json_encode($options);
