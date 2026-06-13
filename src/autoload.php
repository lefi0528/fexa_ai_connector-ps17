<?php

/**
 * Copyright (c) 2025 Fexa AI — All Rights Reserved.
 *
 * Minimal PSR-4 autoloader for the PrestaShop 1.7 / PHP 7.4 build (no Composer).
 * Maps PrestaShop\Module\FexaAiConnector\* to this src/ directory.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'PrestaShop\\Module\\FexaAiConnector\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
