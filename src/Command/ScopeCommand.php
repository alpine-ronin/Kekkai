<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Scope\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'scope', description: 'Show, declare, widen or clear the scope of the current task: show | declare <paths>... -m <intent> | widen <paths>... -m <reason> | clear')]
final class ScopeCommand extends Command
{
    use RootOption;

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'show, declare, widen or clear', 'show')
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Paths, globs or zones')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'The intent (declare) or the reason (widen)');
        $this->addRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->project($input);
        $config = $project->config;
        $action = $input->getArgument('action');
        $rawPaths = $input->getArgument('paths');
        $paths = \is_array($rawPaths) ? array_values(array_filter($rawPaths, 'is_string')) : [];
        $message = $input->getOption('message');
        $message = \is_string($message) ? $message : '';

        try {
            switch ($action) {
                case 'show':
                    $output->writeln($project->scope()?->describe($config) ?? 'No scope declared.');

                    return Command::SUCCESS;
                case 'declare':
                    $scope = Scope::declare($message, $paths, 'user', $config);
                    $project->store->save($scope);
                    $project->store->log(['event' => 'declare', 'by' => 'user', 'entries' => $scope->entries]);
                    $output->writeln($scope->describe($config));

                    return Command::SUCCESS;
                case 'widen':
                    $current = $project->scope();
                    if ($current === null) {
                        $output->writeln('<error>No scope declared: use `kekkai scope declare`.</error>');

                        return Command::FAILURE;
                    }
                    $scope = $current->widen($paths, $message, 'user', $config);
                    $project->store->save($scope);
                    $project->store->log(['event' => 'widen', 'by' => 'user', 'entries' => $paths]);
                    $output->writeln($scope->describe($config));

                    return Command::SUCCESS;
                case 'clear':
                    $output->writeln($project->store->clear() ? 'Scope cleared: the next task declares its own.' : 'No scope to clear.');
                    $project->store->log(['event' => 'clear', 'by' => 'user']);

                    return Command::SUCCESS;
                default:
                    $output->writeln('<error>Unknown action: use show, declare, widen or clear.</error>');

                    return Command::INVALID;
            }
        } catch (\InvalidArgumentException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }
    }
}
