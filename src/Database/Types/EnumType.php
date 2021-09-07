<?php

namespace Base\Database\Types;

use Base\Database\NamingStrategy;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

abstract class EnumType extends Type
{
    public static function getStaticName() { 
        $array = explode('\\', get_called_class());
        return NamingStrategy::camelToSnakeCase(end($array));
    }

    public function getName() { return self::getStaticName(); }
    public function getValues() { 

        $refl = new \ReflectionClass(get_called_class());
        $values = array_values(array_diff($refl->getConstants(),$refl->getParentClass()->getConstants()));
        
        if(!$values)
            throw new \Exception("Enum type \"".get_called_class()."\" is empty");

        return $values;
    }
    
    public function requiresSQLCommentHint(AbstractPlatform $platform) { return true; }
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $values = array_map(fn($val) => "'".$val."'", $this->getValues());

        return "ENUM(".implode(", ", $values).")";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform) { return $value; }
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, $this->getValues())) {
            throw new \InvalidArgumentException("Invalid '".$this->name."' value.");
        }
        return $value;
    }

}