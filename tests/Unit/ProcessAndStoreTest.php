<?php

declare(strict_types=1);

namespace Kekkai\Tests\Unit;

use Kekkai\Config\Config;
use Kekkai\Process;
use Kekkai\Scope\Scope;
use Kekkai\Scope\ScopeStore;
use Kekkai\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class ProcessAndStoreTest extends TestCase
{
    public function testProcess(): void
    {
        $ok = Process::run(['sh', '-c', 'echo out; echo err >&2; exit 3'], sys_get_temp_dir());
        self::assertSame(3, $ok->exitCode);
        self::assertStringContainsString('out', $ok->output);
        self::assertStringContainsString('err', $ok->output);

        $env = Process::run('echo "$KEKKAI_TEST"', sys_get_temp_dir(), 10, ['KEKKAI_TEST' => 'from env']);
        self::assertSame("from env\n", $env->output);

        $started = microtime(true);
        $slow = Process::run(['sleep', '5'], sys_get_temp_dir(), 1);
        self::assertTrue($slow->timedOut);
        self::assertSame(124, $slow->exitCode);
        self::assertLessThan(4, microtime(true) - $started);

        $big = Process::run(['sh', '-c', 'head -c 300000 /dev/zero | tr "\\0" x'], sys_get_temp_dir());
        self::assertSame(300000, \strlen($big->output));
    }

    public function testStore(): void
    {
        $project = new TemporaryProject(git: false);
        $store = new ScopeStore($project->root);

        self::assertNull($store->load());
        self::assertFalse($store->clear());

        $scope = Scope::declare('Prices', ['src/Catalog/'], 'agent', new Config());
        $store->save($scope);
        self::assertEquals($scope, $store->load());
        self::assertSame("*\n", $project->read('.kekkai/.gitignore'));
        self::assertSame([], glob($project->root . '/.kekkai/*.tmp'));

        $store->log(['event' => 'test']);
        $store->log(['event' => 'again']);
        $lines = file($project->root . '/.kekkai/log.jsonl') ?: [];
        self::assertCount(2, $lines);
        self::assertStringContainsString('"event":"again"', $lines[1]);

        self::assertTrue($store->clear());
        self::assertNull($store->load());

        $project->write('.kekkai/scope.json', '{broken');
        self::assertNull($store->load());
    }

    public function testConfigFiles(): void
    {
        $project = new TemporaryProject(git: false);
        self::assertNull(Config::load($project->root)->file);

        $project->write('kekkai.yaml.dist', "outside-scope: ask\n");
        self::assertSame('kekkai.yaml.dist', Config::load($project->root)->file);

        $project->write('kekkai.yaml', "zones: [a, b]\n");
        try {
            Config::load($project->root);
            self::fail('A list of zones is refused');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('zones must map names to path patterns.', $exception->getMessage());
        }

        $project->write('kekkai.yaml', "zones:\n  a: [\n");
        $this->expectExceptionMessageMatches('/^kekkai\.yaml: /');
        Config::load($project->root);
    }
}
