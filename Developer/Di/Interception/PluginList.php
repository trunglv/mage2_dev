<?php
declare(strict_types=1);
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Di\Interception;

use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data\Scoped;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\Interception\DefinitionInterface;
use Magento\Framework\Interception\PluginListGenerator;
use Magento\Framework\Interception\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\RelationsInterface;
use Magento\Framework\ObjectManager\DefinitionInterface as ClassDefinitions;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Serialize;
use Betagento\Developer\Model\PluginFactory as PluginItemDataFactory;
use Betagento\Developer\Model\Plugin as PluginItemData;

class PluginList extends Scoped  {

   
    /**
     * Inherited plugin data
     *
     * @var array<string, mixed>
     */
    protected array $_inherited = [];

    /**
     * Inherited plugin data, preprocessed for read
     *
     * @var array<string, mixed>
     */
    protected array $_processed;

    
    
    public function __construct(
        protected ScopeInterface $configScope,
        protected CacheInterface $cache,
        protected RelationsInterface $_relations,
        protected ConfigInterface $_omConfig,
        protected ClassDefinitions $_classDefinitions,
        protected ObjectManagerInterface $_objectManager,
        protected ClassDefinitions $classDefinitions,
        protected DefinitionInterface $_intercepClassdefinitions,
        protected PluginItemDataFactory $pluginFactory,
        protected PluginListGenerator $pluginListGenerator,
        protected $cacheId = 'plugins'
    ) {
        $this->_scopePriorityScheme = ['global'];
        $serializer = $_objectManager->get(Serialize::class);
        $reader = $_objectManager->create(\Magento\Framework\ObjectManager\Config\Reader\Dom::class);
        parent::__construct($reader, $this->configScope, $cache, $cacheId, $serializer);
    }

    
    /**
     * Get Plugins for a class for a scope [frontend|adminhtml|cron|api]
     *
     * @param string $type
     * @param string $scope
     * @return array<int|string, array<int<0, max>, \Betagento\Developer\Model\Plugin>>
     */
    public function getPlugins($type, $scope) : array {

        $this->_loadScopedData($scope);
        
        $checkedClasses = [$type];
        if (interface_exists($type)) {
            $concreteType = $this->_omConfig->getPreference($type);
            $concreteType = str_replace("\Interceptor", "", $concreteType);
            $checkedClasses[] = $concreteType;
        }
        if (class_exists($type) && !interface_exists($type)) {
            $parentClasses = $this->_relations->getParents($type);
            if ($parentClasses) {
                $checkedClasses = array_merge($checkedClasses, $parentClasses);
            }
        }
        
        $plugins = [];
        foreach ($checkedClasses as $specificType) {
            $me = $this;
            if(isset($this->_data[$specificType]) && $pluginDefinition = $this->_data[$specificType]){
                $plugins[$specificType] = [];
                array_walk($pluginDefinition, function($item, $key) use ($me, &$plugins, $specificType, $scope) {            
                    $methods = $me->getPluginMethods($item['instance']);
                    foreach($methods as $methodName => $methodType){
                        $plugins[$specificType][] = 
                        $this->pluginFactory->create(
                            [
                                'data' => [
                                    'code' => $key,
                                    'original_method' => $methodName,
                                    'plugin_method_type' => $me->getPluginMethodType($methodType),
                                    'instance' => $item['instance'],
                                    'method_exists' => $me->isInjectedMethodExists($specificType, $methodName) ? 'method is ok' : 'method does not exist',
                                    'scope' => $scope
                                ]
                            ]);
                    }
                },[]);
            }
        }
        return $plugins;
    }

    /**
     * Get Plugins by listener type ['around','before','after']
     *
     * @param string $pluginListenerType
     * @param string $scope
     * @return array<PluginItemData>
     */
    public function getPluginsByListenerType($pluginListenerType, $scope)
    {
        $this->_loadScopedData($scope);
        $me = $this;
        $plugins = [];
        array_walk($this->_data, function($pluginList, $classType) use ($me, &$plugins, $pluginListenerType, $scope) {            
            if ($pluginList && count($pluginList)) {
                foreach ($pluginList as $pluginName => $pluginItem) {
                    if (!isset($pluginItem['instance'])) {
                        continue;
                    }
                    $methods = $me->getPluginMethods($pluginItem['instance']);
                    foreach($methods as $methodName => $methodType){
                        if ($pluginListenerType != $me->getPluginMethodType($methodType)) {
                            continue;
                        }
                        $plugins[] = $this->pluginFactory->create(
                            [
                                'data' => [
                                    'class' => $classType,
                                    'code' => $pluginName,
                                    'original_method' => $methodName,
                                    'plugin_method_type' => $me->getPluginMethodType($methodType),
                                    'instance' => $pluginItem['instance'],
                                    'method_exists' => $me->isInjectedMethodExists($classType, $methodName) ? 'method is ok' : 'method does not exist',
                                    'scope' => $scope
                                ]
                            ]
                        );
                    }
                }
            }
            
        },[]);
        return $plugins;
    }

    /**
     * Get all plugin methods
     *
     * @param string $pluginInstanceName
     * @return array<string>
     */
    protected function getPluginMethods($pluginInstanceName){
        $pluginType = $this->_omConfig->getOriginalInstanceType($pluginInstanceName);
        if (!class_exists($pluginType)) {
            throw new \InvalidArgumentException('Plugin class ' . $pluginInstanceName . ' doesn\'t exist');
        }
        return $this->_intercepClassdefinitions->getMethodList($pluginInstanceName);
    }

    /**
     * Check method that is injected whether exists or not
     *
     * @param string $instanceType
     * @param string $pluginMethod
     * @return boolean
     */
    protected function isInjectedMethodExists(string $instanceType, $pluginMethod){
        try {
           
            $class = new \ReflectionClass($instanceType);
            $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
            
            return count(array_filter($methods, function($method) use ($pluginMethod) {
                return $method->getName() == $pluginMethod;
            })) > 0  ? true : false ;
        } catch (\Exception $ex) {
            return false;
        }
        
    }

    /**
     * Get Injected Method Type
     *
     * @param string $methodType
     * @return string
     */
    protected function getPluginMethodType($methodType){
        switch($methodType){
            case DefinitionInterface::LISTENER_AROUND:
                return 'around';
            case DefinitionInterface::LISTENER_BEFORE:
                return 'before';   
            case DefinitionInterface::LISTENER_AFTER:
                return 'after';            
        }
        return '';
    }

    /**
     * Load configuration for the current scope
     * 
     * @param string $givenScope
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _loadScopedData($givenScope = null)
    {

        [$this->_data, $this->_inherited, $this->_processed] = [[],[],[]];
        $scope = $givenScope ? $givenScope :  $this->_configScope->getCurrentScope();
        $this->_scopePriorityScheme = [$scope];
        
        [
            $virtualTypes,
            $this->_scopePriorityScheme,
            $this->_loadedScopes,
            $this->_data,
            $this->_inherited,
            $this->_processed
        ] = $this->pluginListGenerator->loadScopedVirtualTypes(
            $this->_scopePriorityScheme,
            $this->_loadedScopes,
            $this->_data,
            $this->_inherited,
            $this->_processed
        );
        foreach ($virtualTypes as $class) {
            $this->_inheritPlugins($class);
        }
        foreach ($this->getClassDefinitions() as $class) {
            $this->_inheritPlugins($class);
        }
    }

    /**
     * Returns class definitions
     *
     * @return array<string>
     */
    protected function getClassDefinitions()
    {
        return $this->_classDefinitions->getClasses();
    }

    /**
     * Collect parent types configuration for requested type
     *
     * @param string $type
     * @return array<mixed>
     */
    protected function _inheritPlugins($type)
    {
         return $this->pluginListGenerator->inheritPlugins($type, $this->_data, $this->_inherited, $this->_processed);
         
    }
}
