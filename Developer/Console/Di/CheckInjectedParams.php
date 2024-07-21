<?php 
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
declare(strict_types=1);

namespace Betagento\Developer\Console\Di;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\Table;
use Betagento\Developer\Di\DiConfig;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Magento\Framework\App\Area;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;


class CheckInjectedParams extends Command{

    const OBJECT_TYPE = 'type';
    const SCOPE_CODE = 'scope_code';

    
    public function __construct(
        protected DiConfig $diConfig,
        protected State $_state,
        protected ObjectManagerInterface $objectManager,
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure() {
        
        $options = [
            new InputOption(
				self::OBJECT_TYPE,
				'-t',
				InputOption::VALUE_REQUIRED,
				"Class type : -t 'Magento\Quote\Api\CartManagementInterface'"
            ),
            new InputOption(
				self::SCOPE_CODE,
				'-s',
				InputOption::VALUE_OPTIONAL,
				sprintf('Scope : -s %s|%s|%s|%s|%s', Area::AREA_GLOBAL, Area::AREA_FRONTEND, Area::AREA_ADMINHTML, Area::AREA_WEBAPI_REST, Area::AREA_GRAPHQL)
            )
        ];
        
        $this->setName('beta_dev:check_di');
        $this->setDescription('Show all plugins which are injected into a class by specific scopes' .PHP_EOL . "e.g. bin/magento beta_dev:check_di -t 'Magento\Quote\Api\CartManagementInterface' -s global");
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * @inheritDoc
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output){
       
        if (!$objectType = $input->getOption(self::OBJECT_TYPE)) {
            $output->writeln("Please input a class name for a type, example bin/magento beta_dev:show_plugins -t 'Magento\Quote\Api\CartManagementInterface' ");
            return Command::FAILURE; 
        }

        $scope = $input->getOption(self::SCOPE_CODE) 
            ? $input->getOption(self::SCOPE_CODE) : Area::AREA_GLOBAL;
        
        $possibleScopes = [Area::AREA_GLOBAL, Area::AREA_FRONTEND, Area::AREA_ADMINHTML, Area::AREA_WEBAPI_REST, Area::AREA_GRAPHQL];

        if (!in_array($scope, $possibleScopes)) {
            $output->writeln(sprintf("Scope should be %s", implode(" | ", $possibleScopes)));
            return Command::FAILURE; 
        }

        $output->writeln(sprintf("<info>You are in the scope '%s'</info>", $scope));
            
        $this->_state->setAreaCode($scope);
        
        $configLoader = $this->objectManager->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);

        $this->objectManager->configure($configLoader->load($scope));

        $parametters = $this->diConfig->getInjectedParams($objectType);
        $yellowStyle = new OutputFormatterStyle("yellow", null , ['bold', 'underscore']);
        $output->getFormatter()->setStyle('yellow', $yellowStyle);
        $table = new Table($output);
        $table->setHeaders(['name', 'class', 'info', 'preference']);

        array_walk($parametters,

            function($parameter) use ($table) {

                $preferences = [];
                if ($parameter['preference'] && count($parameter['preference'])) {
                    foreach ($parameter['preference'] as $interface => $preferenceClass) {
                        $preferences[] = sprintf("<yellow>%s</yellow> %s ---> %s", $interface, PHP_EOL ,$preferenceClass);
                    }
                }
                $parameter['preference'] = implode(PHP_EOL, $preferences);

                if (count($parameter['parent']) > 0) {

                    $table->addRow(new TableSeparator());
                    $parameter['type'] = "<error>".$parameter['type']. "</error>";
                    $parameter['parent'] = $parameter['error'] == 1 ? implode(PHP_EOL, ["<error>A class that relies on another class should interact with it through an interface!</error>", ... $parameter['parent'] ])
                        : $parameter['parent'] = implode(PHP_EOL, ["<info>Check possible interfaces. A class that relies on another class should interact with it through an interface! </info>", ... $parameter['parent'] ]);
                    
                }
 
                if (count($parameter['parent']) == 0) {
                    $parameter['parent'] = $parameter["is_interface"] == 1 ?  '<info>Ok</info>' : '---';
                    $parameter['type'] = $parameter["is_interface"] == 1 ? '<info>'.$parameter['type'].'</info>' : $parameter['type'];
                }
                
                unset($parameter['error']);
                unset($parameter['is_interface']);
                $table->addRow($parameter);
                $table->addRow(new TableSeparator());
            }

        );
        
        $table->render();        
        return Command::SUCCESS;
    }
}
