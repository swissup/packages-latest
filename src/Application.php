<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Application
{
    public function __construct(private readonly Options $options, private readonly Logger $log)
    {
    }

    public function run(): int
    {
        $started = hrtime(true);

        $http = new HttpClient($this->log);
        $swissup = new SwissupSource($http, $this->log);
        $packagist = new PackagistSource($http, $this->log);
        $builder = new RepositoryBuilder($this->options->output);

        $previous = $builder->previous();

        $remoteInclude = $swissup->advertisedInclude();

        $reuse = !$this->options->force
            && $previous !== null
            && $previous->state(SwissupSource::LABEL)->isBasedOn($remoteInclude);

        $swissupResult = $reuse ? null : $swissup->load($remoteInclude);
        $swissupFingerprint = $swissupResult?->fingerprint
            ?? $previous->state(SwissupSource::LABEL)->fingerprint;

        if ($reuse) {
            $this->log->info(sprintf(
                '  %s: %s unchanged, reusing %d selections',
                SwissupSource::LABEL,
                basename($remoteInclude),
                count($previous->packages)
            ));
        }

        $packagistResult = $packagist->load();

        $changed = array_keys(array_filter([
            SwissupSource::LABEL => $swissupFingerprint
                !== $previous?->state(SwissupSource::LABEL)->fingerprint,
            PackagistSource::LABEL => $packagistResult->fingerprint
                !== $previous?->state(PackagistSource::LABEL)->fingerprint,
        ]));

        $loaded = array_filter([
            SwissupSource::LABEL => $swissupResult,
            PackagistSource::LABEL => $packagistResult,
        ]);

        // Carried forward only where a source was reused; with everything loaded the merge is
        // authoritative and filling gaps would revive withdrawn packages.
        $packages = Merger::merge($loaded, $reuse ? $previous->packages : []);

        if ($packages === []) {
            throw new \RuntimeException('Refusing to write an empty repository.');
        }

        $renderer = new IndexRenderer($this->options->output);
        $scannedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $rebuild = $this->options->force || $changed !== [] || $previous === null;

        if ($rebuild) {
            $written = $builder->writeIncludeJson($packages);
            $include = $written['include'];
            $sha1 = $written['sha1'];
            $generatedAt = $scannedAt;
            $this->log->info(sprintf(
                'Rebuilt from %s: wrote %s (%d packages, %s)',
                $changed === [] ? 'all sources' : implode(' and ', $changed),
                $include,
                count($packages),
                self::size($written['bytes'])
            ));
        } else {
            $include = $previous->include;
            $sha1 = $previous->sha1;
            $generatedAt = $renderer->previousGeneratedAt() ?? $scannedAt;
            $this->log->info('Already up to date; pass --force to rebuild.');
        }

        $builder->writePackagesJson($include, $sha1, [
            SwissupSource::LABEL => new SourceState($swissupFingerprint, $remoteInclude),
            PackagistSource::LABEL => new SourceState($packagistResult->fingerprint),
        ]);

        $indexBytes = $renderer->render($packages, $include, $scannedAt, $generatedAt);
        $this->log->info(sprintf('Wrote index.html (%s)', self::size($indexBytes)));

        $this->reportRequests($http, $started);

        return 0;
    }

    private function reportRequests(HttpClient $http, int $started): void
    {
        $stats = $http->stats();
        $this->log->info(sprintf(
            '%d requests, %d peak in flight, %s, %.2fs',
            $stats['requests'],
            $stats['peak_in_flight'],
            $stats['protocols'],
            (hrtime(true) - $started) / 1e9
        ));
    }

    private static function size(int $bytes): string
    {
        return $bytes < 1024
            ? $bytes . ' B'
            : sprintf('%.1f %s', $bytes / ($bytes < 1048576 ? 1024 : 1048576), $bytes < 1048576 ? 'KB' : 'MB');
    }

    public static function main(array $argv, string $root): int
    {
        $log = null;

        try {
            $options = Options::parse($argv, $root);
            $log = new Logger();

            return (new self($options, $log))->run();
        } catch (\RuntimeException $failure) {
            ($log ?? new Logger())->error($failure->getMessage());

            return 1;
        }
    }
}
