<?php

namespace Base\Database\Types;

use Base\Database\NamingStrategy;
use Base\Model\IconInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use UnexpectedValueException;

abstract class EnumType extends Type implements IconInterface
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
        return camel_to_snake(end($array));
    }

    public function getName() : string { return self::getStaticName(); }
    public static function getPermittedValues() { 

        $refl = new \ReflectionClass(get_called_class());
        $permittedValues = array_values(array_diff($refl->getConstants(),$refl->getParentClass()->getConstants()));
        
        if(!$permittedValues)
            throw new \Exception("Enum type \"".get_called_class()."\" is empty");

        if( ($missingKeys = array_keys_delete(get_called_class()::getIcons(), $permittedValues)) )
            throw new UnexpectedValueException("The following keys \"".implode(",", $missingKeys)."\" are missing in the list of the available icons on class \"".get_called_class()."\".");

        return $permittedValues;
    }
    
    public function requiresSQLCommentHint(AbstractPlatform $platform) : bool { return true; }
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        $permittedValues = array_map(fn($val) => "'".$val."'", $this->getPermittedValues());

        return "ENUM(".implode(", ", $permittedValues).")";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform) : mixed { return $value; }
    public function convertToDatabaseValue($value, AbstractPlatform $platform) : mixed 
    {
        if ($value !== null && !in_array($value, $this->getPermittedValues()))
            throw new \InvalidArgumentException("Invalid '".$this->name."' value.");

        return $value;
    }

}