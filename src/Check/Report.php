<?php

declare(strict_types=1);

namespace Kekkai\Check;

use Kekkai\Config\Config;
use Kekkai\Gate\Decision;
use Kekkai\Scope\Scope;
use Kekkai\Verdict;

final readonly class Report
{
    /**
     * @param list<string> $files
     * @param list<Decision> $findings
     * @param list<CheckResult> $checks
     * @param Scope|null $scope
     * @param Config $config
     * @param string $base
     */
    public function __construct(
        public array $files,
        public array $findings,
        public array $checks,
        public ?Scope $scope,
        public Config $config,
        public string $base,
    ) {
    }

    public function passed(): bool
    {
        return $this->blocking() === [] && array_filter($this->checks, static fn (CheckResult $c): bool => !$c->passed) === [];
    }

    /**
     * @return list<Decision>
     */
    public function blocking(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Decision $d): bool => $d->rule === Decision::OUTSIDE_SCOPE && $d->verdict === Verdict::Deny,
        ));
    }

    public function toText(): string
    {
        if ($this->files === []) {
            return sprintf('Kekkai: no change since %s.', $this->base);
        }

        $lines = [sprintf('Kekkai: %d file(s) changed since %s. %s', \count($this->files), $this->base, $this->passed() ? 'PASSED' : 'FAILED')];
        $lines[] = $this->scope?->describe($this->config) ?? 'Scope: none declared';

        $groups = [
            Decision::GUARDED => 'Kekkai files changed (the agent may not edit them: check who did)',
            Decision::OUTSIDE_SCOPE => 'Outside the scope',
            Decision::PROTECTED => 'Protected files touched (check the user approved them)',
            Decision::OUTSIDE_PROJECT => 'Outside the project',
            Decision::NO_SCOPE => 'Changed without a declared scope',
        ];
        foreach ($groups as $rule => $title) {
            $paths = array_map(static fn (Decision $d): string => $d->path, array_filter($this->findings, static fn (Decision $d): bool => $d->rule === $rule));
            if ($paths === []) {
                continue;
            }
            $blocking = $rule === Decision::OUTSIDE_SCOPE && $this->config->outsideScope === Verdict::Deny;
            $lines[] = sprintf('%s %s:', $blocking ? '✗' : '!', $title);
            foreach ($paths as $path) {
                $lines[] = '  ' . $path;
            }
        }

        if ($this->scope !== null && $this->scope->widenings !== []) {
            $lines[] = sprintf('! Scope widened %d time(s) during the task: see the scope above.', \count($this->scope->widenings));
        }

        foreach ($this->checks as $check) {
            $lines[] = sprintf('%s check %s%s', $check->passed ? '✓' : '✗', $check->name, $check->timedOut ? ' (timed out)' : '');
            if (!$check->passed && trim($check->output) !== '') {
                $lines[] = preg_replace('/^/m', '    ', $check->tail()) ?? $check->tail();
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed(),
            'base' => $this->base,
            'files' => $this->files,
            'scope' => $this->scope?->toArray(),
            'findings' => array_map(static fn (Decision $d): array => ['path' => $d->path, 'rule' => $d->rule, 'verdict' => $d->verdict->value], $this->findings),
            'checks' => array_map(static fn (CheckResult $c): array => ['name' => $c->name, 'command' => $c->command, 'passed' => $c->passed, 'timedOut' => $c->timedOut, 'output' => $c->tail()], $this->checks),
        ];
    }
}
