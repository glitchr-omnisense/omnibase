<?php

namespace Base\Database\Types;

use Base\Database\NamingStrategy;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

abstract class SetType extends Type
{
    public static function getStaticName() { 
        $array = explode('\\', get_called_class());
        return NamingStrategy::camelToSnakeCase(end($array));
    }

    public function getName() : string { return self::getStaticName(); }
    public function getPermittedValues() { 

        $refl = new \ReflectionClass(get_called_class());
        $permittedValues = array_values($refl->getConstants());

        if(!$permittedValues)
            throw new \Exception("Set type \"".get_called_class()."\" is empty");

        return $permittedValues;
    }
    
    public function requiresSQLCommentHint(AbstractPlatform $platform) : bool { return true; }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        $permittedValues = array_map(fn($val) => "'".$val."'", $this->getPermittedValues());
        return "SET(".implode(", ", $permittedValues).")";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform) : mixed
    { 
        $values = explode(",", $value);
        return array_filter($values, fn($v) => in_array($v, $this->getPermittedValues()));
    }
    
    public function convertToDatabaseValue($values, AbstractPlatform $platform) : mixed
    {
        $values = array_filter($values, fn($v) => in_array($v, $this->getPermittedValues()));
        return implode(",", $values);
    }

}
