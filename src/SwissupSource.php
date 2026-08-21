<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class SwissupSource
{
    public const LABEL = 'swissup.github.io';

    private const REPOSITORY = 'https://swissup.github.io/packages/';

    public function __construct(private readonly HttpClient $http, private readonly Logger $log)
    {
    }

    public function load(string $include): SourceResult
    {
        $packages = Filesystem::decode($this->fetchInclude($include), $include)['packages'] ?? [];

        if (!is_array($packages) || $packages === []) {
            throw new \RuntimeException(sprintf('%s: malformed "packages" map.', $include));
        }

        $latest = [];

        foreach ($packages as $name => $versions) {
            $best = is_array($versions) ? Version::latestStable($versions) : null;

            if ($best !== null) {
                $latest[(string) $name] = $best;
            }
        }

        ksort($latest, SORT_STRING);

        return new SourceResult($latest);
    }

    public function advertisedInclude(): string
    {
        $url = self::REPOSITORY . 'packages.json';
        $includes = Filesystem::decode($this->http->get($url), $url)['includes'] ?? [];

        if (!is_array($includes) || count($includes) !== 1) {
            throw new \RuntimeException(sprintf('%s: expected exactly one "includes" entry.', $url));
        }

        return (string) array_key_first($includes);
    }

    private function fetchInclude(string $include): string
    {
        if (preg_match('/\ball\$([0-9a-f]{40})\.json$/', $include, $match) !== 1) {
            throw new \RuntimeException(sprintf('Include "%s" does not name the sha1 of its contents.', $include));
        }

        $url = self::REPOSITORY . $include;
        $this->log->info(sprintf('  %s: downloading %s', self::LABEL, basename($include)));
        $body = $this->http->get($url);

        if (sha1($body) !== $match[1]) {
            throw new \RuntimeException(sprintf('%s: sha1 mismatch, got %s expected %s.', $url, sha1($body), $match[1]));
        }

        return $body;
    }
}
