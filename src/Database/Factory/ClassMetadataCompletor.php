<?php

namespace Base\Database\Factory;

use Doctrine\Persistence\Mapping\ClassMetadata;

class ClassMetadataCompletor
{
    private array $extra = [];

    public function __construct(ClassMetadata $classMetadata)
    {
        $this->classMetadata = $classMetadata;
    }

    public function getEntityFqcn() { return $this->classMetadata->getName(); }
    public function metadata() { return $this->classMetadata; }
    public function exists(string $name) { return array_key_exists($name, $this->extra); }

    public function &__get($name) { return $this->extra[$name]; }
    public function set(string $name, mixed $value)
    {
        $this->extra[$name] = $value;
        return $this;
    }

}
