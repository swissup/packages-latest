<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class RepositoryBuilder
{
    private const DROP_KEYS = ['default-branch', 'published-time', 'security-advisories'];

    private const KEY_ORDER = [
        'name', 'description', 'keywords', 'homepage', 'version', 'version_normalized',
        'license', 'authors', 'source', 'dist', 'type', 'support', 'funding', 'time',
        'autoload', 'autoload-dev', 'require', 'require-dev', 'conflict', 'replace',
        'provide', 'suggest', 'extra', 'bin', 'abandoned',
    ];

    public function __construct(private readonly string $directory)
    {
    }

    public function previous(): ?PreviousBuild
    {
        $path = $this->directory . '/packages.json';

        if (!is_file($path)) {
            return null;
        }

        try {
            $root = Filesystem::decode((string) file_get_contents($path), $path);
            $include = array_key_first($root['includes'] ?? []);
            $sha1 = $root['includes'][$include]['sha1'] ?? null;

            if (!is_string($include) || !is_string($sha1) || !is_file($this->directory . '/' . $include)) {
                return null;
            }

            $packages = Filesystem::decode(
                (string) file_get_contents($this->directory . '/' . $include),
                $include
            )['packages'] ?? [];
        } catch (\RuntimeException) {
            return null;
        }

        if (!is_array($packages) || $packages === []) {
            return null;
        }

        $flattened = [];
        foreach ($packages as $name => $versions) {
            if (is_array($versions) && ($version = reset($versions)) !== false) {
                $flattened[(string) $name] = $version;
            }
        }

        $states = [];
        foreach ($root['sources'] ?? [] as $label => $state) {
            if (is_array($state)) {
                $states[(string) $label] = SourceState::fromArray($state);
            }
        }

        return new PreviousBuild($include, $sha1, $flattened, $states);
    }

    public function writeIncludeJson(array $packages): array
    {
        $map = [];
        foreach ($packages as $name => $version) {
            $map[$name] = [(string) $version['version'] => $this->canonicalise($version)];
        }

        // The sha1 of these exact bytes becomes the filename, so the encoding must stay stable.
        $json = json_encode(
            ['packages' => $map],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $sha1 = sha1($json);
        $include = 'include/all$' . $sha1 . '.json';

        Filesystem::write($this->directory . '/' . $include, $json);

        return ['include' => $include, 'sha1' => $sha1, 'bytes' => strlen($json)];
    }

    public function writePackagesJson(string $include, string $sha1, array $states): void
    {
        Filesystem::write($this->directory . '/packages.json', json_encode(
            [
                'packages' => new \stdClass(),
                'includes' => [$include => ['sha1' => $sha1]],
                'sources' => $states,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL);

        foreach (glob($this->directory . '/include/all$*.json') ?: [] as $stale) {
            if (basename($stale) !== basename($include)) {
                @unlink($stale);
            }
        }
    }

    private function canonicalise(array $version): array
    {
        foreach (self::DROP_KEYS as $key) {
            unset($version[$key]);
        }

        $ordered = [];
        foreach (self::KEY_ORDER as $key) {
            if (array_key_exists($key, $version)) {
                $ordered[$key] = $version[$key];
                unset($version[$key]);
            }
        }

        ksort($version, SORT_STRING);

        return $ordered + $version;
    }
}
