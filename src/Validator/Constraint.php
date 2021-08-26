<?php

namespace Base\Validator;

/**
 * @Annotation
 */
class Constraint extends \Symfony\Component\Validator\Constraint
{
    public $message = "";

    public function __construct(
        array $options = [],
        array $groups = null,
        $payload = null
    ) {

        if(empty($this->message)) {

            $classname = explode("\\", get_called_class());
            $classname = array_pop($classname);
            $this->message = self::camelToSnakeCase($classname);
        }

        parent::__construct($options, $groups, $payload);
    }

    public static function camelToSnakeCase($input)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
    public static function snakeToCamelCase($input)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }
}