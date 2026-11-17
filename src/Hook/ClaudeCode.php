<?php

declare(strict_types=1);

namespace Kekkai\Hook;

final class ClaudeCode
{
    public const EDIT_TOOLS = ['Edit' => 'file_path', 'Write' => 'file_path', 'MultiEdit' => 'file_path', 'NotebookEdit' => 'notebook_path'];

    /**
     * @param array<mixed> $input
     *
     * @return array<string, mixed>|null
     */
    public static function preToolUse(array $input, ?string $root = null): ?array
    {
        $tool = \is_string($input['tool_name'] ?? null) ? $input['tool_name'] : '';
        $key = self::EDIT_TOOLS[$tool] ?? null;
        $toolInput = \is_array($input['tool_input'] ?? null) ? $input['tool_input'] : [];
        $path = $key === null ? null : ($toolInput[$key] ?? null);
        if (!\is_string($path) || $path === '') {
            return null;
        }

        $decision = Guard::edit($path, $root ?? self::root($input), $tool);
        if ($decision === null) {
            return null;
        }

        return [
            'hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
                'permissionDecision' => $decision->verdict->value,
                'permissionDecisionReason' => 'Kekkai: ' . $decision->reason,
            ],
        ];
    }

    /**
     * @param array<mixed> $input
     *
     * @return array<string, mixed>|null
     */
    public static function stop(array $input, ?string $root = null, bool $runChecks = true): ?array
    {
        if (($input['stop_hook_active'] ?? false) === true) {
            return null;
        }

        $report = Guard::review($root ?? self::root($input), $runChecks);

        return $report === null ? null : ['decision' => 'block', 'reason' => $report];
    }

    /**
     * @param array<mixed> $input
     */
    private static function root(array $input): ?string
    {
        $env = getenv('CLAUDE_PROJECT_DIR');
        if (\is_string($env) && $env !== '') {
            return $env;
        }

        return \is_string($input['cwd'] ?? null) ? $input['cwd'] : null;
    }
}
