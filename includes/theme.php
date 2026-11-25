<?php

function shaven_theme_install($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_app_settings (
        setting_key VARCHAR(64) NOT NULL,
        setting_value TEXT NULL,
        updated_by INT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function shaven_theme_can_manage() {
    return shaven_role_is(1, 2);
}

function shaven_theme_color_keys() {
    return [
        'navy' => 'Sidebar and headers',
        'teal' => 'Accent (buttons, links, active menu)',
        'gold' => 'Highlight (menu icons, warning buttons)',
        'sand' => 'Page background',
    ];
}

function shaven_theme_presets() {
    return [
        'classic' => ['label' => 'SHAVEN Classic', 'colors' => ['navy' => '#0e2a3a', 'teal' => '#1f7a8c', 'gold' => '#c4a15a', 'sand' => '#f3f6f7']],
        'ocean' => ['label' => 'Ocean Blue', 'colors' => ['navy' => '#0b2545', 'teal' => '#1d63c4', 'gold' => '#f0a202', 'sand' => '#f2f5fa']],
        'forest' => ['label' => 'Forest Green', 'colors' => ['navy' => '#1b3a2b', 'teal' => '#2e7d4f', 'gold' => '#d4a017', 'sand' => '#f3f6f2']],
        'royal' => ['label' => 'Royal Purple', 'colors' => ['navy' => '#2a1b4a', 'teal' => '#6c4bc4', 'gold' => '#e0a73c', 'sand' => '#f5f3fa']],
        'crimson' => ['label' => 'Crimson', 'colors' => ['navy' => '#3a0f17', 'teal' => '#b0303d', 'gold' => '#d9a441', 'sand' => '#faf4f4']],
        'sunset' => ['label' => 'Sunset Orange', 'colors' => ['navy' => '#3d1f0f', 'teal' => '#d9622b', 'gold' => '#e9b949', 'sand' => '#fbf6f1']],
        'slate' => ['label' => 'Slate Gray', 'colors' => ['navy' => '#1f2933', 'teal' => '#52606d', 'gold' => '#f0b429', 'sand' => '#f4f5f7']],
    ];
}

function shaven_theme_hex($value) {
    $value = strtolower(trim((string)$value));
    return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
}

function shaven_theme_default() {
    $presets = shaven_theme_presets();
    return ['preset' => 'classic', 'colors' => $presets['classic']['colors']];
}

function shaven_theme_current($conn = null) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $conn = $conn ?: ($GLOBALS['conn'] ?? null);
    $theme = shaven_theme_default();
    if ($conn instanceof mysqli) {
        $rows = shaven_rows($conn, "SELECT setting_value FROM tbl_app_settings WHERE setting_key='theme' LIMIT 1");
        $saved = $rows ? json_decode((string)$rows[0]['setting_value'], true) : null;
        if (is_array($saved) && is_array($saved['colors'] ?? null)) {
            foreach (array_keys(shaven_theme_color_keys()) as $key) {
                $hex = shaven_theme_hex($saved['colors'][$key] ?? '');
                if ($hex) {
                    $theme['colors'][$key] = $hex;
                }
            }
            $theme['preset'] = (string)($saved['preset'] ?? 'custom');
            $theme['updated_at'] = (string)($saved['updated_at'] ?? '');
        }
    }
    $cache = $theme;
    return $cache;
}

function shaven_theme_css(array $colors) {
    $css = ':root{';
    foreach (array_keys(shaven_theme_color_keys()) as $key) {
        $hex = shaven_theme_hex($colors[$key] ?? '');
        if ($hex) {
            $css .= '--shaven-' . $key . ':' . $hex . ';';
        }
    }
    return $css . '}';
}

function shaven_theme_head() {
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    $theme = shaven_theme_current();
    echo '<style id="shaven-theme-vars">' . shaven_theme_css($theme['colors']) . '</style>' . "\n";
}

function shaven_theme_assets() {
    if (!empty($GLOBALS['shaven_theme_css_loaded'])) {
        shaven_theme_head();
        return;
    }
    $GLOBALS['shaven_theme_css_loaded'] = true;
    $file = SHAVEN_ROOT . '/dist/css/shaven-theme.css';
    echo '<link rel="stylesheet" href="' . h(shaven_url('dist/css/shaven-theme.css')) . '?v=' . (int)@filemtime($file) . '">' . "\n";
    shaven_theme_head();
}

function shaven_theme_save($conn, $presetKey, array $colors, $userId) {
    if (!shaven_theme_can_manage()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can change the theme.'];
    }
    $presets = shaven_theme_presets();
    $presetKey = (string)$presetKey;
    if ($presetKey !== 'custom' && isset($presets[$presetKey])) {
        $clean = $presets[$presetKey]['colors'];
    } else {
        $presetKey = 'custom';
        $clean = [];
        foreach (shaven_theme_color_keys() as $key => $label) {
            $hex = shaven_theme_hex($colors[$key] ?? '');
            if (!$hex) {
                return ['ok' => false, 'error' => 'Choose a valid color for ' . strtolower($label) . '.'];
            }
            $clean[$key] = $hex;
        }
    }
    $value = json_encode(['preset' => $presetKey, 'colors' => $clean, 'updated_at' => date('Y-m-d H:i:s')]);
    $saved = shaven_exec(
        $conn,
        "INSERT INTO tbl_app_settings (setting_key, setting_value, updated_by, updated_at) VALUES ('theme', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)",
        'si',
        [$value, (int)$userId]
    );
    if (!$saved['ok']) {
        return ['ok' => false, 'error' => 'The theme was not saved.'];
    }
    $label = $presetKey === 'custom' ? 'Custom colors' : $presets[$presetKey]['label'];
    return ['ok' => true, 'error' => $label . ' is now the color scheme for every account.'];
}
