<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class PackageView
{
    public readonly string $version;
    public readonly ?string $url;
    public readonly string $versionTitle;
    public readonly bool $abandoned;
    public readonly string $type;
    public readonly array $requires;
    public readonly string $search;

    public function __construct(public readonly string $name, array $version)
    {
        $this->version = (string) ($version['version'] ?? '');
        $this->url = self::repositoryUrl($version);
        $this->versionTitle = self::versionTitle($version);
        $this->abandoned = !empty($version['abandoned']);
        $this->type = (string) ($version['type'] ?? '');
        $this->requires = is_array($version['require'] ?? null) ? $version['require'] : [];
        $this->search = strtolower($name);
    }

    private static function versionTitle(array $version): string
    {
        $parts = [];

        if (is_string($version['time'] ?? null)) {
            $parts[] = 'Released ' . substr($version['time'], 0, 10);
        }

        if (!empty($version['abandoned'])) {
            $parts[] = is_string($version['abandoned'])
                ? 'Abandoned, use ' . $version['abandoned']
                : 'Abandoned';
        }

        return implode(' · ', $parts);
    }

    private static function repositoryUrl(array $version): ?string
    {
        $url = (string) ($version['source']['url'] ?? '');

        if (preg_match('#^(?:git|ssh)://git@([^/]+)/(.+?)(?:\.git)?$#', $url, $match) === 1
            || preg_match('#^git@([^:]+):(.+?)(?:\.git)?$#', $url, $match) === 1) {
            return 'https://' . $match[1] . '/' . $match[2];
        }

        if (str_starts_with($url, 'https://')) {
            return (string) preg_replace('/\.git$/', '', $url);
        }

        foreach ([$version['support']['source'] ?? null, $version['homepage'] ?? null] as $fallback) {
            if (is_string($fallback)
                && preg_match('#^https://[^/]+/[^/]+/[^/]+#', $fallback, $match) === 1) {
                return $match[0];
            }
        }

        return null;
    }
}
