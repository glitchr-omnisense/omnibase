<?php

namespace Base\Validator;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Constraint extends \Symfony\Component\Validator\Constraint
{
    public string $message;

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // Default message if none provided
        if ($message === null) {
            $classname = explode("\\", static::class);
            $classname = array_pop($classname);
            $message = "@validators." . camel2snake($classname);
        }

        $this->message = $message;

        parent::__construct(groups: $groups, payload: $payload);
    }
}