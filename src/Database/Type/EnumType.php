<?php

namespace Base\Database\Type;

use ArrayAccess;
use Base\Model\IconizeInterface;
use Base\Model\SelectInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use UnexpectedValueException;

abstract class EnumType extends Type implements SelectInterface
{
    protected static $icons = [];
    public static function getIcons(): array 
    {
        $class = static::class;
        if(array_key_exists($class, self::$icons))
            return self::$icons[$class];

        $icons = static::class::__iconizeStatic();
        while($class) {

            if(class_implements_interface($class, IconizeInterface::class)) {

                if( ($missingKeys = array_keys(array_keys_remove($class::__iconizeStatic(), ...$class::getPermittedValues(false)))) )
                    throw new UnexpectedValueException("The following keys \"".implode(",", $missingKeys)."\" are missing in the list of the available icons on class \"".get_called_class()."\".");

                $icons = array_union($icons, $class::__iconizeStatic());
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

    public static function hasKey  (string $key,   bool $inheritance = true) { return array_key_exists($key, self::getPermittedValues($inheritance, true)); }
    public static function hasValue(string $value, bool $inheritance = true) { return array_search($value, self::getPermittedValues($inheritance, true)) !== false; }

    public static function getValue(string $key,   bool $inheritance = true) { return self::getPermittedValues($inheritance, true)[$key] ?? null; }
    public static function getPermittedValues(bool $inheritance = true, bool $preserve_keys = false): array { 

        $refl = new \ReflectionClass(get_called_class());
        if($inheritance) $values = $refl->getConstants();
        else $values = array_diff($refl->getConstants(),$refl->getParentClass()->getConstants());

        if(!$preserve_keys) $values = array_values($values);

        if(!in_array($refl->getName(), [EnumType::class, SetType::class]) && $refl->getName() != Type::class && !$values)
            throw new \Exception("\"".get_called_class()."\" is empty");

        sort($values);

        return $values;
    }

    public static function getPermittedValuesByClass(bool $preserve_keys = false): array
    {
        $refl = new \ReflectionClass(get_called_class());
        if(in_array($refl->getName(), [EnumType::class, SetType::class])) return [];

        $values = [$refl->getName() => $refl->getName()::getPermittedValues(false, $preserve_keys)];
        while(($refl = $refl->getParentClass()) && !in_array($refl->getName(), [EnumType::class, SetType::class]) && $refl->getName() != Type::class)
            $values[$refl->getName()] = $refl->getName()::getPermittedValues(false, $preserve_keys);

        return $values;
    }

    public static function getPermittedValuesByGroup(bool $inheritance = true, bool $preserve_keys = false): array
    {
        $values = self::getPermittedValues($inheritance, $preserve_keys);

        $valuesByGroup = [];
        foreach(array_map(fn($a) => explode("_", $a), $values) as $i => $_) {

            $value = $values[$i];
            $group = &$valuesByGroup;

            $kLast = count($_)-1;
            foreach($_ as $k => $path) {

                if($k == $kLast) $group[$path] = $value;
                else {

                    if(array_key_exists($path, $group))
                        $group[$path] = is_array($group[$path]) ? $group[$path] : ["_self" => $group[$path]];

                    $group[$path] = $group[$path] ?? [];
                    $group = &$group[$path];
                }
            }
        }

        return $valuesByGroup;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform) : bool { return true; }
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        $values = array_map(fn($val) => "'".$val."'", $this->getPermittedValues());
        return "ENUM(".implode(", ", $values).")";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform) : mixed { return $value; }
    public function convertToDatabaseValue($value, AbstractPlatform $platform) : mixed 
    {
        if ($value !== null && !in_array($value, $this->getPermittedValues()))
            throw new \InvalidArgumentException("Invalid '".$this->name."' value.");

        return $value;
    }

}
