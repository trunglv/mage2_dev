<?php 
namespace Betagento\Developer\Console\Interception;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;



use Betagento\Developer\Console\Interception\PluginList;

class ShowPlugins extends Command{

    const OBJECT_TYPE = 'type';
    const SCOPE_CODE = 'scope_code';

    protected $plugins;

    public function __construct(
        PluginList $plugins
    )
    {
        $this->plugins = $plugins;
        parent::__construct();
    }

    protected function configure() {
        
        $options = [
            new InputOption(
				self::OBJECT_TYPE,
				'-t',
				InputOption::VALUE_REQUIRED,
				'Class type : --t Magento/Catalog/Model/Product'
            ),
            new InputOption(
				self::SCOPE_CODE,
				'-s',
				InputOption::VALUE_OPTIONAL,
				'Scope : -s adminhtml|frontend|cron|api'
            )
        ];
        
        $this->setName('beta_dev:show_plugins');
        $this->setDescription('Show all plugins injected into a class');
        $this->setDefinition($options);
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output){

        if ($objectType = $input->getOption(self::OBJECT_TYPE)) {
            $plugins = $this->plugins->getPlugins($objectType, $input->getOption(self::SCOPE_CODE));
            if(count($plugins)){
                $table = new Table($output);
                $table
                    ->setHeaders(array_keys( $plugins[0]) )
                    ->setRows(array_map(function($plugin){
                        return $plugin;
                    }, $plugins ))
                ;
                $table->render();
            }else{
                $output->writeln("-- No plugins injected --");
            }
            
        }
        
    }

}