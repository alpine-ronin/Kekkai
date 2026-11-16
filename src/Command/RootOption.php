<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait RootOption
{
    private function addRootOption(): void
    {
        $this->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'Project root (default: $CLAUDE_PROJECT_DIR, else the current directory)');
    }

    /**
     * @param InputInterface $input
     */
    private function project(InputInterface $input): Project
    {
        $root = $input->getOption('root');

        return Project::at(\is_string($root) ? $root : null);
    }
}
