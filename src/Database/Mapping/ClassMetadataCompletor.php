<?php

namespace Base\Database\Mapping;

class ClassMetadataCompletor
{
    protected ?string $className = null;
    protected array $payload = [];

    public function __construct(string $className, array $payload = [])
    {
        $this->className = $className;
        $this->payload = $payload;
    }

    public function getName(): ?string { return $this->className; }

    public function exists($name) { return array_key_exists($name, $this->payload); }
    public function &__get($name) { return $this->payload[$name]; }
    public function __set(string $name, mixed $value)
    {
        $this->payload["class"] ??= $this->className; // Useful when looking inside cache directory for debugging.....
        $this->payload[$name] = $value;
        return $this;
    }
}