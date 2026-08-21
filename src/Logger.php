<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Logger
{
    public function info(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function warn(string $message): void
    {
        fwrite(STDERR, 'warning: ' . $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, 'error: ' . $message . PHP_EOL);
    }
}
