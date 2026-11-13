<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wordpress-stub/');
}

// Free (wpessential/wpessential) is a dev dependency: its autoloader serves the
// Platform/Kernel/Contracts classes and this package's PSR-4 map serves the Pro modules.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
