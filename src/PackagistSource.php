<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class PackagistSource
{
    public const LABEL = 'packagist.org';

    private const PACKAGE_LIST = 'https://packagist.org/packages/list.json?vendor=swissup';
    private const METADATA = 'https://repo.packagist.org/p2/%s.json';

    public function __construct(private readonly HttpClient $http, private readonly Logger $log)
    {
    }

    public function load(): SourceResult
    {
        $names = $this->names();
        $this->log->info(sprintf('  %s: fetching metadata for %d packages', self::LABEL, count($names)));

        $urls = [];
        foreach ($names as $name) {
            $urls[$name] = sprintf(self::METADATA, $name);
        }

        $latest = [];

        foreach ($this->http->getMany($urls) as $name => $body) {
            $versions = Filesystem::decode($body, $urls[$name])['packages'][$name] ?? null;

            if (!is_array($versions) || $versions === []) {
                continue;
            }

            $best = Version::latestStable(Version::expandMinified(array_values($versions)));

            if ($best !== null) {
                $latest[$name] = $best;
            }
        }

        ksort($latest, SORT_STRING);

        return new SourceResult($latest);
    }

    private function names(): array
    {
        $decoded = Filesystem::decode($this->http->get(self::PACKAGE_LIST), self::PACKAGE_LIST);
        $names = $decoded['packageNames'] ?? null;

        if (!is_array($names) || $names === []) {
            throw new \RuntimeException(sprintf('%s: empty package list.', self::PACKAGE_LIST));
        }

        $names = array_map('strval', $names);
        sort($names, SORT_STRING);

        return $names;
    }
}
