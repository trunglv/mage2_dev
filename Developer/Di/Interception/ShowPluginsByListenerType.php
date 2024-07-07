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

class ShowPluginsByListenerType extends Command{

    const PLUGIN_TYPE = 'plugin_type';
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
				self::PLUGIN_TYPE,
				'-t',
				InputOption::VALUE_REQUIRED,
				"Class type : -t around"
            ),
            new InputOption(
				self::SCOPE_CODE,
				'-s',
				InputOption::VALUE_OPTIONAL,
				sprintf('Scope : -s %s|%s|%s|%s|%s', Area::AREA_GLOBAL, Area::AREA_FRONTEND, Area::AREA_ADMINHTML, Area::AREA_WEBAPI_REST, Area::AREA_GRAPHQL)
            )
        ];
        
        $this->setName('beta_dev:show_plugins_by_listener_type');
        $this->setDescription('Show all plugins by a plugin listener type and a specific scopes' .PHP_EOL . "e.g. bin/magento beta_dev:show_plugins -t around -s global");
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
       
        if (!$pluginType = $input->getOption(self::PLUGIN_TYPE)) {
            $output->writeln("Please input a class name for a type, example bin/magento beta_dev:show_plugins -t around  ");
            return Command::FAILURE; 
        }
        $scopes = $input->getOption(self::SCOPE_CODE) 
            ? [$input->getOption(self::SCOPE_CODE)] :
            [Area::AREA_GLOBAL, Area::AREA_FRONTEND, Area::AREA_ADMINHTML, Area::AREA_WEBAPI_REST, Area::AREA_GRAPHQL] ;
        
        foreach ($scopes as $scope) {
            $plugins = $this->pluginCollector->getPluginsByListenerType(($pluginType), strval($scope));
            if (!count($plugins)) {
                $output->writeln(sprintf("-- No specific scoped %s plugins injected %s in %s --", $pluginType, $scope));
                continue;
            }
            $fireOutputStyle = new OutputFormatterStyle("red", null, ['bold', 'underscore']);
            $output->getFormatter()->setStyle('fire', $fireOutputStyle);
            $output->writeln("<fire> ------Plugins for Scope ".$scope."------ </fire>");
            $output->writeln("");
            if(count($plugins)){
                $table = new Table($output);
                $table
                    ->setHeaders(array_keys( $plugins[0]) )
                    ->setRows(array_map(function($plugins){
                        return $plugins;
                    }, $plugins ))
                ;
                $table->render();
            }else{
                $output->writeln("-- No plugins injected --");
            }
            $output->writeln("");
            $output->getFormatter()->setStyle('fire', $fireOutputStyle);
            $output->writeln("<fire> ----- END Plugins for Scope ------".$scope."</fire>");
            $output->writeln("");
            $output->writeln("");
        }
        return Command::SUCCESS;
    }

}
