<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class IndexRenderer
{
    private const GENERATED_PATTERN = '/<time class="generated" datetime="([^"]+)"/';

    public function __construct(
        private readonly string $directory,
        private readonly Template $template = new Template()
    ) {
    }

    public function render(
        array $packages,
        string $include,
        \DateTimeImmutable $scannedAt,
        \DateTimeImmutable $generatedAt
    ): int {
        $html = $this->template->render('index', [
            'packages' => array_map(
                static fn (string $name, array $version): PackageView => new PackageView($name, $version),
                array_keys($packages),
                $packages
            ),
            'include' => $include,
            'scannedAt' => $scannedAt,
            'generatedAt' => $generatedAt,
        ]);

        Filesystem::write($this->directory . '/index.html', $html);

        return strlen($html);
    }

    public function previousGeneratedAt(): ?\DateTimeImmutable
    {
        $path = $this->directory . '/index.html';

        if (!is_file($path)) {
            return null;
        }

        if (preg_match(self::GENERATED_PATTERN, (string) file_get_contents($path), $match) !== 1) {
            return null;
        }

        try {
            return new \DateTimeImmutable($match[1]);
        } catch (\Exception) {
            return null;
        }
    }
}
