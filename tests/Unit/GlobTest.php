<?php

declare(strict_types=1);

namespace Kekkai\Tests\Unit;

use Kekkai\Glob;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GlobTest extends TestCase
{
    /**
     * @return iterable<array{string, string, bool}>
     */
    public static function cases(): iterable
    {
        yield ['src/Catalog', 'src/Catalog/Product.php', true];
        yield ['src/Catalog', 'src/Catalog', true];
        yield ['src/Catalog', 'src/CatalogBis/Product.php', false];
        yield ['src/Catalog/', 'src/Catalog/Domain/Product.php', true];
        yield ['src/*/Domain/**', 'src/Catalog/Domain/Model/Product.php', true];
        yield ['src/*/Domain/**', 'src/Catalog/Application/Handler.php', false];
        yield ['src/**/Domain/*.php', 'src/Domain/Product.php', true];
        yield ['src/**/Domain/*.php', 'src/A/B/Domain/Product.php', true];
        yield ['src/**/Domain/*.php', 'src/A/Domain/Model/Product.php', false];
        yield ['.env*', '.env.local', true];
        yield ['.env*', 'config/.env', false];
        yield ['composer.lock', 'composer.lock', true];
        yield ['tests/**/*Test.php', 'tests/Unit/GlobTest.php', true];
        yield ['src/Foo?.php', 'src/Foo1.php', true];
        yield ['src/Foo?.php', 'src/Foo12.php', false];
        yield ['./src/a.php', 'src/a.php', true];
    }

    #[DataProvider('cases')]
    public function testMatches(string $pattern, string $path, bool $expected): void
    {
        self::assertSame($expected, (new Glob($pattern))->matches($path));
    }

    public function testCoversEverything(): void
    {
        self::assertTrue((new Glob('**'))->coversEverything());
        self::assertTrue((new Glob('**/*'))->coversEverything());
        self::assertFalse((new Glob('src/**'))->coversEverything());
        self::assertFalse((new Glob('*'))->coversEverything());
    }
}
