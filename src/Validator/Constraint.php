<?php

namespace Base\Validator;

/**
 * @Annotation
 */
class Constraint extends \Symfony\Component\Validator\Constraint
{
    public $message = "";

    public function __construct(array $options = [], array $groups = null, $payload = null) {

        if(empty($this->message)) {

            $classname = explode("\\", get_called_class());
            $classname = array_pop($classname);
            $this->message = camel_to_snake($classname);
        }

        parent::__construct($options, $groups, $payload);
    }
}