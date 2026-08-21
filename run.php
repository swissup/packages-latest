#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';

    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/src/' . strtr(substr($class, strlen($prefix)), '\\', '/') . '.php';
    }
});

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(Application::main($argv, __DIR__));
}
