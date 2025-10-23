<?php
declare(strict_types=1);

$i18n = [];

function load_locale(string $locale): void {
    global $i18n;
    $file = __DIR__ . '/../lang/' . basename($locale) . '.php';
    if (is_file($file)) {
        $i18n = require $file;
    }
}

function t(string $key): string {
    global $i18n;
    return $i18n[$key] ?? $key;
}
