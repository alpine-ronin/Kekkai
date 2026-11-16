<?php

declare(strict_types=1);

namespace Kekkai\Check;

use Kekkai\Change\ChangedFiles;
use Kekkai\Gate\Decision;
use Kekkai\Process;
use Kekkai\Project;
use Kekkai\Verdict;

final readonly class Reviewer
{
    /**
     * @param Project $project
     */
    public function __construct(private Project $project)
    {
    }

    /**
     * @param string $base
     * @param bool $runChecks
     */
    public function review(string $base = 'HEAD', bool $runChecks = true): Report
    {
        $files = (new ChangedFiles($this->project->root))->since($base);
        $scope = $this->project->scope();
        $gate = $this->project->gate();

        $findings = [];
        foreach ($files as $file) {
            $decision = $gate->decide($file);
            if ($decision->rule === Decision::NO_SCOPE && $decision->verdict === Verdict::Allow) {
                continue;
            }
            if ($decision->verdict !== Verdict::Allow) {
                $findings[] = $decision;
            }
        }

        $checks = [];
        if ($runChecks && $files !== []) {
            foreach ($this->project->config->checks as $definition) {
                $process = Process::run($definition->run, $this->project->root, $definition->timeout, [
                    'KEKKAI_CHANGED_FILES' => implode("\n", $files),
                    'KEKKAI_BASE' => $base,
                ]);
                $checks[] = new CheckResult($definition->name, $definition->run, $process->exitCode === 0, $process->output, $process->timedOut);
            }
        }

        return new Report($files, $findings, $checks, $scope, $this->project->config, $base);
    }
}
