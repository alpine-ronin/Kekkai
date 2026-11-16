<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Process;
use Kekkai\Project;
use Kekkai\Scope\ScopeStore;
use Kekkai\Update\Installation;
use Kekkai\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctor', description: 'Check the setup of this project: git, kekkai.yaml, Claude Code hooks, MCP server, checks')]
final class DoctorCommand extends Command
{
    private const OK = 'ok';
    private const WARN = 'warn';
    private const FAIL = 'fail';

    protected function configure(): void
    {
        $this
            ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'Project root (default: $CLAUDE_PROJECT_DIR, else the current directory)')
            ->setHelp(<<<'HELP'
                Checks what Kekkai needs in this project and says how to fix what is missing:

                  <info>✓</info> fine   <comment>!</comment> works, but worth a look   <error>✗</error> Kekkai cannot guard this project as it is

                Exit code 1 when something is ✗.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $input->getOption('root');
        $results = [];
        $installation = Installation::current();
        $results[] = [self::OK, sprintf('Kekkai %s (%s), PHP %s', Version::get(), $installation->kind, PHP_VERSION)];

        try {
            $project = Project::at(\is_string($root) ? $root : null);
        } catch (\InvalidArgumentException $exception) {
            $results[] = [self::FAIL, $exception->getMessage(), 'Fix kekkai.yaml, or run from the project root.'];
            $this->print($output, $results);

            return Command::FAILURE;
        }

        $results[] = [self::OK, sprintf('Project %s', $project->root)];
        $git = Process::run(['git', 'rev-parse', '--is-inside-work-tree'], $project->root, 10);
        $results[] = $git->exitCode === 0
            ? [self::OK, 'Git repository: the review reads the changes from git']
            : [self::FAIL, 'Not a git repository: kekkai check and the stop review need one', 'git init'];

        $config = $project->config;
        $results[] = $config->file === null
            ? [self::WARN, 'No kekkai.yaml: the defaults apply (no zones, nothing protected, no checks)', 'kekkai init']
            : [self::OK, sprintf('%s: %d zone(s), %d protected pattern(s), %d check(s), without-scope %s, outside-scope %s', $config->file, \count($config->zones), \count($config->protected), \count($config->checks), $config->withoutScope->value, $config->outsideScope->value)];

        foreach ($config->checks as $check) {
            $command = strtok(trim($check->run), " \t");
            if (\is_string($command) && !str_contains($command, '/') && preg_match('/^[A-Za-z0-9_.-]+$/', $command) === 1 && !\in_array($command, ['!', 'test', '[', 'echo', 'true', 'false'], true)) {
                $found = Process::run(['sh', '-c', 'command -v ' . escapeshellarg($command)], $project->root, 10)->exitCode === 0;
                if (!$found) {
                    $results[] = [self::WARN, sprintf('Check "%s": %s is not on the PATH', $check->name, $command), 'Install it, or give its path in kekkai.yaml.'];
                }
            }
        }

        $settings = self::json($project->root . '/.claude/settings.json');
        $hooks = \is_array($settings['hooks'] ?? null) ? $settings['hooks'] : [];
        $results[] = self::hasHook($hooks, 'PreToolUse', 'hook pre-tool-use')
            ? [self::OK, 'Claude Code PreToolUse hook: edits are checked before they happen']
            : [self::FAIL, 'No Kekkai PreToolUse hook in .claude/settings.json: edits outside the scope go through', 'kekkai install claude --write'];
        $results[] = self::hasHook($hooks, 'Stop', 'hook stop')
            ? [self::OK, 'Claude Code Stop hook: the diff is reviewed before the agent stops']
            : [self::WARN, 'No Kekkai Stop hook: shell writes and failing checks are not caught', 'kekkai install claude --write'];

        $mcp = self::json($project->root . '/.mcp.json');
        $servers = \is_array($mcp['mcpServers'] ?? null) ? $mcp['mcpServers'] : [];
        $results[] = isset($servers['kekkai'])
            ? [self::OK, 'MCP server kekkai in .mcp.json: the agent can declare and widen its scope']
            : [self::WARN, 'No kekkai server in .mcp.json: the agent cannot declare its scope itself', 'kekkai install claude --write'];

        $scope = $project->scope();
        $results[] = $scope === null
            ? [self::OK, 'No scope declared: the next task declares its own']
            : [self::OK, sprintf('Scope declared by %s: %s (%d widening(s)); kekkai scope clear when the task is done', $scope->by, $scope->intent, \count($scope->widenings))];

        if (is_dir($project->root . '/' . ScopeStore::DIRECTORY) && !is_file($project->root . '/' . ScopeStore::DIRECTORY . '/.gitignore')) {
            $results[] = [self::WARN, '.kekkai/ has no .gitignore: the scope could be committed', 'echo "*" > .kekkai/.gitignore'];
        }

        if ($installation->updatable()) {
            $results[] = [self::OK, 'kekkai self-update updates this installation'];
        }

        $this->print($output, $results);

        return array_filter($results, static fn (array $r): bool => $r[0] === self::FAIL) === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param OutputInterface $output
     * @param list<array{0: string, 1: string, 2?: string}> $results
     */
    private function print(OutputInterface $output, array $results): void
    {
        foreach ($results as $result) {
            $output->writeln(match ($result[0]) {
                self::OK => '<info>✓</info> ',
                self::WARN => '<comment>!</comment> ',
                default => '<error>✗</error> ',
            } . $result[1]);
            if (isset($result[2])) {
                $output->writeln('    → ' . $result[2]);
            }
        }
    }

    /**
     * @param array<mixed> $hooks
     * @param string $event
     * @param string $needle
     */
    private static function hasHook(array $hooks, string $event, string $needle): bool
    {
        foreach (\is_array($hooks[$event] ?? null) ? $hooks[$event] : [] as $matcher) {
            foreach (\is_array($matcher) && \is_array($matcher['hooks'] ?? null) ? $matcher['hooks'] : [] as $hook) {
                if (\is_array($hook) && \is_string($hook['command'] ?? null) && str_contains($hook['command'], 'kekkai') && str_contains($hook['command'], $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $file
     *
     * @return array<mixed>
     */
    private static function json(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return \is_array($data) ? $data : [];
    }
}
