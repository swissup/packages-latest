<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Version
{
    private const MODIFIER = '[._-]?(?:(stable|beta|b|RC|rc|alpha|a|patch|pl|p)((?:[.-]?\d+)*+)?)?([.-]?dev)?';

    public static function stability(string $version): string
    {
        $version = (string) preg_replace('{#.+$}', '', $version);

        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return 'dev';
        }

        preg_match('{' . self::MODIFIER . '(?:\+.*)?$}i', strtolower($version), $match);

        if (!empty($match[3])) {
            return 'dev';
        }

        return match ($match[1] ?? '') {
            'beta', 'b' => 'beta',
            'alpha', 'a' => 'alpha',
            'rc' => 'RC',
            default => 'stable',
        };
    }

    public static function isStable(string $version): bool
    {
        return self::stability($version) === 'stable';
    }

    // Composer v2 minified metadata: entries after the first are diffs, and the literal
    // value "__unset" removes a key.
    public static function expandMinified(array $versions): \Generator
    {
        $expanded = null;

        foreach ($versions as $version) {
            if ($expanded === null) {
                $expanded = $version;
            } else {
                foreach ($version as $key => $value) {
                    if ($value === '__unset') {
                        unset($expanded[$key]);
                    } else {
                        $expanded[$key] = $value;
                    }
                }
            }

            yield $expanded;
        }
    }

    public static function latestStable(iterable $versions): ?array
    {
        $latest = null;

        foreach ($versions as $version) {
            $normalized = $version['version_normalized'] ?? null;

            if (!is_string($normalized) || !self::isStable($normalized)) {
                continue;
            }

            if ($latest === null || version_compare($normalized, $latest['version_normalized'], '>')) {
                $latest = $version;
            }
        }

        return $latest;
    }
}
