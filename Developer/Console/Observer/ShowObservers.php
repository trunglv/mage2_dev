<?php 
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Console\Observer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\App\State;
use Magento\Framework\Config\ScopeInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class ShowObservers extends Command{

    const EVENT_CODE = 'event';
    const SCOPE_CODE = 'scope_code';

    /**
     * @var \Magento\Framework\Event\ConfigInterface $eventConfig $scopeConfig
     */
    protected $eventConfig;
    
    /**
     * @var ScopeInterface $scopeConfig
     */
    protected $scopeConfig;

    /**
     * @var State $state
     */
    protected $state;

    
    /**
     * Constructor
     *
     * @param \Magento\Framework\Event\ConfigInterface $eventConfig
     * @param State $state
     * @param ScopeInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\Event\ConfigInterface $eventConfig,
        State $state,
        ScopeInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->eventConfig = $eventConfig;
        $this->appState = $state;
        parent::__construct();
    }

    protected function configure() {
        
        $options = [
            new InputOption(
				self::EVENT_CODE,
				'-e',
				InputOption::VALUE_REQUIRED,
				'Event code : --e catalog_product_get_final_price'
            ),
            new InputOption(
				self::SCOPE_CODE,
				'-s',
				InputOption::VALUE_OPTIONAL,
				'Scope : -s global|frontend|adminhtml|crontab|webapi_rest|webapi_soap|graphql'
            )
        ];
        
        $this->setName('beta_dev:show_observers');
        $this->setDescription('Show all observers for an event');
        $this->setDefinition($options);
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output){

        $scopes = [
            \Magento\Framework\App\Area::AREA_GLOBAL,
            \Magento\Framework\App\Area::AREA_FRONTEND,
            \Magento\Framework\App\Area::AREA_ADMINHTML,
            \Magento\Framework\App\Area::AREA_CRONTAB,
            \Magento\Framework\App\Area::AREA_WEBAPI_REST,
            \Magento\Framework\App\Area::AREA_WEBAPI_SOAP,
            \Magento\Framework\App\Area::AREA_GRAPHQL
            
        ];

        if ($eventCode = $input->getOption(self::EVENT_CODE)) {
            $inputScope = $input->getOption(self::SCOPE_CODE);
            $me = $this;
            array_walk($scopes, function($scope) use ($inputScope, $me, $output, $eventCode) {
                if($inputScope && $inputScope != $scope ){
                    return;
                }
                $this->scopeConfig->setCurrentScope($scope);
                $configs = $me->eventConfig->getObservers($eventCode);
                $outputStyle = new OutputFormatterStyle(null, null, ['bold', 'underscore']);
                $output->getFormatter()->setStyle('fire', $outputStyle);
                $output->writeln("<fire>Observers for scope {$scope} </>");
                if(count($configs)){
                    $tableConfigs = array_map(function($data, $key){
                        $instanceClass = new \ReflectionClass($data['instance']);
                        if (!$instanceClass->implementsInterface('Magento\Framework\Event\ObserverInterface'))
                        {
                            $data['check'] = sprintf('must implement interface "ObserverInterface" ');
                        }else{
                            $data['check'] = 'ok';
                        }
                        return $data;
                    }, $configs, array_keys($configs));

                    $table = new Table($output);
                    $table
                        ->setHeaders(array_keys( $tableConfigs[0]) )
                        ->setRows($tableConfigs);
                    ;
                    $table->render();
                }else{
                    $output->writeln("--There is no observer for this event --");
                }
            });
        }else{
            $output->writeln("--Please provide an event name --");
        }
    }

}