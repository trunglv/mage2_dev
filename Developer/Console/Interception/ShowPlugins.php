<?php 
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Console\Interception;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Betagento\Developer\Di\Interception\PluginList;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Magento\Framework\App\Area;

class ShowPlugins extends Command{

    const OBJECT_TYPE = 'type';
    const SCOPE_CODE = 'scope_code';

    /**
     * @var PluginList
     */
    protected $pluginCollector;

    public function __construct(
        PluginList $pluginCollector
    )
    {
        $this->pluginCollector = $pluginCollector;
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
        
        $this->setName('beta_dev:show_plugins');
        $this->setDescription('Show all plugins which are injected into a class by specific scopes' .PHP_EOL . "e.g. bin/magento beta_dev:show_plugins -t 'Magento\Quote\Api\CartManagementInterface' -s global");
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
        $scopes = $input->getOption(self::SCOPE_CODE) 
            ? [$input->getOption(self::SCOPE_CODE)] :
            [Area::AREA_GLOBAL, Area::AREA_FRONTEND, Area::AREA_ADMINHTML, Area::AREA_WEBAPI_REST, Area::AREA_GRAPHQL] ;
        
        foreach ($scopes as $scope) {
            $plugins = $this->pluginCollector->getPlugins(($objectType), strval($scope));
            if (!count($plugins)) {
                $output->writeln(sprintf("-- No specific scoped plugins injected for %ss in %s --", $objectType, $scope));
                continue;
            }
            $fireOutputStyle = new OutputFormatterStyle("red", null, ['bold', 'underscore']);
            $output->getFormatter()->setStyle('fire', $fireOutputStyle);
            $output->writeln("<fire> ------Plugins for Scope ".$scope."------ </fire>");
            $output->writeln("");
            
            foreach ($plugins as $type => $pluginTable) {
                $greenStyle = new OutputFormatterStyle("green", null , ['bold', 'underscore']);
                $output->getFormatter()->setStyle('green', $greenStyle);
                $output->writeln("<green>Plugins for type ".$type."</green>");
                $output->writeln("");

                if(count($pluginTable)){
                    $table = new Table($output);
                    $table
                        ->setHeaders(array_keys( $pluginTable[0]) )
                        ->setRows(array_map(function($pluginTable){
                            return $pluginTable;
                        }, $pluginTable ))
                    ;
                    $table->render();
                }else{
                    $output->writeln("-- No plugins injected --");
                }
            }
            $output->writeln("");
            $output->getFormatter()->setStyle('fire', $fireOutputStyle);
            $output->writeln("<fire> ----- END Plugins for Scope ------".$scope."</fire>");
            
        }
        
        return Command::SUCCESS;
    }

}
