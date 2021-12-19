<?php

namespace Base\Traits;

use Symfony\Component\PropertyAccess\PropertyAccess;

trait ProxyTrait
{
    private $_proxy = null;

    public function hasProxy() { return $this->_proxy !== null; }
    public function getProxy() { return $this->_proxy; }
    public function setProxy(object $proxy) { $this->_proxy = $proxy; }

    public function __call(string $methodOrProperty, array $arguments)
    {
        if(!$this->hasProxy())
            throw new \Exception("Proxy not available.. did you forgot to call self::setProxy(Object) ?");

        if(method_exists(get_class($this->_proxy), $methodOrProperty))
            return $this->_proxy->{$methodOrProperty}(...$arguments);
        if(method_exists(get_class($this->_proxy), "get".mb_ucfirst($methodOrProperty)))
            return $this->_proxy->{"get".mb_ucfirst($methodOrProperty)}(...$arguments);

        $accessor = PropertyAccess::createPropertyAccessor();
        if ($accessor->isReadable($this->_proxy, $methodOrProperty))
            return $accessor->getValue($this->_proxy, $methodOrProperty);
        
        throw new \BadMethodCallException("Method (or property accessor) \"$methodOrProperty\" not found in ". get_class($this->_proxy));
    }
}