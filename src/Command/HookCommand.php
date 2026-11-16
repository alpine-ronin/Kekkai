<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Hook\ClaudeCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'hook', description: 'Claude Code hooks (JSON on stdin): pre-tool-use, stop')]
final class HookCommand extends Command
{
    /** @var resource|null */
    private $stdin;

    /**
     * @param resource|null $stdin
     */
    public function __construct($stdin = null)
    {
        $this->stdin = $stdin;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
                The Claude Code hooks, written into .claude/settings.json by <comment>kekkai install claude --write</comment>.
                They read the hook's JSON on stdin and print Claude Code's answer, or nothing.

                  <info>pre-tool-use</info>   on Edit, Write, MultiEdit, NotebookEdit: refuses an edit outside the scope, asks for a
                                 protected file, never answers allow (Claude Code's own permissions then apply)
                  <info>stop</info>           reviews the diff and runs the checks; when they fail, sends the agent back once
                HELP);
        $this
            ->addArgument('event', InputArgument::REQUIRED, 'pre-tool-use or stop')
            ->addOption('no-checks', null, InputOption::VALUE_NONE, 'stop: only the scope, without the checks')
            ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'Project root (default: $CLAUDE_PROJECT_DIR, else the cwd of the hook)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = stream_get_contents($this->stdin ?? \STDIN);
        $data = json_decode(\is_string($raw) ? $raw : '', true);
        $data = \is_array($data) ? $data : [];
        $root = $input->getOption('root');
        $root = \is_string($root) ? $root : null;

        $response = match ($input->getArgument('event')) {
            'pre-tool-use' => ClaudeCode::preToolUse($data, $root),
            'stop' => ClaudeCode::stop($data, $root, !$input->getOption('no-checks')),
            default => false,
        };

        if ($response === false) {
            $output->writeln('<error>Unknown event: use pre-tool-use or stop.</error>');

            return Command::INVALID;
        }

        if ($response !== null) {
            $output->writeln(json_encode($response, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
