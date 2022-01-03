<?php

namespace Base\Database\Type;

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
        return camel_to_snake(end($array));
    }

    public function getName() : string { return self::getStaticName(); }
    public static function getPermittedValuesByClass()
    {
        $refl = new \ReflectionClass(get_called_class());
        if($refl->getName() == self::class) return [];

        $permittedValues = [$refl->getName() => $refl->getName()::getPermittedValues(false)];
        while(($refl = $refl->getParentClass()) && $refl->getName() != self::class && $refl->getName() != Type::class)
            $permittedValues[$refl->getName()] = $refl->getName()::getPermittedValues(false);

        return $permittedValues;
    }
    
    public static function getPermittedValues(bool $inheritance = true) { 

        $refl = new \ReflectionClass(get_called_class());
        if($inheritance) $permittedValues = array_values($refl->getConstants());
        else $permittedValues = array_values(array_diff($refl->getConstants(),$refl->getParentClass()->getConstants()));

        if($refl->getName() != self::class && $refl->getName() != Type::class && !$permittedValues)
            throw new \Exception("Set type \"".get_called_class()."\" is empty");

        if( ($missingKeys = array_keys_delete(get_called_class()::getIcons(), $permittedValues)) )
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
