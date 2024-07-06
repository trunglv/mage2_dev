<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
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
use Magento\Framework\Filesystem\Driver\File as FileDriver;
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
     * @var \Magento\Framework\App\State $state
     */
    protected $state;

    /**
     * @var \Magento\Framework\App\View\Asset\Publisher
     */
    protected $publisher;

    /**
     * @var \Magento\Framework\View\Asset\Repository 
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
     * @var \Magento\Framework\Module\Dir
     */
    protected $moduleDir;

    /**
     * @var array<string,mixed>
     */
    protected $assetParams = [];

    /**
     * @var \Symfony\Component\Console\Helper\Table
     */
    protected $tableOutput;

    
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\App\View\Asset\Publisher $publisher
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param ConfigLoaderInterface $configLoader
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Store\Model\Config\StoreView $storeView
     * @param \Magento\Framework\Module\Dir $moduleDir
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\View\Asset\Publisher $publisher,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ConfigLoaderInterface $configLoader,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\Config\StoreView $storeView,
        \Magento\Framework\Module\Dir $moduleDir,
        protected FileDriver $fileDriver
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
     * @return void
     */
    protected function configure()
    {
        
        $options = [
        new InputOption(
            self::FILE_PATH,
            '-f',
            InputOption::VALUE_REQUIRED,
            "File Path/ Directory Path: for specific file -f js/view/shipping-address/address-renderer/default.js OR for a specific folder -f js"
        ),
            new InputOption(
                self::THEME_PATH,
                '-t',
                InputOption::VALUE_REQUIRED,
                'Theme path: -t Magento/luna'
            ),
            new InputOption(
                self::AREA_CODE,
                '-a',
                InputOption::VALUE_REQUIRED,
                'Area Code: -a frontend|adminhtml'
            ),
            new InputOption(
                self::MODULE_NAME,
                '-m',
                InputOption::VALUE_OPTIONAL,
                'Module name: -m Magento_Checkout'
            ),
            new InputOption(
                self::LOCALE_CODE,
                '-l',
                InputOption::VALUE_OPTIONAL,
                'Locale code: -l da_DK '
            )
        ];
        
        $this->setName('beta_dev:deploy_static');
        $this->setDescription("Deploy a static file.". 
            PHP_EOL . "  ---> Deploy static files from a/an module/extension.".
            PHP_EOL . "  E.g. bin/magento beta_dev:deploy_static -f js/view/shipping-address/address-renderer/default.js -t Magento/luna -m Magento_Checkout".
            PHP_EOL . "  ---> Deploy Css files base on a theme base Magento-Luna architecure.".
            PHP_EOL . "  E.g. bin/magento beta_dev:deploy_static -f css/styles-l.css -t Magento/luna".
            PHP_EOL . "  E.g. bin/magento beta_dev:deploy_static -f css/styles-m.css -t Magento/luna".
            PHP_EOL . "  ---> Deploy JS Translation Json File base on a theme base Magento-Luna architecure.".
            PHP_EOL . "  E.g. bin/magento   beta_dev:deploy_static -t Magento/blank -f js-translation.json"
        );
            
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * @inheritDoc
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($file = $input->getOption(self::FILE_PATH)) || !($themeName = $input->getOption(self::THEME_PATH))) {
            $output->writeln("Please provide either a file name and theme name. Type bin/magento beta_dev:deploy_static --help for more information.");
            return self::FAILURE;
        }
            
        //$this->output = $output;
        $this->tableOutput = new Table($output);
        $this->tableOutput->setHeaders(['path', 'absolute_path']);
        /**
         * @var string $area
         */
        $area = $input->getOption(self::AREA_CODE) ? $input->getOption(self::AREA_CODE) : 'frontend';
        $this->state->setAreaCode($area);
        /**
         * @var string $moduleName
         */
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
        /*
        if (!file_exists($viewDir)) {
            $output->writeln("Can't find a file or a directory for this path : ". $viewDir);
            return;
        }
        */
        if(is_dir($viewDir)) {
            $dirIterator = new \RecursiveDirectoryIterator($viewDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $recursiveIterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($recursiveIterator as $actionFile) {
                $filePath = str_replace($viewDir, '', $actionFile->getPathname());
                $files[] = $file. $filePath;
                
            }
        }else{
            $files = [$file];
        }

        try {
            $publishAssetClosure = \Closure::fromCallable([$this, 'executeAsset']);
            array_walk($files, $publishAssetClosure);
            $this->tableOutput->render();
            return self::SUCCESS;
            
        } catch (\Exception $ex) {
            $output->writeln(sprintf("Has a problem while to proceed the file %s: %s", $file, $ex->getMessage()) . PHP_EOL);
        }
        return self::FAILURE;
        
    }

    /**
     * Publish a asset File
     * @param int $key
     * @param  mixed $file
     * @return mixed
     */
    protected function executeAsset($file, $key)
    {
        /**
         * @var string $file
         */
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
                        $this->fileDriver->deleteFile($absolutePath);
                    }
                }
                $this->publisher->publish($asset);
                $this->tableOutput->addRow([ str_replace($rootPath, "", $asset->getSourceFile()) , $absolutePath]);

            }catch(\Exception $ex){
               throw $ex;
            }
            
        }
        $this->tableOutput->addRow(new TableSeparator());
        return $file;
    }
}
