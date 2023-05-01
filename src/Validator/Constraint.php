<?php

namespace Base\Validator;

/**
 * @Annotation
 */
class Constraint extends \Symfony\Component\Validator\Constraint
{
    public $message = "";

    /**
     * @param array $options
     * @param array|null $groups
     * @param $payload
     */
    public function __construct(array $options = [], array $groups = null, $payload = null)
    {
        if (empty($this->message)) {
            $classname = explode("\\", get_called_class());
            $classname = array_pop($classname);
            $this->message = "@validators." . camel2snake($classname);
        }

        parent::__construct($options, $groups, $payload);
    }
}
