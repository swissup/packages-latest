<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Filesystem
{
    public static function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $directory));
        }

        $temporary = $path . '.' . getmypid() . '.tmp';

        if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException(sprintf('Cannot write "%s".', $path));
        }
    }

    public static function decode(string $json, string $origin): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(sprintf('%s: invalid JSON (%s).', $origin, $exception->getMessage()));
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('%s: expected a JSON object.', $origin));
        }

        return $decoded;
    }
}
