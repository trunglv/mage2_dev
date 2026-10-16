<?php
declare(strict_types=1);
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */ 
namespace Betagento\Developer\Di;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\DefinitionInterface;
use Magento\Framework\ObjectManager\RelationsInterface;
class DiConfig
{

    public function __construct(
        protected ConfigInterface $omConfig,
        protected DefinitionInterface $definition,
        protected RelationsInterface $relations
    )
    {}

    /**
     * Get injected arguments/params/object are injected into a construct function of a object
     *
     * @param string $type
     * @return array<int|string, array<string, mixed>>
     */
    public function getInjectedParams($type)
    {
        $realTypeClass = $this->omConfig->getInstanceType($type);
        $parametters = $this->definition->getParameters($realTypeClass);
        $checkedParams = [];
        foreach ($parametters as $parameter) {
            list($paramName, $paramType, $paramRequired, $paramDefault, $isVariadic) = $parameter;
            if (!is_string($paramType)) {
                continue; // scalar/untyped parameter: nothing to resolve
            }
            if (interface_exists($paramType)) {
                $checkedParams[$paramName] = [
                    'error' => 0,
                    'name' => $paramName,
                    'type' => $paramType,
                    'parent' => [],
                    'is_interface' => true,
                    'preference' => [$paramType => $this->omConfig->getPreference($paramType)],
                    
                ];
                continue;
            }
            if (class_exists($paramType)) {
                
                $parentInterfaces = [];
                $preferences = [];
                $error = 0;
                $this->getDeepInterfaces($paramType, $parentInterfaces);
                foreach($parentInterfaces as $pInterface) {
                    if ($preference = $this->omConfig->getPreference($pInterface)) {
                        $preferences[$pInterface] = $preference;
                        if ($preference == $paramType) {
                            $error = 1;
                        }
                    }
                }
                $checkedParams[$paramName] = [
                    'error' => $error,
                    'name' => $paramName,
                    'type' => $paramType,
                    'parent' => $parentInterfaces,
                    'is_interface' => false,
                    'preference' => $preferences
                ];
                continue;
            }
        }
        return $checkedParams;
    }

    /**
     * Get all interfaces are implemented by a class
     *
     * @param string $type
     * @param array<string> $parentInterfaces
     * @return void
     */
    protected function getDeepInterfaces($type, &$parentInterfaces = []) : void {
        // getParents() puts null first for a class with no parent class; drop it (null offsets/args fail on PHP 8.5)
        $parentClasses = array_filter($this->relations->getParents($type));
        if(!count($parentClasses)) {
            return ;
        }
        foreach ($parentClasses as $daddyOrMom) {
            
            if (interface_exists($daddyOrMom)) {
                $parentInterfaces [] = $daddyOrMom;
                continue;
            }

            if (class_exists($daddyOrMom)) {
                $this->getDeepInterfaces($daddyOrMom, $parentInterfaces);
            }
        }
    }
}

