<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class SourceState implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $fingerprint = null,
        private readonly ?string $include = null
    ) {
    }

    public static function fromArray(array $state): self
    {
        $sha1 = $state['sha1'] ?? null;
        $include = $state['include'] ?? null;

        return new self(is_string($sha1) ? $sha1 : null, is_string($include) ? $include : null);
    }

    public function isBasedOn(?string $include): bool
    {
        return $include !== null && $this->include === $include && $this->fingerprint !== null;
    }

    public function jsonSerialize(): array
    {
        return array_filter(
            ['sha1' => $this->fingerprint, 'include' => $this->include],
            static fn (?string $value): bool => $value !== null
        );
    }
}
