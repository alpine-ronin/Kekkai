<?php

declare(strict_types=1);

namespace Kekkai;

use Composer\InstalledVersions;

final class Version
{
    public const PACKAGE = 'alpine-ronin/kekkai';

    public static function get(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
            return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'dev';
        }

        return 'dev';
    }
}
