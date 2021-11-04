<?php 
namespace Betagento\Developer\Console\Order;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\ObjectManager;

class BuildOrderGrid extends Command {

    
    const MISSING_ORDERS = 'missing-orders'; 

    const STANDALONE_ORDER = 'standalone-order'; 


    public function __construct()
    {
        parent::__construct();
    }

    
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

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        if ($input->getOption(self::MISSING_ORDERS) || $input->getOption(self::STANDALONE_ORDER)) {
            /**
             * I have to use ObjectManager here, because I can inject an object "Magento\Sales\Model\ResourceModel\Order\Grid" into a contructor function.
             */
            $gridBuilding = ObjectManager::getInstance()->get('Magento\Sales\Model\ResourceModel\Order\Grid');
            
            if($input->getOption(self::MISSING_ORDERS)){
                $output->writeln("--- Building for missing items ---");
                $gridBuilding->refreshBySchedule(); 
            }
            if($orderId = $input->getOption(self::STANDALONE_ORDER)){
                $output->writeln(sprintf("--- Building for a specific order: %s --- ", $orderId));
                $gridBuilding->refresh($orderId);
            }

		} else {
			$output->writeln("Please read a guideline to use this command. Thank you!");
        }
           
    }

}
