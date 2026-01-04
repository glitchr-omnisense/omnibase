<?php

namespace Base\Database\Entity\Extension;

use Symfony\Component\PropertyAccess\Exception\AccessException;

trait TranslatableAliasTrait
{
    use TranslatableTrait {
        TranslatableTrait::__call  as __translatableCall;
        TranslatableTrait::__isset as __translatableisset;
        TranslatableTrait::__get   as __translatableGet;
        TranslatableTrait::__set   as __translatableSet;
    }
    use AliasTrait {
        AliasTrait::__call  as __aliasCall;
        AliasTrait::__isset  as __aliasIsset;
        AliasTrait::__get  as __aliasGet;
        AliasTrait::__set  as __aliasSet;
    }

    public function __call(string $method, array $arguments): mixed
    {
        try {
            return $this->__aliasCall($method, $arguments);
        } catch (AccessException $e) {
            return $this->__translatableCall($method, $arguments);
        }
    }

    public function __isset(string $property): bool {
        if($this->__aliasIsset($property)) return true;
        return $this->__translatableIsset($property);
    }

    public function __get(string $property): mixed {
        try { return $this->__aliasGet($property); }
        catch (AccessException $e) { return $this->__translatableGet($property); }
    }

    public function __set(string $property, mixed $value): void {
        try { $this->__aliasSet($property, $value); } 
        catch (AccessException $e) { $this->__translatableSet($property, $value); }
    }
}