<?php

namespace Base\Database\Mapping;

/**
 * What omnibase's attributes add to an entity class's mapping, as plain data (plan "omnibase agnostic", step E3):
 * #[Alias]'s names (`aliasNames`, alias => property), #[Hierarchify]'s hierarchy (`entityHierarchy`,
 * `entityHierarchySeparator`)... Completors::of() fills it from the class's attributes (their complete() hook),
 * on no ORM. omnibridge/doctrine's ClassMetadataCompletor is this class under its former name.
 */
class Completor
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

    /** @return array<string, mixed> what the attributes wrote, by name */
    public function payload(): array
    {
        return $this->payload;
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
    public function __set(string $name, mixed $value)
    {
        $this->payload["class"] ??= $this->className; // Useful when looking inside cache directory for debugging.....
        $this->payload[$name] = $value;
        return $this;
    }
}
