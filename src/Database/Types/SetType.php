<?php

namespace Base\Database\Types;

use Base\Database\NamingStrategy;
use Base\Model\IconInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use UnexpectedValueException;

abstract class SetType extends Type implements IconInterface
{
    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        return array_map(function($values) use ($pos) {
            if(is_array($values)) return ($pos < 0 ? $values : closest($values, $pos));
            else return $values;
        }, array_union(...$arrays));
    }

    public static function getStaticName() { 
        $array = explode('\\', get_called_class());
        return NamingStrategy::camelToSnakeCase(end($array));
    }

    public function getName() : string { return self::getStaticName(); }
    public static function getPermittedValues() { 

        $refl = new \ReflectionClass(get_called_class());
        $permittedValues = array_values($refl->getConstants());

        if(!$permittedValues)
            throw new \Exception("Set type \"".get_called_class()."\" is empty");

        if( ($missingKeys = array_key_missing(get_called_class()::getIcons(), $permittedValues)) )
            throw new UnexpectedValueException("The following keys \"".implode(",", $missingKeys)."\" are missing in the list of the available icons on class \"".get_called_class()."\".");

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
