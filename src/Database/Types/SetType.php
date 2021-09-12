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

    public function getName() { return self::getStaticName(); }
    public function getPermittedValues() { 

        $refl = new \ReflectionClass(get_called_class());
        $permittedValues = array_values(array_diff($refl->getConstants(),$refl->getParentClass()->getConstants()));
        
        if(!$permittedValues)
            throw new \Exception("Enum type \"".get_called_class()."\" is empty");

        return $permittedValues;
    }
    
    public function requiresSQLCommentHint(AbstractPlatform $platform) { return true; }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $permittedValues = array_map(fn($val) => "'".$val."'", $this->getPermittedValues());

        return "SET(".implode(", ", $permittedValues).")";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform) { return explode(",", $value); }
    public function convertToDatabaseValue($values, AbstractPlatform $platform)
    {
        foreach($values as $value) {
        
            if (!in_array($value, $this->getPermittedValues()))
                throw new \InvalidArgumentException("Invalid '".$this->name."' value.");
        }

        return implode(",", $values);
    }

}