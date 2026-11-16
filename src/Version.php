<?php

declare(strict_types=1);

namespace Kekkai;

use Composer\InstalledVersions;

final class Version
{
    public const PACKAGE = 'alpine-ronin/kekkai';

    private static string $built = '@kekkai_version@';

    public static function get(): string
    {
        if (!str_starts_with(self::$built, '@')) {
            return self::$built;
        }

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE);
            if (\is_string($version) && !str_contains($version, 'no-version-set')) {
                return ltrim($version, 'v');
            }
        }

        return 'dev';
    }

    public static function executable(): ?string
    {
        $phar = \Phar::running(false);

        return $phar === '' ? null : $phar;
    }

    public static function isRelease(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/', $version) === 1;
    }
}
