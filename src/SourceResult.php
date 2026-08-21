<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class SourceResult
{
    public readonly string $fingerprint;

    public function __construct(public readonly array $packages)
    {
        $this->fingerprint = self::fingerprint($packages);
    }

    // Identifies the selected releases, not the upstream file: a moved dev branch or a new
    // prerelease must not look like a change.
    private static function fingerprint(array $packages): string
    {
        $identity = [];

        foreach ($packages as $name => $version) {
            $identity[$name] = ($version['version'] ?? '') . '@' . ($version['source']['reference'] ?? '');
        }

        ksort($identity, SORT_STRING);

        return sha1((string) json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
