<?php

declare(strict_types=1);

namespace Kekkai\Tests\Unit;

use Kekkai\Config\Config;
use Kekkai\Scope\Scope;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    public function testZonesAndGlobs(): void
    {
        $config = Config::fromArray(['zones' => ['catalog' => ['src/Catalog/', 'tests/Catalog/']]]);
        $scope = Scope::declare('Add a price to products', ['catalog', 'config/services.yaml'], 'agent', $config);

        self::assertTrue($scope->covers('src/Catalog/Domain/Product.php', $config));
        self::assertTrue($scope->covers('tests/Catalog/ProductTest.php', $config));
        self::assertTrue($scope->covers('config/services.yaml', $config));
        self::assertFalse($scope->covers('src/Billing/Invoice.php', $config));
    }

    public function testWidenKeepsHistory(): void
    {
        $config = new Config();
        $scope = Scope::declare('Fix the rounding', ['src/Billing/'], 'agent', $config)
            ->widen(['src/Shared/Money.php'], 'Rounding lives in Money', 'agent', $config);

        self::assertTrue($scope->covers('src/Shared/Money.php', $config));
        self::assertSame(['src/Billing/'], $scope->entries);
        self::assertCount(1, $scope->widenings);
        self::assertStringContainsString('widened by agent: Rounding lives in Money', $scope->describe($config));

        $restored = Scope::fromArray($scope->toArray());
        self::assertEquals($scope, $restored);
    }

    public function testWideningNeedsAReason(): void
    {
        $config = new Config();
        $this->expectExceptionMessage('Say why the scope must grow');

        Scope::declare('x', ['src/A/'], 'agent', $config)->widen(['src/B/'], ' ', 'agent', $config);
    }

    public function testWideningWithNothingNew(): void
    {
        $config = new Config();
        $this->expectExceptionMessage('already in the scope');

        Scope::declare('x', ['src/A/'], 'agent', $config)->widen(['src/A/'], 'again', 'agent', $config);
    }

    public function testRefusesTheWholeProject(): void
    {
        $this->expectExceptionMessage('covers the whole project');

        Scope::declare('everything', ['**'], 'agent', new Config());
    }

    public function testRefusesPathsOutsideTheProject(): void
    {
        $this->expectExceptionMessage('leads outside the project');

        Scope::declare('escape', ['../other/'], 'agent', new Config());
    }

    public function testNeedsAnIntent(): void
    {
        $this->expectExceptionMessage('intent');

        Scope::declare('', ['src/'], 'agent', new Config());
    }
}
