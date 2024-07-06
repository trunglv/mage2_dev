<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Di\Interception;

use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data\Scoped;
use Magento\Framework\Config\ReaderInterface;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\Interception\DefinitionInterface;
use Magento\Framework\Interception\PluginListGenerator;
use Magento\Framework\Interception\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\RelationsInterface;
use Magento\Framework\ObjectManager\DefinitionInterface as ClassDefinitions;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Framework\App\Area;


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
     * @var PluginListGenerator
     */
    private $pluginListGenerator;

    /**
     * Constructor
     *
     * @param ReaderInterface $reader
     * @param ScopeInterface $configScope
     * @param CacheInterface $cache
     * @param ConfigInterface $omConfig
     * @param DefinitionInterface $definitions
     * @param ObjectManagerInterface $objectManager
     * @param ClassDefinitions $classDefinitions
     * @param array $scopePriorityScheme
     * @param string|null $cacheId
     * @param SerializerInterface|null $serializer
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
        $fullWillCheckClasses = [$type];
        if (interface_exists($type)) {
            $concreteType = $this->_omConfig->getPreference($type);
            $concreteType = str_replace("\Interceptor", "", $concreteType);
            $fullWillCheckClasses[] = $concreteType;
        }
        if (class_exists($type) && !interface_exists($type)) {
            $parentClasses = $this->_relations->getParents($type);
            if ($parentClasses) {
                $fullWillCheckClasses = array_merge($fullWillCheckClasses, $parentClasses);
            }
        }
        $plugins = [];
        foreach ($fullWillCheckClasses as $specificType) {
            $pluginDefinition = [];
            if (!isset($this->_inherited[$specificType]) && !array_key_exists($type, $this->_inherited)) {
                $pluginDefinition = $this->_data[$specificType];
            }
            $me = $this;
            if($pluginDefinition){
                $plugins[$specificType] = [];
                array_walk($pluginDefinition, function($item, $key) use ($me, &$plugins, $specificType) {            
                    $methods = $me->getPluginMethods($item['instance']);
                    foreach($methods as $methodName => $methodType){
                        $plugins[$specificType][] = [
                            'code' => $key,
                            'original_method' => $methodName,
                            'plugin_method_type' => $me->getPluginMethodType($methodType),
                            'instance' => $item['instance'],
                            'method_exists' => $me->isInjectedMethodExists($specificType, $methodName) ? 'method is ok' : 'method does not exist'
                        ];
                    }
                },[]);
            }
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
       
        //var_dump($pluginMethod);exit;
        //$methods = get_class_methods($pluginMethod);
        $class = new \ReflectionClass($instanceType);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        return count(array_filter($methods, function($method) use ($pluginMethod) {
            return $method->getName() == $pluginMethod;
        })) > 0  ? true : false ;
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
     * Load configuration for current scope
     *
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
     * @return array
     */
    protected function getClassDefinitions()
    {
        return $this->_classDefinitions->getClasses();
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
}
