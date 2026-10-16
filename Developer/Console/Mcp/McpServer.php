<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */
declare(strict_types=1);

namespace Betagento\Developer\Console\Mcp;

use Betagento\Developer\Mcp\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console entry point for the MCP stdio server
 */
class McpServer extends Command
{
    public const READ_ONLY = 'read-only';
    public const TIMEOUT = 'timeout';

    /**
     * Define the command name, description and options
     *
     * @return void
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::READ_ONLY,
                null,
                InputOption::VALUE_NONE,
                'Expose only the introspection tools (hide deploy_static_file and deploy_requirejs)'
            ),
            new InputOption(
                self::TIMEOUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Per tool call timeout in seconds',
                '300'
            ),
        ];
        $this->setName('beta_dev:mcp_server');
        $this->setDescription('Run an MCP (Model Context Protocol) server over stdio exposing the beta_dev tools '
            . 'to AI agents' . PHP_EOL . 'e.g. claude mcp add mage2-dev -- php bin/magento beta_dev:mcp_server');
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // stdout carries the JSON-RPC stream; anything else must go to stderr.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- keep PHP notices out of the protocol stream
        ini_set('display_errors', 'stderr');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $server = new Server(
            PHP_BINARY,
            BP . '/bin/magento',
            BP,
            (bool) $input->getOption(self::READ_ONLY),
            max(1, (int) $input->getOption(self::TIMEOUT))
        );
        $server->run();

        return Command::SUCCESS;
    }
}
