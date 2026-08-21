<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class Options
{
    private function __construct(
        public readonly string $output,
        public readonly bool $force
    ) {
    }

    public static function parse(array $argv, string $defaultOutput): self
    {
        $output = $defaultOutput;
        $force = false;

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--force') {
                $force = true;
            } elseif (str_starts_with($argument, '--output=')) {
                $output = rtrim(substr($argument, 9), '/');
            } elseif ($argument === '--help' || $argument === '-h') {
                fwrite(STDOUT, self::usage());
                exit(0);
            } else {
                throw new \RuntimeException(sprintf('Unknown argument "%s".%s%s', $argument, PHP_EOL, self::usage()));
            }
        }

        if ($output === '' || !is_dir($output)) {
            throw new \RuntimeException(sprintf('Output directory "%s" does not exist.', $output));
        }

        return new self($output, $force);
    }

    public static function usage(): string
    {
        return <<<TXT
        Usage: php run.php [options]

          --force        Rebuild even when both sources are unchanged.
          --output=DIR   Where to write the repository (default: script directory).
          --help, -h     Show this message.

        TXT;
    }
}
