<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Hook\ClaudeCode;
use Kekkai\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'install', description: 'Connect Claude Code: hooks and MCP server, printed or written (--write)')]
final class InstallCommand extends Command
{
    use RootOption;

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
                Connects Claude Code to Kekkai in this project: the PreToolUse and Stop hooks in .claude/settings.json, the
                MCP server in .mcp.json. Without --write, prints them. --write merges into the files and keeps what is there.

                  <info>kekkai install claude</info>
                  <info>kekkai install claude --write</info>
                  <info>kekkai install claude --write --binary "vendor/bin/kekkai"</info>

                Commit both files so that the team works under the same rules.
                HELP);
        $this
            ->addArgument('agent', InputArgument::OPTIONAL, 'claude', 'claude')
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Merge the hooks into .claude/settings.json and the server into .mcp.json')
            ->addOption('binary', null, InputOption::VALUE_REQUIRED, 'Command that runs kekkai (default: this executable)');
        $this->addRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('agent') !== 'claude') {
            $output->writeln('<error>Only Claude Code (claude) has hooks for now. Other MCP clients: run `kekkai serve` as a stdio server and `kekkai check` in CI.</error>');

            return Command::INVALID;
        }

        $project = $this->project($input);
        $binary = $input->getOption('binary');
        $binary = \is_string($binary) ? $binary : self::binary($project->root);

        $settings = self::settings($binary);
        $mcp = ['mcpServers' => ['kekkai' => ['command' => 'sh', 'args' => ['-c', $binary . ' serve']]]];

        if (!$input->getOption('write')) {
            $output->writeln(['# .claude/settings.json', self::json($settings), '', '# .mcp.json', self::json($mcp), '', 'Write both with: kekkai install claude --write'], OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $settingsFile = $project->root . '/.claude/settings.json';
        $this->merge($settingsFile, $settings);
        $this->merge($project->root . '/.mcp.json', $mcp);
        $output->writeln(['Hooks written to .claude/settings.json, server to .mcp.json.', 'Restart Claude Code in this project, then approve the kekkai MCP server.']);

        return Command::SUCCESS;
    }

    /**
     * @param string $binary
     *
     * @return array<string, mixed>
     */
    public static function settings(string $binary): array
    {
        return [
            'hooks' => [
                'PreToolUse' => [['matcher' => implode('|', array_keys(ClaudeCode::EDIT_TOOLS)), 'hooks' => [['type' => 'command', 'command' => $binary . ' hook pre-tool-use']]]],
                'Stop' => [['hooks' => [['type' => 'command', 'command' => $binary . ' hook stop', 'timeout' => 900]]]],
            ],
        ];
    }

    /**
     * @param string $root
     */
    private static function binary(string $root): string
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $real = \is_string($script) ? realpath($script) : false;
        if ($real === false) {
            return 'kekkai';
        }

        $relative = Path::relative($root, $real);
        if ($relative !== null && $relative !== '') {
            return '"$CLAUDE_PROJECT_DIR"/' . $relative;
        }

        $which = trim((string) shell_exec('command -v kekkai 2>/dev/null'));

        return $which !== '' && realpath($which) === $real ? 'kekkai' : escapeshellarg($real);
    }

    /**
     * @param string $file
     * @param array<string, mixed> $addition
     */
    private function merge(string $file, array $addition): void
    {
        $current = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        if (!\is_array($current)) {
            throw new \RuntimeException(sprintf('%s is not valid JSON: fix it first.', $file));
        }

        $merged = self::deepMerge($current, $addition);
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }
        file_put_contents($file, self::json($merged) . "\n");
    }

    /**
     * @param array<mixed> $current
     * @param array<mixed> $addition
     *
     * @return array<mixed>
     */
    private static function deepMerge(array $current, array $addition): array
    {
        foreach ($addition as $key => $value) {
            if (\is_array($value) && array_is_list($value)) {
                $existing = \is_array($current[$key] ?? null) ? $current[$key] : [];
                foreach ($value as $item) {
                    if (!\in_array($item, $existing, true)) {
                        $existing[] = $item;
                    }
                }
                $current[$key] = $existing;
            } elseif (\is_array($value)) {
                $current[$key] = self::deepMerge(\is_array($current[$key] ?? null) ? $current[$key] : [], $value);
            } else {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    /**
     * @param array<mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
