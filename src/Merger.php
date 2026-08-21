<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Merger
{
    public static function merge(array $sources, array $carried = []): array
    {
        $packages = [];

        foreach ($sources as $source) {
            foreach ($source->packages as $name => $version) {
                $current = $packages[$name] ?? null;

                if ($current !== null
                    && version_compare($version['version_normalized'], $current['version_normalized'], '<')) {
                    continue;
                }

                $packages[$name] = $version;
            }
        }

        foreach ($carried as $name => $version) {
            $packages[$name] ??= $version;
        }

        ksort($packages, SORT_STRING);

        return $packages;
    }
}
