<?php
namespace Betagento\Developer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Symfony\Component\Console\Input\InputOption;

class DeployStaticFile extends Command {

    const FILE_PATH = 'file_path';
    const THEME_PATH = 'theme_path';
    const MODULE_NAME = 'module_name';
    const LOCALE_CODE = 'locale_code';
    /**
     * Constructor function
     *
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Magento\Framework\App\View\Asset\Publisher $publisher
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\Module\ModuleList $moduleList
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param ConfigLoaderInterface $configLoader
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Store\Model\Config\StoreView $storeView
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\App\View\Asset\Publisher $publisher,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\Module\ModuleList $moduleList,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ConfigLoaderInterface $configLoader,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\Config\StoreView $storeView
    ) {
        $this->state = $state;
        
        $this->request = $request;
        $this->publisher = $publisher;
        $this->assetRepo = $assetRepo;
        $this->moduleList = $moduleList;
        $this->objectManager = $objectManager;
        $this->configLoader = $configLoader;
        $this->filesystem = $filesystem;
        $this->storeView = $storeView;
        parent::__construct();
    }

    

    protected function configure() {
        
        $options = [
			new InputOption(
				self::FILE_PATH,
				'-f',
				InputOption::VALUE_REQUIRED,
				"File Path: --f js/view/shipping-address/address-renderer/default.js"
            ),
            new InputOption(
				self::THEME_PATH,
				'-t',
				InputOption::VALUE_REQUIRED,
				'Theme path: --t Magento/luna'
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

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        if (($file = $input->getOption(self::FILE_PATH)) && ($themeName = $input->getOption(self::THEME_PATH))) {
            $languageCodes = $this->storeView->retrieveLocales();
            
            $this->state->setAreaCode('frontend');
            foreach($languageCodes as $languageCode){
                $languageInput = $input->getOption(self::LOCALE_CODE);
                if($languageInput && $languageInput != $languageCode){
                    continue;
                }
                $params = [
                    'area' => 'frontend',
                    'theme' => $themeName,
                    'locale' => $languageCode,
                    'module' =>  $input->getOption(self::MODULE_NAME) ? $input->getOption(self::MODULE_NAME) : ''
                ];
                
                $this->objectManager->configure($this->configLoader->load($params['area']));
                $asset = $this->assetRepo->createAsset($file, $params);
                //$asset->getRelativeSourceFilePath()
                $dir = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);
                if ($dir->isExist($asset->getPath())) {
                    //$asset->getPath()
                    $absolutePath = $dir->getAbsolutePath($asset->getPath());
                    @unlink($absolutePath);
                    //$output->writeln('Delete a file : '.$absolutePath);
                }
                $this->publisher->publish($asset);
                $output->writeln("Done -- ". $absolutePath);
                //exit;
            }

		} else {
			$output->writeln("Please provide either a file name and theme name");
        }
           
    }
}
