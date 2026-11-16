<?php

declare(strict_types=1);

namespace Kekkai\Command;

use Kekkai\Mcp\McpServer;
use Kekkai\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'serve', description: 'MCP server on stdio: declare_scope, widen_scope, show_scope, check_changes')]
final class ServeCommand extends Command
{
    use RootOption;

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
                The MCP server, on stdio. Tools: declare_scope, widen_scope, show_scope, check_changes.
                Your MCP client starts it; you never run it yourself. Written into .mcp.json by <comment>kekkai install claude --write</comment>.
                HELP);
        $this->addRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new McpServer($this->project($input), Version::get()))->run();

        return Command::SUCCESS;
    }
}
