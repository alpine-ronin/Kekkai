<?php

declare(strict_types=1);

namespace Kekkai\Tests\Unit;

use Kekkai\Hook\ClaudeCode;
use Kekkai\Tests\Support\Json;
use Kekkai\Tests\Support\TemporaryProject;
use Kekkai\Version;
use PHPUnit\Framework\TestCase;

final class HookTest extends TestCase
{
    public function testIgnoresWhatIsNotAnEdit(): void
    {
        $project = new TemporaryProject();

        self::assertNull(ClaudeCode::preToolUse(['tool_name' => 'Read', 'tool_input' => ['file_path' => '/etc/passwd']], $project->root));
        self::assertNull(ClaudeCode::preToolUse(['tool_name' => 'Edit', 'tool_input' => []], $project->root));
        self::assertNull(ClaudeCode::preToolUse([], $project->root));
    }

    public function testNotebookEditAndRelativePaths(): void
    {
        $project = new TemporaryProject();

        $answer = ClaudeCode::preToolUse(['tool_name' => 'NotebookEdit', 'tool_input' => ['notebook_path' => 'analysis.ipynb']], $project->root);
        self::assertSame('ask', Json::path($answer, 'hookSpecificOutput', 'permissionDecision'));
    }

    public function testOutsideTheProjectAsks(): void
    {
        $project = new TemporaryProject();

        $answer = ClaudeCode::preToolUse(['tool_name' => 'Write', 'tool_input' => ['file_path' => $project->root . '/../elsewhere.php']], $project->root);
        $reason = Json::path($answer, 'hookSpecificOutput', 'permissionDecisionReason');
        self::assertSame('ask', Json::path($answer, 'hookSpecificOutput', 'permissionDecision'));
        self::assertIsString($reason);
        self::assertStringContainsString('is outside the project', $reason);
    }

    public function testAnInvalidConfigurationAsksTheUser(): void
    {
        $project = (new TemporaryProject())->write('kekkai.yaml', "protect: [a]\n");

        $answer = ClaudeCode::preToolUse(['tool_name' => 'Write', 'tool_input' => ['file_path' => $project->root . '/a.php']], $project->root);
        $reason = Json::path($answer, 'hookSpecificOutput', 'permissionDecisionReason');
        self::assertSame('ask', Json::path($answer, 'hookSpecificOutput', 'permissionDecision'));
        self::assertIsString($reason);
        self::assertStringContainsString('Kekkai could not decide (Unknown key(s) in kekkai.yaml: protect', $reason);
    }

    public function testTheProjectComesFromTheHookCwd(): void
    {
        $project = new TemporaryProject();
        $previous = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR');

        try {
            $answer = ClaudeCode::preToolUse(['cwd' => $project->root, 'tool_name' => 'Write', 'tool_input' => ['file_path' => $project->root . '/kekkai.yaml']]);
        } finally {
            putenv($previous === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $previous);
        }

        self::assertSame('deny', Json::path($answer, 'hookSpecificOutput', 'permissionDecision'));
    }

    public function testStopOutsideGitLetsTheAgentStop(): void
    {
        self::assertNull(ClaudeCode::stop([], (new TemporaryProject(git: false))->root));
    }

    public function testVersion(): void
    {
        self::assertTrue(Version::isRelease('1.2.3'));
        self::assertTrue(Version::isRelease('1.0.0-rc.1'));
        self::assertFalse(Version::isRelease('dev-main'));
        self::assertNotSame('', Version::get());
        self::assertNull(Version::executable());
    }
}
