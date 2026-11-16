<?php

declare(strict_types=1);

namespace Kekkai\Tests\Unit;

use Kekkai\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testRelative(): void
    {
        self::assertSame('src/A.php', Path::relative('/p', '/p/src/A.php'));
        self::assertSame('src/A.php', Path::relative('/p', 'src/A.php'));
        self::assertSame('src/A.php', Path::relative('/p/', '/p/./src/x/../A.php'));
        self::assertSame('', Path::relative('/p', '/p'));
        self::assertNull(Path::relative('/p', '/p/../q/A.php'));
        self::assertNull(Path::relative('/p', '/project/A.php'));
        self::assertNull(Path::relative('/p', '../q/A.php'));
    }

    public function testWindowsPaths(): void
    {
        self::assertSame('src/A.php', Path::relative('C:\\p', 'C:\\p\\src\\A.php'));
        self::assertTrue(Path::isAbsolute('C:\\p'));
    }

    public function testResolveFollowsSymbolicLinksOfTheExistingPart(): void
    {
        $base = sys_get_temp_dir() . '/kekkai-path-' . bin2hex(random_bytes(4));
        mkdir($base . '/real/project', 0o777, true);
        symlink($base . '/real', $base . '/link');

        try {
            $real = (string) realpath($base . '/real/project');
            self::assertSame($real . '/src/New.php', Path::resolve($base . '/link/project/src/New.php'));
            self::assertSame($real, Path::resolve($base . '/link/project'));
            self::assertNull(Path::relative($real, $base . '/link/project/src/New.php'));
            self::assertSame('src/New.php', Path::relative($real, Path::resolve($base . '/link/project/src/New.php')));
        } finally {
            unlink($base . '/link');
            rmdir($base . '/real/project');
            rmdir($base . '/real');
            rmdir($base);
        }
    }
}
