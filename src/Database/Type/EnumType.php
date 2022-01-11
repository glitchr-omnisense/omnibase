<?php

namespace Base\Database\Type;

use Base\Model\IconizeInterface;
use Base\Model\SelectInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use UnexpectedValueException;

abstract class EnumType extends Type implements SelectInterface
{
    public static function getIds(): array { return array_keys(self::getIcons()); }

    protected static $icons = [];
    public static function getIcons(): array 
    {
        $class = static::class;
        if(array_key_exists($class, self::$icons))
            return self::$icons[$class];

        $icons = static::class::__staticIconize();
        while($class) {

            if(class_implements_interface($class, IconizeInterface::class)) {

                if( ($missingKeys = array_keys(array_keys_remove($class::getPermittedValues(false), $class::__staticIconize()))) )
                    throw new UnexpectedValueException("The following keys \"".implode(",", $missingKeys)."\" are missing in the list of the available icons on class \"".get_called_class()."\".");

                $icons = array_union($icons, $class::__staticIconize());
                self::$icons[$class] = $icons;
            }
            
            $class = get_parent_class($class);
        }
        
        self::$icons[static::class] = $icons;
        return $icons;
    }

    public static function getIcon(string $id, int $index = -1): ?string { return array_map( fn($values) => ($index < 0 || !is_array($values)) ? $values : closest($values, $index), self::getIcons() )[$id] ?? null; }
    public static function getText(string $id, ?TranslatorInterface $translator = null): ?string { return $translator ? $translator->enum($id, get_called_class(), Translator::TRANSLATION_SINGULAR) : $id; }
    public static function getHtml(string $id): ?string { return null; }
    public static function getData(string $id): ?array { return null; }

    public function getName() : string { return self::getStaticName(); }
    public static function getStaticName() { 
        $array = explode('\\', get_called_class());
        return camel_to_snake(end($array));
    }

    public static function getPermittedValuesByClass()
    {
        $refl = new \ReflectionClass(get_called_class());
        if(in_array($refl->getName(), [EnumType::class, SetType::class])) return [];

        $permittedValues = [$refl->getName() => $refl->getName()::getPermittedValues(false)];
        while(($refl = $refl->getParentClass()) && !in_array($refl->getName(), [EnumType::class, SetType::class]) && $refl->getName() != Type::class)
            $permittedValues[$refl->getName()] = $refl->getName()::getPermittedValues(false);

        return $permittedValues;
    }
    
    public static function getPermittedValues(bool $inheritance = true) { 

        $refl = new \ReflectionClass(get_called_class());
        if($inheritance) $permittedValues = array_values($refl->getConstants());
        else $permittedValues = array_values(array_diff($refl->getConstants(),$refl->getParentClass()->getConstants()));

        if(!in_array($refl->getName(), [EnumType::class, SetType::class]) && $refl->getName() != Type::class && !$permittedValues)
            throw new \Exception("\"".get_called_class()."\" is empty");

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