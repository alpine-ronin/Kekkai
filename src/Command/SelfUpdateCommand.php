<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Update\Installation;
use Kekkai\Update\Releases;
use Kekkai\Update\SelfUpdater;
use Kekkai\Update\UpdateFailed;
use Kekkai\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'self-update', description: 'Update the binary or PHAR to the latest release, or to a given one', aliases: ['update'])]
final class SelfUpdateCommand extends Command
{
    /**
     * @param Releases|null $releases
     * @param Installation|null $installation
     * @param bool $replace
     */
    public function __construct(
        private readonly ?Releases $releases = null,
        private readonly ?Installation $installation = null,
        private readonly bool $replace = true,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::OPTIONAL, 'A release (0.2.0), or edge for the last build of main', 'latest')
            ->addOption('check', 'c', InputOption::VALUE_NONE, 'Only say whether a newer release exists: exit code 1 when one does')
            ->addOption('no-attestation', null, InputOption::VALUE_NONE, 'Skip the attestation check even when the GitHub CLI is logged in')
            ->setHelp(<<<'HELP'
                Downloads the release's file for this installation (the binary for this system, or kekkai.phar), checks it
                against the release's SHA256SUMS and, when the GitHub CLI is logged in, its attestation (that the release
                workflow of alpine-ronin/kekkai built it), then puts it in place of the running one.

                  <info>kekkai self-update</info>            the latest release
                  <info>kekkai self-update 0.2.0</info>      a given release, newer or older
                  <info>kekkai self-update edge</info>       the last build of main
                  <info>kekkai self-update --check</info>    is there a newer release?

                Installed with Composer, update with <comment>composer update alpine-ronin/kekkai</comment>; from a clone, with git pull.
                KEKKAI_RELEASES_URL replaces https://github.com/alpine-ronin/kekkai/releases (a mirror, a test).
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $releases = $this->releases ?? new Releases();
        $installation = $this->installation ?? Installation::current();
        $current = Version::get();
        $requested = $input->getArgument('version');
        $channel = \is_string($requested) && $requested !== '' ? $requested : 'latest';
        $channel = \in_array($channel, ['edge', 'dev-main', 'main'], true) ? 'edge' : ($channel === 'latest' ? 'latest' : ltrim($channel, 'v'));

        try {
            if ($input->getOption('check')) {
                $latest = $releases->version('latest');
                if ($latest === $current) {
                    $output->writeln(sprintf('Kekkai %s is the latest release.', $current));

                    return Command::SUCCESS;
                }
                $output->writeln(sprintf('Kekkai %s is available (this is %s). %s', $latest, $current, $installation->updatable() ? 'Run: kekkai self-update' : $installation->howToUpdate()));

                return Command::FAILURE;
            }

            if (!$installation->updatable() || $installation->path === null) {
                $output->writeln($installation->howToUpdate());

                return Command::SUCCESS;
            }

            if ($channel !== 'edge') {
                $target = $channel === 'latest' ? $releases->version('latest') : $channel;
                if ($target === $current) {
                    $output->writeln(sprintf('Kekkai %s is already installed.', $current));

                    return Command::SUCCESS;
                }
            }

            $download = (new SelfUpdater($releases, !$input->getOption('no-attestation')))->download($installation->path, $installation->asset, $channel);
        } catch (UpdateFailed $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Kekkai %s -> %s: %s checked against SHA256SUMS%s.',
            $current,
            $download['version'],
            $installation->asset,
            match (true) {
                $download['attested'] === true => ', and its attestation',
                (bool) $input->getOption('no-attestation') => ' (attestation check skipped)',
                default => ' (attestation not checked: install and log in the GitHub CLI to check it)',
            },
        ));
        $output->writeln(sprintf('Installed in %s. Restart your MCP client so the server runs the new version.', $installation->path));

        if (!$this->replace) {
            rename($download['file'], $installation->path);

            return Command::SUCCESS;
        }

        SelfUpdater::replace($download['file'], $installation->path);
    }
}
