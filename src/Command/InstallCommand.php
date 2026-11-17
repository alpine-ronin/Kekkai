<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Hook\Agent;
use Kekkai\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'install', description: 'Connect an agent (claude, cursor): hooks and MCP server, printed or written (--write)')]
final class InstallCommand extends Command
{
    use RootOption;

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
                Connects an agent to Kekkai in this project: its edit and stop hooks, and the MCP server. Without --write,
                prints them. --write merges into the files and keeps what is there.

                  <info>claude</info>   Claude Code: hooks in .claude/settings.json, server in .mcp.json
                  <info>cursor</info>   Cursor: hooks in .cursor/hooks.json, server in .cursor/mcp.json

                  <info>kekkai install cursor</info>
                  <info>kekkai install claude --write</info>
                  <info>kekkai install claude --write --binary "vendor/bin/kekkai"</info>

                Other agents: register <comment>kekkai serve</comment> as a stdio MCP server and run <comment>kekkai check</comment> before
                finishing and in CI. Commit the files so that the team works under the same rules.
                HELP);
        $this
            ->addArgument('agent', InputArgument::OPTIONAL, implode(' or ', Agent::values()), Agent::Claude->value)
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Merge the hooks and the server into the agent\'s files')
            ->addOption('binary', null, InputOption::VALUE_REQUIRED, 'Command that runs kekkai (default: this executable)');
        $this->addRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('agent');
        $name = \is_string($name) ? $name : '';
        $agent = Agent::tryFrom($name);
        if ($agent === null) {
            $output->writeln(sprintf('<error>No hooks for %s: use %s. Other MCP clients: run `kekkai serve` as a stdio server and `kekkai check` before finishing and in CI.</error>', $name, implode(' or ', Agent::values())));

            return Command::INVALID;
        }

        $project = $this->project($input);
        $binary = $input->getOption('binary');
        $binary = \is_string($binary) ? $binary : self::binary($project->root, $agent);

        $hooks = $agent->hooks($binary);
        $mcp = $agent->mcpServer($binary);

        if (!$input->getOption('write')) {
            $output->writeln(['# ' . $agent->hooksFile(), self::json($hooks), '', '# ' . $agent->mcpFile(), self::json($mcp), '', 'Write both with: kekkai install ' . $agent->value . ' --write'], OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $this->merge($project->root . '/' . $agent->hooksFile(), $hooks);
        $this->merge($project->root . '/' . $agent->mcpFile(), $mcp);
        $output->writeln([sprintf('Hooks written to %s, server to %s.', $agent->hooksFile(), $agent->mcpFile()), $agent->restartHint()]);

        return Command::SUCCESS;
    }

    private static function binary(string $root, Agent $agent): string
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $real = \is_string($script) ? realpath($script) : false;
        if ($real === false) {
            return 'kekkai';
        }

        $relative = Path::relative($root, $real);
        if ($relative !== null && $relative !== '') {
            return $agent->projectPrefix() . $relative;
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
