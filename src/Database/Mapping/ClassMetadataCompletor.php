<?php

namespace Base\Database\Mapping;

class ClassMetadataCompletor
{
    protected array $payload = [];
    protected string $className;
    public function __constructor(string $className, array $payload = [])
    {
        $this->className = $className;
        $this->payload = $payload;
    }

    public function getName() { return $this->className; }
    public function exists($name) { return array_key_exists($name, $this->payload); }

    public function &__get($name) { return $this->payload[$name]; }
    public function __set(string $name, mixed $value)
    {
        $this->payload[$name] = $value;
        return $this;
    }

}