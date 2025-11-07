<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /overview');
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? '')) {
    $_SESSION['form_errors'] = [t('csrf_invalid')];
    $_SESSION['form_values'] = $_POST;
    $id = (int)($_POST['id'] ?? 0);
    header('Location: /vehicles/edit?id=' . $id);
    exit;
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /overview');
    exit;
}

$errors = [];

$vin = trim($_POST['vin'] ?? '');
$license_plate = trim($_POST['license_plate'] ?? '');
$make = trim($_POST['make'] ?? '');
$model = trim($_POST['model'] ?? '');
$year = trim($_POST['year'] ?? '');
$first_registration_raw = trim($_POST['first_registration'] ?? '');
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
$firstRegistration = null;
if ($first_registration_raw !== '') {
    $digits = preg_replace('/[^0-9]/', '', $first_registration_raw);
    if (strlen($digits) === 8) {
        $d = substr($digits, 0, 2);
        $m = substr($digits, 2, 2);
        $y = substr($digits, 4, 4);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            $firstRegistration = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
            $yearInt = (int)$y;
        } else {
            $errors[] = 'Erstzulassung ist kein gültiges Datum.';
        }
    } else {
        $errors[] = 'Erstzulassung muss im Format TT.MM.JJJJ eingegeben werden.';
    }
} elseif ($year !== '') {
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

// Optional image upload (only update if a new image is provided)
$profileImageRel = null;
// Prefer pre-uploaded path from AJAX endpoint if present
$preUploaded = trim($_POST['profile_image_path'] ?? '');
if ($preUploaded !== '' && strpos($preUploaded, '/assets/files/uploads/vehicles/') === 0) {
    $profileImageRel = $preUploaded;
}
// Support delete of current image
$deleteImage = isset($_POST['profile_image_delete']) && $_POST['profile_image_delete'] === '1';
if ($deleteImage) {
    // We'll set profile_image to NULL in DB. Also try removing file.
    $profileImageRel = ''; // special marker to clear column later
}

if (!$profileImageRel && !empty($_FILES['profile_image']['name'] ?? '')) {
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
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Bild darf maximal 5 MB groß sein.';
        } else {
            $ext = $allowed[$mime];
            $uploadsDir = __DIR__ . '/../../assets/files/uploads/vehicles';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0775, true);
            }
            $basename = 'veh_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetAbs = $uploadsDir . '/' . $basename;
            if (move_uploaded_file($file['tmp_name'], $targetAbs)) {
                $profileImageRel = '/assets/files/uploads/vehicles/' . $basename;
            }
        }
    }
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_values'] = $_POST;
    header('Location: /vehicles/edit?id=' . $id);
    exit;
}

try {
    $pdo = vehicle_db();

    // Ensure vehicle belongs to user (or is global when uid=0)
    $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1');
    $stmt->execute([$id, $uid, $uid]);
    if (!$stmt->fetch()) {
        $_SESSION['form_errors'] = ['Fahrzeug wurde nicht gefunden oder Zugriff verweigert.'];
        $_SESSION['form_values'] = $_POST;
        header('Location: /vehicles/edit?id=' . $id);
        exit;
    }

    // Check existing columns for safe update (profile_image compatibility)
    $colsStmt = $pdo->query('SHOW COLUMNS FROM vehicles');
    $existingCols = [];
    foreach (($colsStmt->fetchAll()) as $c) {
        $existingCols[] = is_array($c) && isset($c['Field']) ? (string)$c['Field'] : (string)($c[0] ?? '');
    }
    if (!in_array('profile_image', $existingCols, true)) {
        try {
            $pdo->exec("ALTER TABLE vehicles ADD COLUMN profile_image VARCHAR(512) NULL AFTER odometer_unit");
            $colsStmt = $pdo->query('SHOW COLUMNS FROM vehicles');
            $existingCols = [];
            foreach (($colsStmt->fetchAll()) as $c) {
                $existingCols[] = is_array($c) && isset($c['Field']) ? (string)$c['Field'] : (string)($c[0] ?? '');
            }
        } catch (Throwable $alterEx) {
            error_log('vehicles.profile_image ALTER failed: ' . $alterEx->getMessage());
        }
    }
    // Ensure first_registration column exists
    if (!in_array('first_registration', $existingCols, true)) {
        try {
            $pdo->exec("ALTER TABLE vehicles ADD COLUMN first_registration DATE NULL AFTER purchase_date");
            $colsStmt = $pdo->query('SHOW COLUMNS FROM vehicles');
            $existingCols = [];
            foreach (($colsStmt->fetchAll()) as $c) {
                $existingCols[] = is_array($c) && isset($c['Field']) ? (string)$c['Field'] : (string)($c[0] ?? '');
            }
        } catch (Throwable $alterEx) {
            error_log('vehicles.first_registration ALTER failed: ' . $alterEx->getMessage());
        }
    }

    // Build update set
    $fields = [
        'vin' => ($vin !== '' ? $vin : null),
        'license_plate' => ($license_plate !== '' ? $license_plate : null),
        'make' => $make,
        'model' => $model,
        'year' => $yearInt,
        'engine_code' => ($engine_code !== '' ? $engine_code : null),
        'fuel_type' => $fuel_type,
        'odometer_km' => $odoInt,
        'color' => ($color !== '' ? $color : null),
        'purchase_date' => ($purchase_date !== '' ? $purchase_date : null),
        'purchase_price' => $priceDec,
        'first_registration' => $firstRegistration,
        'notes' => ($notes !== '' ? $notes : null),
    ];

    if (in_array('profile_image', $existingCols, true)) {
        if ($profileImageRel === '') {
            // Clear image
            // Fetch old path to delete
            try {
                $oldStmt = $pdo->prepare('SELECT profile_image FROM vehicles WHERE id = ? LIMIT 1');
                $oldStmt->execute([$id]);
                $oldPath = (string)($oldStmt->fetch()['profile_image'] ?? '');
                if ($oldPath && strpos($oldPath, '/assets/files/uploads/vehicles/') === 0) {
                    $abs = __DIR__ . '/../../' . ltrim($oldPath, '/');
                    if (is_file($abs)) { @unlink($abs); }
                }
            } catch (Throwable $__) {}
            $fields['profile_image'] = null;
        } elseif ($profileImageRel) {
            $fields['profile_image'] = $profileImageRel;
        }
    }

    // Filter to existing columns
    $setParts = [];
    $params = [];
    foreach ($fields as $col => $val) {
        if (in_array($col, $existingCols, true)) {
            $setParts[] = "$col = ?";
            $params[] = $val;
        }
    }
    $params[] = $id;
    $params[] = $uid;
    $params[] = $uid;

    if (!$setParts) {
        header('Location: /vehicles/view?id=' . $id);
        exit;
    }

    $sql = 'UPDATE vehicles SET ' . implode(', ', $setParts) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header('Location: /vehicles/view?id=' . $id);
    exit;
} catch (Throwable $e) {
    error_log('Vehicle update failed: ' . $e->getMessage());
    $_SESSION['form_errors'] = ['Fahrzeug konnte nicht aktualisiert werden.', 'Technischer Fehler: ' . $e->getMessage()];
    $_SESSION['form_values'] = $_POST;
    header('Location: /vehicles/edit?id=' . $id);
    exit;
}
