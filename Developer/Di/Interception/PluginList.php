<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Di\Interception;

use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data\Scoped;
use Magento\Framework\Config\ReaderInterface;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\Interception\ConfigLoaderInterface;
use Magento\Framework\Interception\DefinitionInterface;
use Magento\Framework\Interception\PluginListGenerator;
use Magento\Framework\Interception\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\RelationsInterface;
use Magento\Framework\ObjectManager\DefinitionInterface as ClassDefinitions;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Serialize\Serializer\Serialize;



class PluginList extends Scoped  {

     /**
     * Inherited plugin data
     *
     * @var array
     */
    protected $_inherited = [];

    /**
     * Inherited plugin data, preprocessed for read
     *
     * @var array
     */
    protected $_processed;

    /**
     * Type config
     *
     * @var ConfigInterface
     */
    protected $_omConfig;

    /**
     * Class relations information provider
     *
     * @var RelationsInterface
     */
    protected $_relations;

    /**
     * List of interception methods per plugin
     *
     * @var DefinitionInterface
     */
    protected $_definitions;

    /**
     * List of interceptable application classes
     *
     * @var ClassDefinitions
     */
    protected $_classDefinitions;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var array
     */
    protected $_pluginInstances = [];

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ConfigLoaderInterface
     */
    private $configLoader;

    /**
     * @var PluginListGenerator
     */
    private $pluginListGenerator;

    /**
     * Constructor
     *
     * @param ReaderInterface $reader
     * @param ScopeInterface $configScope
     * @param CacheInterface $cache
     * @param RelationsInterface $relations
     * @param ConfigInterface $omConfig
     * @param DefinitionInterface $definitions
     * @param ObjectManagerInterface $objectManager
     * @param ClassDefinitions $classDefinitions
     * @param array $scopePriorityScheme
     * @param string|null $cacheId
     * @param SerializerInterface|null $serializer
     * @param ConfigLoaderInterface|null $configLoader
     * @param PluginListGenerator|null $pluginListGenerator
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\ObjectManager\Config\Reader\Dom $reader,
        ScopeInterface $configScope,
        CacheInterface $cache,
        RelationsInterface $relations,
        ConfigInterface $omConfig,
        DefinitionInterface $definitions,
        ObjectManagerInterface $objectManager,
        ClassDefinitions $classDefinitions,
        array $scopePriorityScheme = ['global'],
        $cacheId = 'plugins',
        SerializerInterface $serializer = null,
        ConfigLoaderInterface $configLoader = null,
        PluginListGenerator $pluginListGenerator = null
    ) {
        $this->serializer = $serializer ?: $objectManager->get(Serialize::class);
        parent::__construct($reader, $configScope, $cache, $cacheId, $this->serializer);
        $this->_omConfig = $omConfig;
        $this->_relations = $relations;
        $this->_definitions = $definitions;
        $this->_classDefinitions = $classDefinitions;
        $this->_scopePriorityScheme = $scopePriorityScheme;
        $this->_objectManager = $objectManager;
        $this->configLoader = $configLoader ?: $this->_objectManager->get(ConfigLoaderInterface::class);
        $this->pluginListGenerator = $pluginListGenerator ?: $this->_objectManager->get(PluginListGenerator::class);
    }

    
    /**
     * Get Plugins for a class for a scope [frontend|adminhtml|cron|api]
     *
     * @param string $type
     * @param string $scope
     * @return array
     */
    public function getPlugins($type, $scope) : array{
        $this->_loadScopedData($scope);
        if (!isset($this->_pluginInstances[$type])) {
            if (!isset($this->_inherited[$type]) && !array_key_exists($type, $this->_inherited)) {
                $this->_inheritPlugins($type);
            }
        }
        $plugins = [];
        $me = $this;
        if(isset($this->_inherited[$type])){
            array_walk($this->_inherited[$type], function($item, $key) use ($me, &$plugins, $type) {            
                $methods = $me->getPluginMethods($item['instance']);
                foreach($methods as $methodName => $methodType){
                    $plugins[] = [
                        'code' => $key,
                        'original_method' => $methodName,
                        'plugin_method_type' => $me->getPluginMethodType($methodType),
                        'instance' => $item['instance'],
                        'method_exists' => $me->isInjectedMethodExists($type, $methodName)
                    ];
                }
            },[]);
        }
        return $plugins;
    }

    /**
     * Get all plugin methods
     *
     * @param string $pluginInstanceName
     * @return []
     */
    protected function getPluginMethods($pluginInstanceName){
        $pluginType = $this->_omConfig->getOriginalInstanceType($pluginInstanceName);
        if (!class_exists($pluginType)) {
            throw new \InvalidArgumentException('Plugin class ' . $pluginInstanceName . ' doesn\'t exist');
        }
        return $this->_definitions->getMethodList($pluginInstanceName);
    }

    /**
     * Check method that is injected whether exists or not
     *
     * @param string $instanceType
     * @param string $pluginMethod
     * @return boolean
     */
    protected function isInjectedMethodExists($instanceType, $pluginMethod){
       
        $methods = get_class_methods($pluginMethod);
        $class = new \ReflectionClass($instanceType);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        return count(array_filter($methods, function($method) use ($pluginMethod) {
            return $method->getName() == $pluginMethod;
        })) > 0  ? 'method is ok' : 'method does not exist' ;
    }

    /**
     * Get Injected Method Type
     *
     * @param string $methodType
     * @return void
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
     * Load configuration for current scope
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _loadScopedData($givenScope = null)
    {
        $scope = $givenScope ? $givenScope :  $this->_configScope->getCurrentScope();
        if (false === isset($this->_loadedScopes[$scope])) {
            $index = array_search($scope, $this->_scopePriorityScheme, true);
            /**
             * Force current scope to be at the end of the scheme to ensure that default priority scopes are loaded.
             * Mostly happens when the current scope is primary.
             * For instance if the default scope priority scheme is [primary, global] and current scope is primary,
             * the resulted scheme will be [global, primary] so global scope is loaded.
             */
            if ($index !== false) {
                unset($this->_scopePriorityScheme[$index]);
            }
            $this->_scopePriorityScheme[] = $scope;

            $cacheId = implode('|', $this->_scopePriorityScheme) . "|" . $this->_cacheId;
            $configData = $this->configLoader->load($cacheId);

            if ($configData) {
                [$this->_data, $this->_inherited, $this->_processed] = $configData;
                $this->_loadedScopes[$scope] = true;
            } else {
                $data = $this->_cache->load($cacheId);
                if ($data) {
                    [$this->_data, $this->_inherited, $this->_processed] = $this->serializer->unserialize($data);
                    foreach ($this->_scopePriorityScheme as $scopeCode) {
                        $this->_loadedScopes[$scopeCode] = true;
                    }
                } else {
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
                    $this->_cache->save(
                        $this->serializer->serialize([$this->_data, $this->_inherited, $this->_processed]),
                        $cacheId
                    );
                }
            }
            $this->_pluginInstances = [];
        }
    }

    /**
     * Whether scope code is current scope code
     *
     * @param string $scopeCode
     * @return bool
     */
    protected function isCurrentScope($scopeCode)
    {
        return $this->_configScope->getCurrentScope() === $scopeCode;
    }

    /**
     * Returns class definitions
     *
     * @return array
     */
    protected function getClassDefinitions()
    {
        return $this->_classDefinitions->getClasses();
    }

    /**
     * Merge configuration
     *
     * @param array $config
     * @return void
     */
    public function merge(array $config)
    {
        $this->_data = $this->pluginListGenerator->merge($config, $this->_data);
    }

    /**
     * Collect parent types configuration for requested type
     *
     * @param string $type
     * @return array
     */
    protected function _inheritPlugins($type)
    {
         return $this->pluginListGenerator->inheritPlugins($type, $this->_data, $this->_inherited, $this->_processed);
         
    }

    /**
     * Sort items
     *
     * @param array $itemA
     * @param array $itemB
     * @return int
     */
    protected function _sort($itemA, $itemB)
    {
        return ($itemA['sortOrder'] ?? PHP_INT_MIN) - ($itemB['sortOrder'] ?? PHP_INT_MIN);
    }

}
