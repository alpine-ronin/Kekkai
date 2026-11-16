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
}
