<?php

namespace Base\Traits;

use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 *
 */
trait ProxyTrait
{
    private ?object $_proxy = null;

    public function hasProxy(): bool
    {
        return $this->_proxy !== null;
    }

    public function getProxy(): ?object
    {
        return $this->_proxy;
    }

    public function setProxy(object $proxy)
    {
        $this->_proxy = $proxy;
    }

    /**
     * @param string $methodOrProperty
     * @param array $arguments
     * @return mixed|null
     * @throws Exception
     */
    public function __call(string $methodOrProperty, array $arguments)
    {
        if (!$this->hasProxy()) {
            throw new Exception("Proxy not available.. did you forgot to call self::setProxy(Object) ?");
        }

        // Getter from proxy
        if (method_exists(get_class($this->_proxy), $methodOrProperty)) {
            return $this->_proxy->{$methodOrProperty}(...$arguments);
        }
        if (method_exists(get_class($this->_proxy), "get" . mb_ucfirst($methodOrProperty))) {
            return $this->_proxy->{"get" . mb_ucfirst($methodOrProperty)}(...$arguments);
        }

        // Proxy variable
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        if ($propertyAccessor->isReadable($this->_proxy, $methodOrProperty)) {
            return $propertyAccessor->getValue($this->_proxy, $methodOrProperty);
        }

        // Fallback
        if (method_exists(get_class($this), "getGlobals")) {
            $global = $this->getGlobals()[$methodOrProperty] ?? null;
            if ($global !== null) {
                return $global;
            }
        }

        return null;
        // throw new \BadMethodCallException("Variable \"".$methodOrProperty."\" does not exist.");
    }
}
