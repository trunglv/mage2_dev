<?php
namespace Betagento\Developer\Console\Controller;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Magento\Framework\App\Route\ConfigInterface;
use Magento\Framework\Module\Dir;
use Symfony\Component\Console\Helper\Table;

class ActionList extends Command {

    const FRONT_NAME = 'frontname'; 
    const AREA_CODE = 'area'; 

    /*
     * @var ModuleReader
     */
    protected $moduleReader;

    /*
     * @var ConfigInterface
     */
    protected $routeConfig;
    
    /*
     * @var Dir
     */
    protected $moduleDir;
    
    /**
     * Constructor
     *
     * @param ModuleReader $state
     */
    public function __construct(
        ModuleReader $moduleReader,
        ConfigInterface $routeConfig,
        Dir $moduleDir
    ) {
        $this->moduleReader = $moduleReader;
        $this->routeConfig = $routeConfig;
        $this->moduleDir = $moduleDir;
        parent::__construct();
    }

    
    /**
     * @var array
     */
    protected $reservedWords = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare',
        'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
        'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof',
        'insteadof','interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected',
        'public', 'require', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var',
        'while', 'xor', 'void',
    ];


    protected function configure() {
        
        $options = [
            new InputOption(
				self::FRONT_NAME,
				'-f',
				InputOption::VALUE_REQUIRED,
				'Frontname : --m catalog'
            ),
            new InputOption(
				self::AREA_CODE,
				'-a',
				InputOption::VALUE_REQUIRED,
				'Area Code: --a frontend|adminhtml '
			)
        ];
        
        $this->setName('beta_dev:show_controller_action');
        $this->setDescription('Show all controller actions for a frontname per a scope');
        $this->setDefinition($options);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        if ( !( $frontName = $input->getOption(self::FRONT_NAME) ) || !($area = $input->getOption(self::AREA_CODE) ) ) {

            $output->writeln("Front name or Area Code");
            return;
        }
            
        $actions = [];
        try{
            $modules = $this->routeConfig->getModulesByFrontName($frontName , $area);
        }catch(\Throwable $ex){
            $output->writeln("Front name or Area Code is invalid!");
            return;
        }
        
        foreach($modules as $moduleName){
            $actionDir = $this->moduleDir->getDir($moduleName , Dir::MODULE_CONTROLLER_DIR) ;
        
            if (!file_exists($actionDir)) {
                $output->writeln("Dir is not exist");
            }
            $actions[$moduleName] = [];
            $dirIterator = new \RecursiveDirectoryIterator($actionDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $recursiveIterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::LEAVES_ONLY);
            $namespace = str_replace('_', '\\', $moduleName);
            /** @var \SplFileInfo $actionFile */

            foreach ($recursiveIterator as $actionFile) {

                if($area == 'frontend')
                    if(in_array( 'Adminhtml', explode("/", str_replace($actionDir, '', $actionFile->getPathname()) ) )) continue;
        
                if($area == 'adminhtml')
                    if(!in_array( 'Adminhtml', explode("/", str_replace($actionDir, '', $actionFile->getPathname()) ) )) continue;
                
                $actionName = str_replace('/', '\\', str_replace($actionDir, '', $actionFile->getPathname()));
                $action = $namespace . "\\" . Dir::MODULE_CONTROLLER_DIR . substr($actionName, 0, -4);

                if(is_subclass_of($action, \Magento\Framework\App\ActionInterface::class)){

                    $controllerClass =  new \ReflectionClass($action);
                    if(!$controllerClass->isAbstract() && !$controllerClass->isInterface())
                        $actions[$moduleName][strtolower($action)] = 
                            [
                                'action_class' => $action,
                                'path' => $this->getPossibleUrlPath($moduleName, $frontName, $area, strtolower($action))
                            ]
                        ;
                }
            }

            if(count($actions)){
                $outputStyle = new OutputFormatterStyle(null, null, ['bold', 'underscore']);
                $output->getFormatter()->setStyle('fire', $outputStyle);
                $output->writeln("<fire>Controller Actions are defined in a module {$moduleName} </>");

                $table = new Table($output);
                $table
                    ->setHeaders(array_keys(current($actions[$moduleName])) )
                    ->setRows(array_map(function($action){
                        return $action;
                    }, $actions[$moduleName] ))
                ;
                $table->render();
                if($area == 'adminhtml')
                    $output->writeln("[ADMIN_PATH_CONFIG] is 'admin' by default, but can be adjusted by Magento Configuration!");
            }
        }
           
    }

    protected function getPossibleUrlPath($module, $frontName, $area, $actionPath){

        if($area == 'frontend') $area = '';
            $modudePath  = str_replace('_','\\', strtolower($module));
        
        $modudePath = $modudePath.'\\controller\\'. ($area ? $area.'\\' : '') ;
        $path = str_replace($modudePath, '' ,$actionPath);
        $actionPaths = explode('\\',$path);
        $actionName = array_pop($actionPaths);
        if(in_array($actionName, $this->reservedWords)){
            return 'Action name is invalid -- due to same to programming syntax';
        }
        $contollerPath = implode("_",$actionPaths);
        $path = $frontName. '\\' . $contollerPath . '\\' . $actionName;
        if($area == 'adminhtml')
            $path = '[ADMIN_PATH_CONFIG]\\'. $path;

        return $path;
    }

}
