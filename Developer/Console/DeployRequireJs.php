<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Deploy\Service\DeployRequireJsConfig;

class DeployRequireJs extends Command {

    const THEME_PATH = 'theme_path'; 
    const LOCALE_CODE = 'locale_code'; 

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param ConfigLoaderInterface $configLoader
     * @param \Magento\Store\Model\Config\StoreView $storeView
     * @param DeployRequireJsConfig $deployRequireJsConfig
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ConfigLoaderInterface $configLoader,
        \Magento\Store\Model\Config\StoreView $storeView,
        DeployRequireJsConfig $deployRequireJsConfig
    ) {
        $this->state = $state;
        $this->objectManager = $objectManager;
        $this->configLoader = $configLoader;
        $this->storeView = $storeView;
        $this->deployRequireJsConfig = $deployRequireJsConfig;
        parent::__construct();
    }

    

    protected function configure() {
        
        $options = [
            new InputOption(
				self::THEME_PATH,
				'-t',
				InputOption::VALUE_REQUIRED,
				'Theme path: --t Magento/luna'
            ),
            new InputOption(
				self::LOCALE_CODE,
				'-l',
				InputOption::VALUE_OPTIONAL,
				'Locale code: --l da_DK '
			)
        ];
        
        $this->setName('beta_dev:deploy_requirejs');
        $this->setDescription('Deploy requirejs-config.js');
        $this->setDefinition($options);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $themePath = $input->getOption(self::THEME_PATH);
        if (!$themePath){
            $output->writeln("Please provide theme path");
            return;
        }
        $output->writeln($themePath);
        $languageCodes = $this->storeView->retrieveLocales();
        $this->state->setAreaCode('frontend');
        foreach($languageCodes as $languageCode){
            $languageInput = $input->getOption(self::LOCALE_CODE);
            if($languageInput && $languageInput != $languageCode){
                continue;
            }
            $this->objectManager->configure($this->configLoader->load('frontend'));
            $this->deployRequireJsConfig->deploy('frontend', $themePath, $languageCode);
            $output->writeln("Deployed requirejs-config.js -- ". $languageCode. "-- theme: ". $themePath);
        }
           
    }
}