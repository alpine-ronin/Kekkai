<?php

declare(strict_types=1);

namespace Kekkai\Update;

use Kekkai\Version;

final readonly class Installation
{
    public const BINARY = 'binary';
    public const PHAR = 'phar';
    public const COMPOSER = 'composer';
    public const SOURCE = 'source';

    /**
     * @param string $kind
     * @param string|null $path
     * @param string $asset
     */
    public function __construct(
        public string $kind,
        public ?string $path,
        public string $asset,
    ) {
    }

    public static function current(): self
    {
        $executable = Version::executable();
        if ($executable !== null) {
            return PHP_SAPI === 'micro'
                ? new self(self::BINARY, $executable, self::binaryAsset())
                : new self(self::PHAR, $executable, 'kekkai.phar');
        }

        $vendor = str_contains(str_replace('\\', '/', __DIR__), '/vendor/alpine-ronin/kekkai/');

        return new self($vendor ? self::COMPOSER : self::SOURCE, null, 'kekkai.phar');
    }

    public static function binaryAsset(): string
    {
        $system = match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            'Linux' => 'linux',
            default => throw new UpdateFailed(sprintf('No Kekkai binary for %s: use the PHAR (kekkai.phar) with PHP 8.3 or later.', PHP_OS_FAMILY)),
        };
        $architecture = match (php_uname('m')) {
            'x86_64', 'amd64' => 'x86_64',
            'arm64', 'aarch64' => 'aarch64',
            default => throw new UpdateFailed(sprintf('No Kekkai binary for the %s architecture: use the PHAR with PHP 8.3 or later.', php_uname('m'))),
        };

        if ($system === 'macos' && $architecture === 'x86_64') {
            throw new UpdateFailed('No Kekkai binary for Intel Macs: use the PHAR (kekkai.phar) with PHP 8.3 or later.');
        }

        return sprintf('kekkai-%s-%s', $system, $architecture);
    }

    public function updatable(): bool
    {
        return $this->path !== null;
    }

    public function howToUpdate(): string
    {
        return match ($this->kind) {
            self::COMPOSER => 'Kekkai is installed with Composer: run composer update alpine-ronin/kekkai.',
            self::SOURCE => 'Kekkai runs from a clone: run git pull, then composer install.',
            default => 'kekkai self-update',
        };
    }
}
