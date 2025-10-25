<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Generate challenge
$challenge = generate_webauthn_challenge();
store_webauthn_challenge($challenge);

// Generate user ID (use database ID as base)
$userId = base64_encode(hash('sha256', (string)$user['id'], true));

// Get domain for WebAuthn
$rpId = defined('APP_DOMAIN') && APP_DOMAIN ? APP_DOMAIN : ($_SERVER['HTTP_HOST'] ?? 'localhost');
// Remove port from HTTP_HOST if present
$rpId = explode(':', $rpId)[0];

// Create options for credential creation
$options = [
    'challenge' => $challenge,
    'rp' => [
        'name' => APP_NAME,
        'id' => $rpId
    ],
    'user' => [
        'id' => $userId,
        'name' => $user['email'],
        'displayName' => $user['name']
    ],
    'pubKeyCredParams' => [
        ['type' => 'public-key', 'alg' => -7],  // ES256
        ['type' => 'public-key', 'alg' => -257] // RS256
    ],
    'timeout' => 60000,
    'attestation' => 'none',
    'authenticatorSelection' => [
        // 'authenticatorAttachment' => 'platform', // Removed to allow external authenticators like Bitwarden
        'requireResidentKey' => true, // Required for discoverable credentials
        'residentKey' => 'required',
        'userVerification' => 'preferred'
    ]
];

echo json_encode($options);
