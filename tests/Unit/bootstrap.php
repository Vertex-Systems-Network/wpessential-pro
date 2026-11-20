<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wordpress-stub/');
}

// Free (wpessential/wpessential) is a dev dependency: its autoloader serves the
// Platform/Kernel/Contracts classes and this package's PSR-4 map serves the Pro modules.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!function_exists('wp_strip_all_tags')) {
    // Minimal stand-in for the WordPress function used when sanitizing labels.
    function wp_strip_all_tags(string $text): string
    {
        return trim(strip_tags((string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text)));
    }
}
