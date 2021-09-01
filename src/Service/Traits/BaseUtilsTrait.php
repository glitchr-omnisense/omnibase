<?php

namespace Base\Service\Traits;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Config\Definition\Exception\Exception;

trait BaseUtilsTrait
{
    public static function isAssoc(array $arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function isNested($a)
    {
        $rv = array_filter($a, 'is_array');
        if (count($rv) > 0) return true;
        return false;
    }

    public static function array_flatten($array = null)
    {
        $result = array();

        if (!\is_array($array)) {
            $array = func_get_args();
        }

        foreach ($array as $key => $value) {

            if (\is_array($value)) {
                $result = array_merge($result, self::array_flatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }

        return $result;
    }

    public static function interpretLink($input)
    {
        return preg_replace_callback(
            "@
        (?:http|ftp|https)://
        (?:
            (?P<domain>\S+?) (?:/\S+)|
            (?P<domain_only>\S+)
        )
        @sx",
        function ($a) {
            $link = "<a href='" . $a[0] . "'>";
            $link .= $a["domain"] !== "" ? $a["domain"] : $a["domain_only"];
            $link .= "</a>";
            return $link;
        }, $input);
    }

    public static function isNested2($a)
    {
        foreach ($a as $v) {
            if (is_array($v)) return true;
        }
        return false;
    }

    function isEntity($class)
    {
        if (is_object($class))
            $class = ClassUtils::getClass($class);

        return !$this->getEntityManager()->getMetadataFactory()->isTransient($class);
    }

    public static function camelToSnakeCase($input)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
    public static function snakeToCamelCase($input)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    public static function getRandomStr($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function getSynopsis($object)
    {
        return self::get_class_synopsis($object);
    }

    public static function get_class_synopsis($object)
    {

        if (!$object) return dump("Object passed is null");
        $objectID = (is_object($object)) ? "Object: 0x" . spl_object_hash($object) . "\n" : "";

        if (!is_object($object)) return dump($object);

        $className    = get_class($object);
        $classParent  = get_parent_class($object);
        $classMethods = get_class_methods($className);
        $classVars    = get_class_vars($className);

        $classReflection = new \ReflectionClass($object);

        $methods = "";
        foreach ($classMethods as $methodName) {

            $params = (new \ReflectionMethod($className, $methodName))->getParameters();

            $args = "";
            foreach ($params as $param) {
                $optional = ($param->isOptional()) ? " = optional" : "";
                $args .= (!empty($args)) ? ", " : "";
                $args .= "$" . $param->getName() . $optional;
            }

            $methods .= "\n     public function " . $methodName . "(" . $args . ") { ... }";
        }

        $vars = "";
        foreach ($classVars as $varName => $value) {

            $value = ( is_array($value)) ? print_r($value, true) : (
                     (is_object($value) && !method_exists($value, '__toString')) ? get_class($value)."(not stringeable)" : $value);

            $vars .= (!empty($vars)) ? ",\n" : "";
            $vars .= "     $" . $varName . " = \"" . $value . "\"";
        }

        if (empty($vars)) $vars = "     -- No public variable available";
        if (empty($methods)) $methods = "     -- No public method available";
        $parentName = (!empty($classParent)) ? "            extends " . $classParent : "";

        return dump(
            $classReflection,
            $objectID .
                "class " . $className . $parentName . " {\n\n" .
                $vars . "\n" .
                $methods . "\n}\n\nMore information in the ReflectionMethod below.."
        );
    }
}
