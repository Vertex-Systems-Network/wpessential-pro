<?php

declare(strict_types=1);

/*
 * WPEssential Pro integration fixtures run against a checkout of the Free plugin
 * (Vertex-Systems-Network/wpessential). Point WPE_FREE_ROOT at it; the default is a
 * sibling checkout at ../wpessential. Free Platform/Kernel/Contracts/Bootstrap classes
 * and the Free CPT/Taxonomy modules autoload from there; Pro modules load from this repo.
 */
if (!defined('WPE_FREE_ROOT')) {
    $wpeFreeRoot = getenv('WPE_FREE_ROOT');
    if (!is_string($wpeFreeRoot) || $wpeFreeRoot === '') {
        $wpeFreeRoot = dirname(__DIR__, 3) . '/wpessential';
    }
    $wpeFreeRoot = rtrim($wpeFreeRoot, '/\\');
    if (!is_file($wpeFreeRoot . '/wpessential.php')) {
        fwrite(STDERR, "FAIL: WPEssential Free checkout not found at {$wpeFreeRoot}; set WPE_FREE_ROOT.\n");
        exit(1);
    }
    define('WPE_FREE_ROOT', $wpeFreeRoot);
    unset($wpeFreeRoot);

    spl_autoload_register(static function (string $class): void {
        $prefix = 'WPEssential\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $path = WPE_FREE_ROOT . '/frameworks/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}
