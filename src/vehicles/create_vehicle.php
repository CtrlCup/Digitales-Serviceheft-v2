<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /vehicles/create');
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? '')) {
    $errors[] = t('csrf_invalid');
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);

$vin = trim($_POST['vin'] ?? '');
$license_plate = trim($_POST['license_plate'] ?? '');
$make = trim($_POST['make'] ?? '');
$model = trim($_POST['model'] ?? '');
$year = trim($_POST['year'] ?? '');
$engine_code = trim($_POST['engine_code'] ?? '');
$fuel_type = trim($_POST['fuel_type'] ?? 'petrol');
$odometer_km = trim($_POST['odometer_km'] ?? '');
$color = trim($_POST['color'] ?? '');
$purchase_date = trim($_POST['purchase_date'] ?? '');
$purchase_price = trim($_POST['purchase_price'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($make === '') { $errors[] = 'Bitte Hersteller angeben.'; }
if ($model === '') { $errors[] = 'Bitte Modell angeben.'; }
if ($fuel_type === '' || !in_array($fuel_type, ['petrol','diesel','electric','hybrid','lpg','cng','hydrogen','other'], true)) {
    $errors[] = 'Bitte einen gültigen Kraftstofftyp wählen.';
}

$yearInt = null;
if ($year !== '') {
    if (!preg_match('/^\d{4}$/', $year)) {
        $errors[] = 'Bitte ein gültiges Baujahr (YYYY) eingeben.';
    } else {
        $yearInt = (int)$year;
    }
}

$odoInt = null;
if ($odometer_km !== '') {
    if (!preg_match('/^\d+$/', $odometer_km)) {
        $errors[] = 'Kilometerstand muss eine Zahl sein.';
    } else {
        $odoInt = (int)$odometer_km;
    }
}

$priceDec = null;
if ($purchase_price !== '') {
    if (!preg_match('/^\d+(?:[\.,]\d{1,2})?$/', $purchase_price)) {
        $errors[] = 'Kaufpreis muss eine Zahl mit bis zu zwei Nachkommastellen sein.';
    } else {
        $priceDec = str_replace(',', '.', $purchase_price);
    }
}

// Handle optional image upload
$profileImageRel = null;
if (!empty($_FILES['profile_image']['name'] ?? '')) {
    $file = $_FILES['profile_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Bild-Upload fehlgeschlagen.';
    } else {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!isset($allowed[$mime])) {
            $errors[] = 'Nur JPG, PNG oder WEBP sind erlaubt.';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors[] = 'Bild darf maximal 5 MB groß sein.';
        }
    }
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_values'] = $_POST;
    header('Location: /vehicles/create');
    exit;
}

try {
    $pdo = vehicle_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS vehicles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        vin VARCHAR(64) NULL,
        license_plate VARCHAR(32) NULL,
        make VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        trim_level VARCHAR(100) NULL,
        year SMALLINT UNSIGNED NULL,
        engine_code VARCHAR(64) NULL,
        fuel_type ENUM(\'petrol\',\'diesel\',\'electric\',\'hybrid\',\'lpg\',\'cng\',\'hydrogen\',\'other\') NOT NULL DEFAULT \'petrol\',
        color VARCHAR(64) NULL,
        odometer_km INT UNSIGNED NULL,
        odometer_unit ENUM(\'km\',\'mi\') NOT NULL DEFAULT \'km\',
        profile_image VARCHAR(512) NULL,
        purchase_date DATE NULL,
        purchase_price DECIMAL(10,2) NULL,
        purchase_mileage_km INT UNSIGNED NULL,
        sale_date DATE NULL,
        sale_price DECIMAL(10,2) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vehicles_vin (vin),
        KEY idx_vehicles_license_plate (license_plate),
        KEY idx_vehicles_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

    // If image validated, move to uploads
    if (!empty($_FILES['profile_image']['name'] ?? '') && empty($_SESSION['form_errors'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
        finfo_close($finfo);
        $ext = $allowed[$mime] ?? null;
        if ($ext) {
            $uploadsDir = __DIR__ . '/../../assets/files/uploads/vehicles';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0775, true);
            }
            $basename = 'veh_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetAbs = $uploadsDir . '/' . $basename;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetAbs)) {
                $profileImageRel = '/assets/files/uploads/vehicles/' . $basename;
            }
        }
    }

    // Build a safe INSERT based on existing columns to avoid unknown column errors
    $colsStmt = $pdo->query('SHOW COLUMNS FROM vehicles');
    $existingCols = [];
    foreach (($colsStmt->fetchAll()) as $c) {
        $existingCols[] = is_array($c) && isset($c['Field']) ? (string)$c['Field'] : (string)($c[0] ?? '');
    }
    // If profile_image column is missing (older schema), add it
    if (!in_array('profile_image', $existingCols, true)) {
        try {
            $pdo->exec("ALTER TABLE vehicles ADD COLUMN profile_image VARCHAR(512) NULL AFTER odometer_unit");
            // refresh columns
            $colsStmt = $pdo->query('SHOW COLUMNS FROM vehicles');
            $existingCols = [];
            foreach (($colsStmt->fetchAll()) as $c) {
                $existingCols[] = is_array($c) && isset($c['Field']) ? (string)$c['Field'] : (string)($c[0] ?? '');
            }
        } catch (Throwable $alterEx) {
            // If ALTER fails due to permissions, continue without storing image path
            error_log('vehicles.profile_image ALTER failed: ' . $alterEx->getMessage());
        }
    }

    $data = [
        'user_id' => $uid ?: null,
        'vin' => $vin !== '' ? $vin : null,
        'license_plate' => $license_plate !== '' ? $license_plate : null,
        'make' => $make,
        'model' => $model,
        'year' => $yearInt,
        'engine_code' => $engine_code !== '' ? $engine_code : null,
        'fuel_type' => $fuel_type,
        'odometer_km' => $odoInt,
        'color' => $color !== '' ? $color : null,
        'purchase_date' => $purchase_date !== '' ? $purchase_date : null,
        'purchase_price' => $priceDec,
        'notes' => $notes !== '' ? $notes : null,
        'profile_image' => $profileImageRel,
    ];

    $insertCols = [];
    $placeholders = [];
    $values = [];
    foreach ($data as $col => $val) {
        if (in_array($col, $existingCols, true)) {
            $insertCols[] = $col;
            $placeholders[] = '?';
            $values[] = $val;
        }
    }

    $sql = 'INSERT INTO vehicles (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    header('Location: /overview');
    exit;
} catch (Throwable $e) {
    error_log('Vehicle create failed: ' . $e->getMessage());
    $_SESSION['form_errors'] = ['Fahrzeug konnte nicht gespeichert werden.', 'Technischer Fehler: ' . $e->getMessage()];
    $_SESSION['form_values'] = $_POST;
    header('Location: /vehicles/create');
    exit;
}
