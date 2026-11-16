<?php

declare(strict_types=1);

namespace Kekkai\Tests\Integration;

use Kekkai\Check\Reviewer;
use Kekkai\Hook\ClaudeCode;
use Kekkai\Mcp\McpServer;
use Kekkai\Process;
use Kekkai\Project;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kekkai-test-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/src/Catalog', 0o777, true);
        mkdir($this->root . '/src/Billing', 0o777, true);
        file_put_contents($this->root . '/src/Catalog/Product.php', "<?php\n");
        file_put_contents($this->root . '/src/Billing/Invoice.php', "<?php\n");
        file_put_contents($this->root . '/kekkai.yaml', <<<'YAML'
            zones:
              catalog: ['src/Catalog/']
            protected: ['composer.lock']
            checks:
              - name: no-todo
                run: '! grep -rn TODO src'
            YAML);

        foreach ([['init', '-q', '-b', 'main'], ['config', 'user.email', 't@t'], ['config', 'user.name', 't'], ['add', '.'], ['commit', '-qm', 'init']] as $args) {
            self::assertSame(0, Process::run(['git', ...$args], $this->root)->exitCode);
        }
    }

    protected function tearDown(): void
    {
        Process::run(['rm', '-rf', $this->root], sys_get_temp_dir());
    }

    public function testAgentWorkflow(): void
    {
        $server = new McpServer(Project::at($this->root));

        $before = ClaudeCode::preToolUse(['tool_name' => 'Edit', 'tool_input' => ['file_path' => $this->root . '/src/Catalog/Product.php']], $this->root);
        self::assertSame('ask', self::permission($before));

        $declared = $this->call($server, 'declare_scope', ['intent' => 'Add a price to products', 'paths' => ['catalog']]);
        self::assertStringContainsString('catalog = src/Catalog/', $declared);

        self::assertNull(ClaudeCode::preToolUse(['tool_name' => 'Write', 'tool_input' => ['file_path' => $this->root . '/src/Catalog/Price.php']], $this->root));

        $outside = ClaudeCode::preToolUse(['tool_name' => 'Edit', 'tool_input' => ['file_path' => $this->root . '/src/Billing/Invoice.php']], $this->root);
        self::assertSame('deny', self::permission($outside));

        $guarded = ClaudeCode::preToolUse(['tool_name' => 'Write', 'tool_input' => ['file_path' => $this->root . '/.kekkai/scope.json']], $this->root);
        self::assertSame('deny', self::permission($guarded));

        self::assertNull(ClaudeCode::preToolUse(['tool_name' => 'Bash', 'tool_input' => ['command' => 'ls']], $this->root));

        file_put_contents($this->root . '/src/Catalog/Price.php', "<?php\n");
        self::assertNull(ClaudeCode::stop(['session_id' => 'first'], $this->root));

        file_put_contents($this->root . '/src/Billing/Invoice.php', "<?php\n// changed through sed\n");
        $stop = ClaudeCode::stop(['session_id' => 'second'], $this->root);
        self::assertIsArray($stop);
        self::assertSame('block', $stop['decision']);
        self::assertIsString($stop['reason']);
        self::assertStringContainsString('src/Billing/Invoice.php', $stop['reason']);
        self::assertNull(ClaudeCode::stop(['stop_hook_active' => true], $this->root));

        $widened = $this->call($server, 'widen_scope', ['paths' => ['src/Billing/Invoice.php'], 'reason' => 'Invoices show the price']);
        self::assertStringContainsString('widened by agent: Invoices show the price', $widened);
        self::assertNull(ClaudeCode::stop(['session_id' => 'third'], $this->root));

        file_put_contents($this->root . '/src/Catalog/Price.php', "<?php\n// TODO\n");
        $report = (new Reviewer(Project::at($this->root)))->review();
        self::assertFalse($report->passed());
        self::assertStringContainsString('✗ check no-todo', $report->toText());
        self::assertStringContainsString('Scope widened 1 time(s)', $report->toText());

        $again = $server->handle(['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call', 'params' => ['name' => 'declare_scope', 'arguments' => ['intent' => 'other', 'paths' => ['src/']]]]);
        self::assertIsArray($again);
        self::assertIsArray($again['result']);
        self::assertTrue($again['result']['isError']);

        self::assertFileExists($this->root . '/.kekkai/log.jsonl');
        self::assertSame("*\n", file_get_contents($this->root . '/.kekkai/.gitignore'));
    }

    public function testInitializeAndList(): void
    {
        $server = new McpServer(Project::at($this->root), '1.2.3');
        $init = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
        self::assertIsArray($init);
        self::assertIsArray($init['result']);
        self::assertSame('2025-06-18', $init['result']['protocolVersion']);

        $list = $server->handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        self::assertIsArray($list);
        self::assertIsArray($list['result']);
        self::assertIsArray($list['result']['tools']);
        self::assertCount(4, $list['result']['tools']);

        self::assertNull($server->handle(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
    }

    /**
     * @param McpServer $server
     * @param string $tool
     * @param array<string, mixed> $arguments
     */
    private function call(McpServer $server, string $tool, array $arguments): string
    {
        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]]);
        self::assertIsArray($response);
        self::assertIsArray($response['result']);
        self::assertArrayNotHasKey('isError', $response['result']);
        self::assertIsArray($response['result']['content']);
        self::assertIsArray($response['result']['content'][0]);
        self::assertIsString($response['result']['content'][0]['text']);

        return $response['result']['content'][0]['text'];
    }

    /**
     * @param array<string, mixed>|null $response
     */
    private static function permission(?array $response): string
    {
        self::assertIsArray($response);
        self::assertIsArray($response['hookSpecificOutput']);
        self::assertIsString($response['hookSpecificOutput']['permissionDecision']);

        return $response['hookSpecificOutput']['permissionDecision'];
    }
}
