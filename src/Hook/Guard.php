<?php

declare(strict_types=1);

namespace Kekkai\Hook;

use Kekkai\Check\Reviewer;
use Kekkai\Gate\Decision;
use Kekkai\Project;
use Kekkai\Verdict;

/**
 * What every agent's hooks ask Kekkai, whatever the agent: may this file be edited, may the agent stop.
 * The adapters only translate their agent's JSON to and from these answers.
 */
final class Guard
{
    public const SEND_BACK = 'Fix what failed before finishing: revert the files outside the scope (or widen it with a reason if the change needs them), then make the checks pass.';

    /**
     * The decision for an edit, or null when the edit may go ahead without a word.
     */
    public static function edit(string $path, ?string $root, string $tool): ?Decision
    {
        try {
            $project = Project::at($root);
            $decision = $project->gate()->decide($path);
        } catch (\Throwable $exception) {
            return new Decision(Verdict::Ask, Decision::UNDECIDED, $path, sprintf('Kekkai could not decide (%s): approve or refuse this edit yourself.', $exception->getMessage()));
        }

        if ($decision->verdict === Verdict::Allow) {
            return null;
        }

        $project->store->log(['event' => 'pre-tool-use', 'tool' => $tool, 'path' => $decision->path, 'rule' => $decision->rule, 'verdict' => $decision->verdict->value]);

        return $decision;
    }

    /**
     * The report to send the agent back with, or null when it may stop.
     */
    public static function review(?string $root, bool $runChecks): ?string
    {
        try {
            $project = Project::at($root);
            $report = (new Reviewer($project))->review('HEAD', $runChecks);
        } catch (\Throwable) {
            return null;
        }

        if ($report->passed()) {
            return null;
        }

        $project->store->log(['event' => 'stop', 'blocking' => array_map(static fn (Decision $d): string => $d->path, $report->blocking()), 'passed' => false]);

        return $report->toText() . "\n\n" . self::SEND_BACK;
    }
}
