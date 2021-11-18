<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Console\Order;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class BuildOrderGrid extends Command {

    const MISSING_ORDERS = 'missing-orders'; 
    const STANDALONE_ORDER = 'standalone-order'; 

    /**
     * it's virtual class -- pls check di for more information
     * @param \Magento\Sales\Model\ResourceModel\GridInterface
     */
    private $orderGrid;
    /**
     * @param \Magento\Sales\Model\ResourceModel\GridInterface $orderGrid
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\GridInterface $orderGrid
    )
    {
        $this->orderGrid = $orderGrid;
        parent::__construct();
    }

    /**
     * @inheritDoc
     *
     * @return void
     */
    protected function configure() {
        
        $options = [
            new InputOption(
				self::MISSING_ORDERS,
				'-m',
				InputOption::VALUE_OPTIONAL,
				'Build order grid items for all missed orders, -m true'
            ),
            new InputOption(
				self::STANDALONE_ORDER,
				'-s',
				InputOption::VALUE_OPTIONAL,
				'Build an order grid item for a specific order, -s [order_id]'
			)
        ];
        
        $this->setName('beta_dev:build_order_grid');
        $this->setDescription('Deploy requirejs-config.js');
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        
        if (!$input->getOption(self::MISSING_ORDERS) && !$input->getOption(self::STANDALONE_ORDER)) {
            $output->writeln("Please provide correct command line agurments.");
            return;
        }
        /**
         * Refresh order grid items for missing ones.
         */
        if($input->getOption(self::MISSING_ORDERS)){
            $output->writeln("--- Building for missing items ---");
            $this->orderGrid->refreshBySchedule(); 
        }
        /**
         * Refresh order grid item for a specific one.
         */
        if($orderId = $input->getOption(self::STANDALONE_ORDER)){
            $output->writeln(sprintf("--- Building for a specific order: %s --- ", $orderId));
            $this->orderGrid->refresh($orderId);
        }           
    }

}