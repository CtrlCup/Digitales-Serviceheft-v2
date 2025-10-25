<?php
declare(strict_types=1);

// ========================================
// 2FA (TOTP) Functions
// ========================================

/**
 * Generate a random secret for TOTP (base32 encoded)
 */
function generate_totp_secret(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Generate TOTP code from secret
 */
function generate_totp_code(string $secret, ?int $timestamp = null): string {
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    $key = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', floor($timestamp / 30));
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify TOTP code
 */
function verify_totp_code(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/[^0-9]/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    
    $timestamp = time();
    for ($i = -$window; $i <= $window; $i++) {
        $generatedCode = generate_totp_code($secret, $timestamp + ($i * 30));
        if (hash_equals($generatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Base32 decode function
 */
function base32_decode(string $secret): string {
    if (empty($secret)) {
        return '';
    }
    
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $paddingCharCount = substr_count($secret, '=');
    $allowedValues = [6, 4, 3, 1, 0];
    
    if (!in_array($paddingCharCount, $allowedValues)) {
        return '';
    }
    
    for ($i = 0; $i < 4; $i++) {
        if ($paddingCharCount === $allowedValues[$i] &&
            substr($secret, -($allowedValues[$i])) !== str_repeat('=', $allowedValues[$i])) {
            return '';
        }
    }
    
    $secret = str_replace('=', '', $secret);
    $secret = str_split($secret);
    $binaryString = '';
    
    for ($i = 0; $i < count($secret); $i = $i + 8) {
        $x = '';
        if (!in_array($secret[$i], str_split($chars))) {
            return '';
        }
        
        for ($j = 0; $j < 8; $j++) {
            $x .= str_pad(base_convert((string)strpos($chars, $secret[$i + $j]), 10, 2), 5, '0', STR_PAD_LEFT);
        }
        
        $eightBits = str_split($x, 8);
        for ($z = 0; $z < count($eightBits); $z++) {
            $binaryString .= (($y = chr((int)base_convert($eightBits[$z], 2, 10))) || ord($y) === 48) ? $y : '';
        }
    }
    
    return $binaryString;
}

/**
 * Generate QR code data URL for TOTP
 */
function generate_totp_qr_code(string $secret, string $email, string $issuer = APP_NAME): string {
    $otpauth = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        urlencode($issuer),
        urlencode($email),
        $secret,
        urlencode($issuer)
    );
    
    // Simple QR code generation using Google Charts API (deprecated but still works)
    // For production, use a proper QR library
    return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($otpauth);
}

/**
 * Generate recovery codes for 2FA
 */
function generate_recovery_codes(int $count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $code = '';
        for ($j = 0; $j < 8; $j++) {
            $code .= random_int(0, 9);
        }
        $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }
    return $codes;
}

/**
 * Check if user has 2FA enabled
 */
function user_has_2fa(int $userId): bool {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT enabled FROM user_2fa WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (bool)$row['enabled'];
}

/**
 * Get user's 2FA data
 */
function get_user_2fa(int $userId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM user_2fa WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Setup 2FA for user (create secret, not yet enabled)
 */
function setup_user_2fa(int $userId): string {
    $pdo = db();
    $secret = generate_totp_secret();
    $recoveryCodes = generate_recovery_codes();
    $recoveryCodesJson = json_encode($recoveryCodes);
    
    $stmt = $pdo->prepare('
        INSERT INTO user_2fa (user_id, totp_secret, enabled, recovery_codes, updated_at)
        VALUES (?, ?, 0, ?, NOW())
        ON DUPLICATE KEY UPDATE totp_secret = ?, recovery_codes = ?, updated_at = NOW()
    ');
    $stmt->execute([$userId, $secret, $recoveryCodesJson, $secret, $recoveryCodesJson]);
    
    return $secret;
}

/**
 * Enable 2FA for user after verification
 */
function enable_user_2fa(int $userId): void {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE user_2fa SET enabled = 1, updated_at = NOW() WHERE user_id = ?');
    $stmt->execute([$userId]);
}

/**
 * Disable 2FA for user
 */
function disable_user_2fa(int $userId): void {
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM user_2fa WHERE user_id = ?');
    $stmt->execute([$userId]);
}

/**
 * Verify recovery code
 */
function verify_recovery_code(int $userId, string $code): bool {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT recovery_codes FROM user_2fa WHERE user_id = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    
    if (!$row || empty($row['recovery_codes'])) {
        return false;
    }
    
    $codes = json_decode($row['recovery_codes'], true);
    if (!is_array($codes)) {
        return false;
    }
    
    $code = preg_replace('/[^0-9-]/', '', $code);
    $key = array_search($code, $codes, true);
    
    if ($key === false) {
        return false;
    }
    
    // Remove used code
    unset($codes[$key]);
    $codes = array_values($codes);
    
    $stmt = $pdo->prepare('UPDATE user_2fa SET recovery_codes = ?, updated_at = NOW() WHERE user_id = ?');
    $stmt->execute([json_encode($codes), $userId]);
    
    return true;
}

// ========================================
// Passkeys (WebAuthn) Functions
// ========================================

/**
 * Get user's passkeys
 */
function get_user_passkeys(int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT id, credential_id, name, last_used_at, created_at
        FROM webauthn_credentials
        WHERE user_id = ?
        ORDER BY created_at DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Add passkey for user
 */
function add_user_passkey(
    int $userId,
    string $credentialId,
    string $publicKey,
    int $signCount = 0,
    ?string $name = null,
    ?string $transports = null,
    ?string $aaguid = null,
    ?string $attestationFmt = null
): int {
    $pdo = db();
    $stmt = $pdo->prepare('
        INSERT INTO webauthn_credentials 
        (user_id, credential_id, public_key, sign_count, transports, aaguid, attestation_fmt, name, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $userId,
        $credentialId,
        $publicKey,
        $signCount,
        $transports,
        $aaguid,
        $attestationFmt,
        $name
    ]);
    
    return (int)$pdo->lastInsertId();
}

/**
 * Remove passkey
 */
function remove_user_passkey(int $userId, int $passkeyId): bool {
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?');
    $stmt->execute([$passkeyId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Get passkey by credential ID
 */
function get_passkey_by_credential_id(string $credentialId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT * FROM webauthn_credentials
        WHERE credential_id = ?
        LIMIT 1
    ');
    $stmt->execute([$credentialId]);
    return $stmt->fetch() ?: null;
}

/**
 * Update passkey last used timestamp
 */
function update_passkey_last_used(int $passkeyId): void {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE webauthn_credentials SET last_used_at = NOW() WHERE id = ?');
    $stmt->execute([$passkeyId]);
}

/**
 * Update passkey sign count
 */
function update_passkey_sign_count(int $passkeyId, int $signCount): void {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE webauthn_credentials SET sign_count = ? WHERE id = ?');
    $stmt->execute([$signCount, $passkeyId]);
}

/**
 * Generate challenge for WebAuthn
 * Returns base64url encoded challenge
 */
function generate_webauthn_challenge(): string {
    $bytes = random_bytes(32);
    // Return as base64url (not hex!)
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * Store challenge in session for verification
 */
function store_webauthn_challenge(string $challenge): void {
    $_SESSION['webauthn_challenge'] = $challenge;
    $_SESSION['webauthn_challenge_time'] = time();
}

/**
 * Get and validate stored challenge
 */
function get_webauthn_challenge(): ?string {
    if (empty($_SESSION['webauthn_challenge'])) {
        return null;
    }
    
    // Challenge expires after 5 minutes
    $challengeTime = $_SESSION['webauthn_challenge_time'] ?? 0;
    if (time() - $challengeTime > 300) {
        unset($_SESSION['webauthn_challenge']);
        unset($_SESSION['webauthn_challenge_time']);
        return null;
    }
    
    return $_SESSION['webauthn_challenge'];
}

/**
 * Clear stored challenge
 */
function clear_webauthn_challenge(): void {
    unset($_SESSION['webauthn_challenge']);
    unset($_SESSION['webauthn_challenge_time']);
}
