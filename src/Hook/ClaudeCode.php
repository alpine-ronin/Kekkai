<?php

declare(strict_types=1);

namespace Kekkai\Hook;

use Kekkai\Check\Reviewer;
use Kekkai\Gate\Decision;
use Kekkai\Project;
use Kekkai\Verdict;

final class ClaudeCode
{
    public const EDIT_TOOLS = ['Edit' => 'file_path', 'Write' => 'file_path', 'MultiEdit' => 'file_path', 'NotebookEdit' => 'notebook_path'];

    /**
     * @param array<mixed> $input
     * @param string|null $root
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

        try {
            $project = Project::at($root ?? self::root($input));
            $decision = $project->gate()->decide($path);
        } catch (\Throwable $exception) {
            return self::permission(Verdict::Ask, sprintf('Kekkai could not decide (%s): approve or refuse this edit yourself.', $exception->getMessage()));
        }

        if ($decision->verdict === Verdict::Allow) {
            return null;
        }

        $project->store->log(['event' => 'pre-tool-use', 'tool' => $tool, 'path' => $decision->path, 'rule' => $decision->rule, 'verdict' => $decision->verdict->value]);

        return self::permission($decision->verdict, $decision->reason);
    }

    /**
     * @param array<mixed> $input
     * @param string|null $root
     * @param bool $runChecks
     *
     * @return array<string, mixed>|null
     */
    public static function stop(array $input, ?string $root = null, bool $runChecks = true): ?array
    {
        if (($input['stop_hook_active'] ?? false) === true) {
            return null;
        }

        try {
            $project = Project::at($root ?? self::root($input));
            $report = (new Reviewer($project))->review('HEAD', $runChecks);
        } catch (\Throwable) {
            return null;
        }

        if ($report->passed()) {
            return null;
        }

        $project->store->log(['event' => 'stop', 'blocking' => array_map(static fn (Decision $d): string => $d->path, $report->blocking()), 'passed' => false]);

        return [
            'decision' => 'block',
            'reason' => $report->toText() . "\n\nFix what failed before finishing: revert the files outside the scope (or widen it with a reason if the change needs them), then make the checks pass.",
        ];
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

    /**
     * @param Verdict $verdict
     * @param string $reason
     *
     * @return array<string, mixed>
     */
    private static function permission(Verdict $verdict, string $reason): array
    {
        return [
            'hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
                'permissionDecision' => $verdict->value,
                'permissionDecisionReason' => 'Kekkai: ' . $reason,
            ],
        ];
    }
}
