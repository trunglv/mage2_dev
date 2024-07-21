<?php
namespace Betagento\Developer\Webapi;

use Magento\Framework\Reflection\MethodsMap;
use Magento\Webapi\Model\Config\Converter as ConfigConverter;
use Magento\Framework\Reflection\FieldNamer;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;

class Reflection
{
    /**
     * @var \Magento\Webapi\Model\ConfigInterface
     */
    protected $webapiConfig;
    
    /**
     * @var MethodsMap
     */
    private $methodsMap;
    
    /**
     * @var ObjectManagerConfig
     */
    protected $objectManagerConfig;

    /**
     *
     * @var \Magento\Framework\Reflection\FieldNamer
     */
    protected $fieldNamer;

    /**
     * Cache all Reflected Interfaces
     *
     * @var array<string>
     */
    protected $_alreadyReflectedInterfaces = [];

    public function __construct(
        \Magento\Webapi\Model\ConfigInterface $webapiConfig,
        MethodsMap $methodsMap,
        FieldNamer $fieldNamer,
        ObjectManagerConfig $objectManagerConfig
    )
    {
        $this->webapiConfig = $webapiConfig;
        $this->methodsMap = $methodsMap;
        $this->fieldNamer = $fieldNamer;
        $this->objectManagerConfig = $objectManagerConfig;
    }

    /**
     * Show reflected meta of a service
     *
     * @param string $serviceUrl
     * @param string $httpMethod
     * @return mixed
     */
    public function show($serviceUrl, $httpMethod){

        $meta = [];
        $servicesRoutes = $this->webapiConfig->getServices()[ConfigConverter::KEY_ROUTES];
        
        $serviceRoute = isset($servicesRoutes[$serviceUrl]) ? $servicesRoutes[$serviceUrl] : false;
        
        if (!$serviceRoute) {
            return false;
        }
            
        $serviceInfo  = isset($serviceRoute[$httpMethod]) ? $serviceRoute[$httpMethod] : false;
        
        if (!$serviceInfo) {
            return false;
        }
            
        $serviceClassName = $serviceInfo['service']['class'];
        $serviceMethodName = $serviceInfo['service']['method'];
        $meta = [
            'route' => [
                'service_class' => $serviceClassName,
                'preference_class' => $this->objectManagerConfig->getPreference($serviceClassName),
                'service_method' => $serviceMethodName
            ]
        ];
        $methodParams = $this->methodsMap->getMethodParams($serviceClassName, $serviceMethodName);
        
        $detialMethodParams = array_map (
            function ($item) {
                $item['type'] = $this->reflectDataType($item['type']);
                return $item;
            }, $methodParams
        );

        $meta['input'] = $detialMethodParams;
        $outputType = $this->methodsMap->getMethodReturnType($serviceClassName, $serviceMethodName);

        $meta['output'] = [
            'type' => $this->reflectDataType($outputType),
        ];
        return $meta;
    }
    
    /**
     * Reflection Data Type
     * @param string $objectType
     * @return  string
     */
    protected function reflectDataType($objectType){
        $objectType = str_replace("[]", "", $objectType);
        if(!class_exists($objectType) && !interface_exists($objectType)){
            return $objectType;    
        }
        $detailType = '';
        try{
            $objectTypeRelectionString = print_r($this->getMetaFromInterface($objectType),true);
            $objectTypeRelectionString = str_replace("Array","", $objectTypeRelectionString);
            $objectTypeRelectionString = str_replace("(","[", $objectTypeRelectionString);
            $objectTypeRelectionString = str_replace(")","]", $objectTypeRelectionString);
            $detailType = $objectType . PHP_EOL . "---" . $objectTypeRelectionString;
            
        }catch(\Exception $ex) {

            $detailType = $ex->getMessage();

        }
        return $detailType;
    }

    /**
     * @param string $interfaceName
     * @return array<mixed>
     */
    protected function getMetaFromInterface($interfaceName){
        
        $this->_alreadyReflectedInterfaces[] = $interfaceName;
        $interfaceName = str_replace("[]", "", $interfaceName);
        $interfaceName = $this->objectManagerConfig->getInstanceType($interfaceName);
        $methods =  $this->methodsMap->getMethodsMap($interfaceName);
        $fields = [];

        foreach($methods as $method => $info){
            $fieldName  = $this->fieldNamer->getFieldNameForMethodName($method);
            if($fieldName){
                $fields[$fieldName] = "{$fieldName}: {$info['type']}";
                $objectType = str_replace("[]", "", $info['type']);
                if(class_exists($objectType) || interface_exists($objectType)){

                    if (!in_array($objectType, $this->_alreadyReflectedInterfaces)) {
                        
                        $fields["Reflection for type ". $objectType] = $this->getMetaFromInterface($objectType);
                        continue;
                    }
                    $fields["type ". $objectType] = ["Already reflected"];
                }
            }
        }
        return $fields;
    }
}
