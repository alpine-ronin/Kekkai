<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Check\Reviewer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check', description: 'Review the changes since a revision: the scope, then the checks of kekkai.yaml')]
final class CheckCommand extends Command
{
    use RootOption;

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
                Reviews the files changed since a git revision (working tree and untracked files): which are outside the
                scope, protected, or changed without a scope, then runs the checks of kekkai.yaml with the changed files in
                $KEKKAI_CHANGED_FILES.

                  <info>kekkai check</info>                       since HEAD
                  <info>kekkai check --base origin/main</info>    the whole branch, in CI
                  <info>kekkai check --no-checks</info>           the scope only
                  <info>kekkai check --format json</info>         docs/schemas/report.schema.json

                Exit code 0 when the review passed, 1 when it failed, 2 on an error.
                HELP);
        $this
            ->addOption('base', 'b', InputOption::VALUE_REQUIRED, 'Git revision to compare with', 'HEAD')
            ->addOption('no-checks', null, InputOption::VALUE_NONE, 'Only the scope, without the checks')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'text or json', 'text');
        $this->addRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $base = $input->getOption('base');
        try {
            $report = (new Reviewer($this->project($input)))->review(\is_string($base) ? $base : 'HEAD', !$input->getOption('no-checks'));
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        if ($input->getOption('format') === 'json') {
            $output->writeln(json_encode($report->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        } else {
            $output->writeln($report->toText(styled: true));
        }

        return $report->passed() ? Command::SUCCESS : Command::FAILURE;
    }
}
