<?php

namespace Base\Database\Mapping;

/**
 *
 */
class ClassMetadataCompletor
{
    protected ?string $className = null;
    protected array $payload = [];

    public function __construct(string $className, array $payload = [])
    {
        $this->className = $className;
        $this->payload = $payload;
    }

    public function getName(): ?string
    {
        return $this->className;
    }

    /**
     * @param $name
     * @return bool
     */
    public function exists($name)
    {
        return array_key_exists($name, $this->payload);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function &__get($name)
    {
        return $this->payload[$name];
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function __set(string $name, mixed $value)
    {
        $this->payload["class"] ??= $this->className; // Useful when looking inside cache directory for debugging.....
        $this->payload[$name] = $value;
        return $this;
    }
}
