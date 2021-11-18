<?php
/**
 * @trunglv 
 */
namespace Betagento\Developer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Module\Dir;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
/**
 * Deploy Static File
 */

class DeployStaticFile extends Command
{

    const FILE_PATH = 'file_path';
    const THEME_PATH = 'theme_path';
    const MODULE_NAME = 'module_name';
    const LOCALE_CODE = 'locale_code';
    const AREA_CODE = 'area';


    /**
     * @var \Magento\Framework\App\State $name
     */
    protected $state;

    /**
     * @var \Magento\Framework\App\View\Asset\Publisher
     */
    protected $publisher;

    /**
     * @var Magento\Framework\View\Asset\Repository 
     */
    protected $assetRepo;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * @var ConfigLoaderInterface
     */
    protected $configLoader;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Store\Model\Config\StoreView
     */
    protected $storeView;

    /**
     * @param Dir
     */
    private $moduleDir;

    /**
     * @var array
     */
    protected $assetParams = [];

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var \Symfony\Component\Console\Helper\Table
     */
    private $tableOutput;
    /**
     * Constructor function
     *
     * @param \Magento\Framework\App\State                $state
     * @param \Magento\Framework\App\Request\Http         $request
     * @param \Magento\Framework\App\View\Asset\Publisher $publisher
     * @param \Magento\Framework\View\Asset\Repository    $assetRepo
     * @param \Magento\Framework\Module\ModuleList        $moduleList
     * @param \Magento\Framework\ObjectManagerInterface   $objectManager
     * @param ConfigLoaderInterface                       $configLoader
     * @param \Magento\Framework\Filesystem               $filesystem
     * @param \Magento\Store\Model\Config\StoreView       $storeView
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\View\Asset\Publisher $publisher,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ConfigLoaderInterface $configLoader,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\Config\StoreView $storeView,
        Dir $moduleDir
    ) {
        $this->state = $state;
        $this->publisher = $publisher;
        $this->assetRepo = $assetRepo;
        $this->objectManager = $objectManager;
        $this->configLoader = $configLoader;
        $this->filesystem = $filesystem;
        $this->storeView = $storeView;
        $this->moduleDir = $moduleDir;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        
        $options = [
        new InputOption(
            self::FILE_PATH,
            '-f',
            InputOption::VALUE_REQUIRED,
            "File Path/ Directory Path: --f js/view/shipping-address/address-renderer/default.js --f js"
        ),
            new InputOption(
                self::THEME_PATH,
                '-t',
                InputOption::VALUE_REQUIRED,
                'Theme path: --t Magento/luna'
            ),
            new InputOption(
                self::AREA_CODE,
                '-a',
                InputOption::VALUE_REQUIRED,
                'Area Code: --a frontend|adminhtml'
            ),
            new InputOption(
                self::MODULE_NAME,
                '-m',
                InputOption::VALUE_OPTIONAL,
                'Module name: --m Magento_Checkout'
            ),
            new InputOption(
                self::LOCALE_CODE,
                '-l',
                InputOption::VALUE_OPTIONAL,
                'Locale code: --l da_DK '
            )
        ];
        
        $this->setName('beta_dev:deploy_static');
        $this->setDescription('Deploy a static file');
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($file = $input->getOption(self::FILE_PATH)) || !($themeName = $input->getOption(self::THEME_PATH))) {
            $output->writeln("Please provide either a file name and theme name");
            return;
        }
            
        $this->output = $output;
        $this->tableOutput = new Table($output);
        $this->tableOutput->setHeaders(['path', 'absolute_path']);

        $area = $input->getOption(self::AREA_CODE) ? $input->getOption(self::AREA_CODE) : 'frontend';
        $this->state->setAreaCode($area);
        $moduleName = $input->getOption(self::MODULE_NAME) ? $input->getOption(self::MODULE_NAME) : '';
        $specificLanguageCode = $input->getOption(self::LOCALE_CODE);
        
        $this->assetParams = [
            'area' => $area,
            'theme' => $themeName,
            //'locale' => $languageCode,
            'module' =>  $moduleName,
            'specific_locale' => $specificLanguageCode
        ];

        $separator = DIRECTORY_SEPARATOR;
        $viewDir = $this->moduleDir->getDir($moduleName, Dir::MODULE_VIEW_DIR);
        $viewDir .= $separator . $area . $separator . 'web'. $separator .$file ;
        $files = [];
        if(is_dir($viewDir)) {
            if (!file_exists($viewDir)) {
                $output->writeln("Can't find a directory in system");
                return;
            }
            $actions[$moduleName] = [];
            $dirIterator = new \RecursiveDirectoryIterator($viewDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $recursiveIterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($recursiveIterator as $actionFile) {
                $filePath = str_replace($viewDir, '', $actionFile->getPathname());
                $files[] = $file. $filePath;
                
            }
        }else{
            $files = [$file];
        }

        $publishAssetClosure = \Closure::fromCallable([$this, 'executeAsset']);
        array_walk($files, $publishAssetClosure);
        $this->tableOutput->render();
    }

    /**
     * Publish a asset File
     *
     * @param  string $file
     * @return void
     */
    protected function executeAsset($file)
    {
        $languageCodes = $this->storeView->retrieveLocales();
        $specifyLanguageCode = !empty($this->assetParams['specific_locale']) ? $this->assetParams['specific_locale'] : '';
        $rootPath = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)->getAbsolutePath();
        foreach($languageCodes as $languageCode)
        {
            try{
                if($specifyLanguageCode && $specifyLanguageCode != $languageCode) {
                    continue;
                }
                $this->assetParams['locale'] = $languageCode;    
                $this->objectManager->configure($this->configLoader->load($this->assetParams['area']));
                $asset = $this->assetRepo->createAsset($file, $this->assetParams);
                $dir = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);
                $absolutePath = $dir->getAbsolutePath($asset->getPath());
                if ($dir->isExist($asset->getPath())) {
                    if(file_exists($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }
                $this->publisher->publish($asset);
                //$this->output->writeln(sprintf("Proceed the file %s", $absolutePath) .PHP_EOL);
                $this->tableOutput->addRow([ str_replace($rootPath, "", $asset->getSourceFile()) , $absolutePath]);

            }catch(\Exception $ex){
               
                //$this->output->writeln(sprintf("Has a problem while to proceed the file %s: %s", $file, $ex->getMessage()) . PHP_EOL);
            }
            
            //exit;
        }
        $this->tableOutput->addRow(new TableSeparator());
    }
}
