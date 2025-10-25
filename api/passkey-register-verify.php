<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Verify challenge
    $storedChallenge = get_webauthn_challenge();
    if (!$storedChallenge) {
        throw new Exception('No challenge found or challenge expired');
    }
    
    // Decode client data
    $clientDataJSON = base64_decode(strtr($data['response']['clientDataJSON'], '-_', '+/'));
    $clientData = json_decode($clientDataJSON, true);
    
    if (!$clientData) {
        throw new Exception('Invalid client data');
    }
    
    // Verify challenge matches
    $receivedChallenge = $clientData['challenge'] ?? '';
    
    // Both stored and received challenges are in base64url format
    if (!hash_equals($storedChallenge, $receivedChallenge)) {
        throw new Exception('Challenge mismatch');
    }
    
    // Verify origin
    $origin = $clientData['origin'] ?? '';
    $domain = defined('APP_DOMAIN') && APP_DOMAIN ? APP_DOMAIN : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Remove port if present
    $domain = explode(':', $domain)[0];
    $expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $domain;
    
    // If using non-standard ports, add them back to expected origin
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') {
        $expectedOrigin .= ':' . $_SERVER['SERVER_PORT'];
    }
    
    if ($origin !== $expectedOrigin) {
        throw new Exception('Origin mismatch: expected ' . $expectedOrigin . ', got ' . $origin);
    }
    
    // Decode attestation object
    $attestationObject = base64_decode(strtr($data['response']['attestationObject'], '-_', '+/'));
    
    // Parse CBOR (simplified - in production use a proper CBOR library)
    // For now, we'll just store the attestation object and extract basic info
    
    // Store credential
    $credentialId = base64_decode(strtr($data['rawId'], '-_', '+/'));
    $name = $data['name'] ?? 'Passkey';
    
    // In a production environment, you would:
    // 1. Parse the attestation object properly using a CBOR library
    // 2. Extract the public key
    // 3. Verify the attestation signature
    // 4. Store the public key for future authentication
    
    // For this implementation, we'll store the raw data
    $publicKeyData = base64_encode($attestationObject);
    
    add_user_passkey(
        (int)$user['id'],
        $credentialId,
        $publicKeyData,
        0,
        $name,
        null,
        null,
        'none'
    );
    
    // Clear challenge
    clear_webauthn_challenge();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
