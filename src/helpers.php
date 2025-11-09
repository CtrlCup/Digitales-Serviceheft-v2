<?php
declare(strict_types=1);

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_common_head_links(): void {
    // Get base URL for assets (works from any directory depth)
    $base = '/';
    // Hide browser loading indicators
    echo '<style>::-webkit-progress-bar,::-webkit-progress-value{display:none!important}progress,[role="progressbar"]{display:none!important}</style>';
    echo '<link rel="icon" type="image/svg+xml" href="' . $base . 'assets/files/favicon.svg">';
    echo '<link rel="shortcut icon" href="' . $base . 'assets/files/favicon.svg">';
    echo '<link rel="apple-touch-icon" href="' . $base . 'assets/files/favicon.svg">';
    echo '<link rel="stylesheet" href="' . $base . 'assets/css/app.css">';
    echo '<script defer src="' . $base . 'assets/js/theme.js"></script>';
}

function render_i18n_for_js(array $keys = []): void {
    // Gibt ausgewählte Übersetzungen als globales JS-Objekt aus
    $translations = [];
    foreach ($keys as $key) {
        $translations[$key] = t($key);
    }
    echo '<script>window.__i18n = ' . json_encode($translations, JSON_UNESCAPED_UNICODE) . ';</script>';
}

function get_time_based_greeting(): string {
    // Gibt zeitabhängige Begrüßung zurück basierend auf Uhrzeit
    $hour = (int)date('G'); // 0-23
    
    if ($hour >= 5 && $hour < 12) {
        return t('good_morning');
    } elseif ($hour >= 12 && $hour < 18) {
        return t('good_day');
    } else {
        return t('good_evening');
    }
}

function render_brand_header(array $options = []): void {
    $links = $options['links'] ?? [];
    $cta = $options['cta'] ?? null;
    $showTheme = $options['showTheme'] ?? true;
    
    echo '<header class="app-header">';
    echo   '<div class="header-container">';
    echo     '<div class="header-left">';
    echo       '<a href="/" class="logo-link">';
    echo         '<img src="/assets/files/logo-light.svg" alt="Logo" class="logo-icon logo-light">';
    echo         '<img src="/assets/files/logo-dark.svg" alt="Logo" class="logo-icon logo-dark">';
    echo         '<span class="logo-text">' . e(APP_NAME) . '</span>';
    echo       '</a>';
    echo     '</div>';
    echo     '<div class="header-right">';
    
    // CTA Button (prominent)
    if ($cta) {
        $ctaLabel = $cta['label'] ?? '';
        $ctaHref = $cta['href'] ?? '#';
        echo '<a href="' . e($ctaHref) . '" class="btn-cta">' . e($ctaLabel) . '</a>';
    }
    
    // Navigation links as icon buttons
    foreach ($links as $link) {
        $label = $link['label'] ?? '';
        $href = $link['href'] ?? '#';
        $icon = $link['icon'] ?? null;
        $text = $link['text'] ?? null; // Optionaler Text neben Icon
        
        if ($icon) {
            $btnClass = $text ? 'header-link-with-text' : 'icon-btn';
            echo '<a href="' . e($href) . '" class="' . $btnClass . '" aria-label="' . e($label) . '" title="' . e($label) . '">';
            echo icon_svg($icon);
            if ($text) {
                echo '<span class="link-text">' . e($text) . '</span>';
            }
            echo '</a>';
        }
    }
    
    // Theme toggle icon button
    if ($showTheme) {
        echo '<button id="theme-toggle" class="icon-btn theme-toggle" aria-label="' . e(t('toggle_theme')) . '" title="' . e(t('toggle_theme')) . '">';
        echo   '<span class="theme-icon-light">' . icon_svg('sun') . '</span>';
        echo   '<span class="theme-icon-dark">' . icon_svg('moon') . '</span>';
        echo '</button>';
    }
    
    echo     '</div>';
    echo   '</div>';
    echo '</header>';
}

function icon_svg(string $name): string {
    $icons = [
        'sun' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32l1.41-1.41"/></svg>',
        'moon' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
        'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'logout' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'settings' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1m16.24-3.76l-4.24 4.24M7.76 16.24l-4.24 4.24m16.24 0l-4.24-4.24M7.76 7.76L3.52 3.52"/></svg>',
        'lock' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><circle cx="12" cy="16" r="1"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'unlock' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><circle cx="12" cy="16" r="1"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>',
        'edit' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'key' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.778-7.778zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>',
        'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
        'at' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>',
        'plus' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'car-plus' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13l2-5a2 2 0 0 1 1.87-1.25h8.26A2 2 0 0 1 17 8l2 5"/><path d="M5 13h12"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/><path d="M2 16.5h3"/><path d="M19 16.5h3"/><path d="M20 3v4"/><path d="M18 5h4"/></svg>',
    ];
    return $icons[$name] ?? '';
}
