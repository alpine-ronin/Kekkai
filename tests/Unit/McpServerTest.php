<?php

declare(strict_types=1);

namespace Kekkai\Tests\Unit;

use Kekkai\Mcp\McpServer;
use Kekkai\Project;
use Kekkai\Tests\Support\Json;
use Kekkai\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class McpServerTest extends TestCase
{
    public function testRunSpeaksJsonRpcLineByLine(): void
    {
        $project = new TemporaryProject();
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        self::assertIsResource($input);
        self::assertIsResource($output);
        fwrite($input, implode("\n", [
            '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"1999-01-01"}}',
            '{"jsonrpc":"2.0","method":"notifications/initialized"}',
            '',
            'not json',
            '{"jsonrpc":"2.0","id":2,"method":"resources/list"}',
            '{"jsonrpc":"2.0","id":3,"method":"ping"}',
            '{"jsonrpc":"2.0","id":4}',
        ]) . "\n");
        rewind($input);

        (new McpServer(Project::at($project->root), '1.0.0'))->run($input, $output);
        rewind($output);
        $lines = array_map(static fn (string $line): mixed => json_decode($line, true), array_filter(explode("\n", (string) stream_get_contents($output))));
        $lines = array_values($lines);

        self::assertCount(5, $lines);
        self::assertSame('2025-11-25', Json::path($lines[0], 'result', 'protocolVersion'));
        self::assertSame('kekkai', Json::path($lines[0], 'result', 'serverInfo', 'name'));
        self::assertSame(-32700, Json::path($lines[1], 'error', 'code'));
        self::assertSame(-32601, Json::path($lines[2], 'error', 'code'));
        self::assertSame(3, Json::path($lines[3], 'id'));
        self::assertSame(-32600, Json::path($lines[4], 'error', 'code'));
    }

    public function testToolErrorsAreResultsTheAgentReads(): void
    {
        $project = (new TemporaryProject())->write('kekkai.yaml', "allow-widen: false\n");
        $server = new McpServer(Project::at($project->root));

        self::assertSame('Widening is reserved to the user in this project: stop and ask them, they run `kekkai scope widen`.', self::call($server, 'widen_scope', ['paths' => ['a'], 'reason' => 'b'], true));
        self::assertStringStartsWith('Unknown tool', self::call($server, 'delete_scope', [], true));
        self::assertStringContainsString('paths must be a list', self::call($server, 'declare_scope', ['intent' => 'x', 'paths' => 3], true));
        self::assertSame('No scope declared.', self::call($server, 'show_scope', []));
    }

    public function testWidenWithoutAScope(): void
    {
        $project = new TemporaryProject();
        $server = new McpServer(Project::at($project->root));

        self::assertSame('No scope is declared yet: call declare_scope first.', self::call($server, 'widen_scope', ['paths' => ['src/'], 'reason' => 'x'], true));
    }

    public function testCheckChangesWithoutChecks(): void
    {
        $project = (new TemporaryProject())->write('kekkai.yaml', "checks:\n  - {name: never, run: 'false'}\n")->commit()->write('src/A.php');
        $server = new McpServer(Project::at($project->root));
        self::call($server, 'declare_scope', ['intent' => 'A', 'paths' => 'src/']);

        self::assertStringContainsString('PASSED', self::call($server, 'check_changes', ['run_checks' => false]));
        self::assertStringContainsString('✗ check never', self::call($server, 'check_changes', []));
    }

    /**
     * @param McpServer $server
     * @param string $tool
     * @param array<string, mixed> $arguments
     * @param bool $error
     */
    private static function call(McpServer $server, string $tool, array $arguments, bool $error = false): string
    {
        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]]);
        self::assertSame($error, Json::path($response, 'result', 'isError') ?? false);
        $text = Json::path($response, 'result', 'content', 0, 'text');
        self::assertIsString($text);

        return $text;
    }
}
