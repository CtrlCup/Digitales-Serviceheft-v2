<?php
declare(strict_types=1);

$i18n = [];

function load_locale(string $locale): void {
    global $i18n;
    $i18n = [];
    $norm = strtolower(preg_replace('/[^a-zA-Z_\-]/', '', $locale));
    if ($norm === '') { $norm = 'de'; }
    $lang = substr($norm, 0, 2);
    $paths = [];
    if ($lang !== 'de') { $paths[] = __DIR__ . '/../lang/de.php'; }
    $paths[] = __DIR__ . '/../lang/' . $lang . '.php';
    foreach ($paths as $p) {
        if (is_file($p)) {
            $arr = require $p;
            if (is_array($arr)) { $i18n = array_merge($i18n, $arr); }
        }
    }
}

function t(string $key): string {
    global $i18n;
    return isset($i18n[$key]) ? (string)$i18n[$key] : $key;
}

function available_locales(): array {
    $out = [];
    foreach (glob(__DIR__ . '/../lang/*.php') as $f) {
        $code = basename($f, '.php');
        $out[] = $code;
    }
    sort($out);
    return $out;
}
