<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Check\Reviewer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check', description: 'Check the files changed since a revision against the scope, and run the checks of kekkai.yaml')]
final class CheckCommand extends Command
{
    use RootOption;

    protected function configure(): void
    {
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

        $output->writeln(
            $input->getOption('format') === 'json'
                ? json_encode($report->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)
                : $report->toText(),
            OutputInterface::OUTPUT_RAW,
        );

        return $report->passed() ? Command::SUCCESS : Command::FAILURE;
    }
}
