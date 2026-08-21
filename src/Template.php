<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Template
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__) . '/templates';
    }

    public function render(string $__template, array $__data = []): string
    {
        $__file = $this->directory . '/' . $__template . '.phtml';

        if (!is_file($__file)) {
            throw new \RuntimeException(sprintf('Template "%s" does not exist.', $__file));
        }

        extract($__data, EXTR_SKIP);
        ob_start();

        try {
            require $__file;
        } finally {
            $__html = (string) ob_get_clean();
        }

        return $__html;
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
