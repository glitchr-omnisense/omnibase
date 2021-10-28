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

    public function int2size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = (int) floor(log($bytes) / log(1024));

        return ((int) ($bytes / (1024 ** $factor))).@$units[$factor];
    }

    public function size2int(string $size): int
    {
        $size = trim(str_replace(" ", "", $size), "B");
        if(!preg_match('/^([0-9]*)(.*)$/', $size, $matches))
            throw new \Exception("Failed to parse file size provided \"".$size."\"");

        $size = $matches[1];
        $units = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
        $factor = array_search($matches[2] ?? '', $units);

        return (int) ($size * (1024 ** $factor));
    }
}