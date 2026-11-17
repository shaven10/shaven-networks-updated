<?php
if (defined('SHAVEN_GUARD')) {
    return;
}
define('SHAVEN_GUARD', '1');
if (PHP_SAPI === 'cli') {
    return;
}
require_once __DIR__ . '/bootstrap.php';
shaven_web_boot(true);
shaven_theme_buffer();

function shaven_theme_buffer() {
    if (defined('SHAVEN_THEME_LEVEL')) {
        return;
    }
    define('SHAVEN_THEME_LEVEL', ob_get_level());
    ob_start();
    register_shutdown_function('shaven_theme_finish');
}

function shaven_theme_finish() {
    if (!defined('SHAVEN_THEME_LEVEL')) {
        return;
    }
    $parts = [];
    while (ob_get_level() > SHAVEN_THEME_LEVEL) {
        $chunk = ob_get_clean();
        if ($chunk === false) {
            break;
        }
        $parts[] = $chunk;
    }
    $html = implode('', array_reverse($parts));
    if ($html === '') {
        return;
    }
    if (stripos($html, '<html') !== false && stripos($html, 'shaven-theme.css') === false && stripos($html, '</head>') !== false) {
        $tag = '<link rel="stylesheet" href="' . h(shaven_url('dist/css/shaven-theme.css')) . '?v=' . (int)@filemtime(__DIR__ . '/../dist/css/shaven-theme.css') . '">';
        $html = preg_replace('/<\/head>/i', $tag . '</head>', $html, 1);
    }
    echo $html;
}
