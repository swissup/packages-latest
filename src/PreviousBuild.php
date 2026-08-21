<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class PreviousBuild
{
    public function __construct(
        public readonly string $include,
        public readonly string $sha1,
        public readonly array $packages,
        private readonly array $states
    ) {
    }

    public function state(string $label): SourceState
    {
        return $this->states[$label] ?? new SourceState();
    }
}
