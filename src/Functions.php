<?php 

namespace {

    function interpret_link($input)
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

    function camel_to_snake($input) { return strtolower(str_replace("._", ".", preg_replace('/(?<!^)[A-Z]/', '_$0', $input))); }
    function snake_to_came($input)  { return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input)))); }
    function class_synopsis($object)
    {
        if (!$object) return dump("Object passed is null");
        $objectID = (is_object($object)) ? "Object: 0x" . spl_object_hash($object) . "\n" : "";

        if (!is_object($object) && !is_string($object)) return dump($object);

        $className    = (is_string($object) ? $object : get_class($object));
        $classParent  = get_parent_class($className);
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

    function shorten_str(?string $str, int $length = 100, string $separator = " [..] "): ?string
    {
        $nChr = strlen($str);

        if($nChr > $length + strlen($separator))
            return substr($str, 0, $length/2) . $separator . substr($str, $nChr-$length/2, $length/2+1);

        return $str;
    }

    function random_str(int $length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function class_implements_interface($objectOrClass, $interface)
    {
        if(!is_string($objectOrClass) && !is_object($objectOrClass)) return false;

        $classImplements = class_implements($objectOrClass); 
        return array_key_exists($interface, $classImplements);
    }

    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    function array_is_nested($a)
    {
        $rv = array_filter($a, 'is_array');
        if (count($rv) > 0) return true;
        return false;
    }

    function array_is_associative(array $arr)
    {
        if(!$arr) return false;

        $keys = array_keys($arr);
        foreach($keys as $key)
            if(gettype($key) != "integer") return true;

        return $keys !== range(0, count($arr) - 1);
    }

    function array_replace_keys($array, array|string|int $old, array|string|int $new) {

        if(gettype($old) == "array" && gettype($new) == "array") {

            foreach($old as $i => $_)
                $array = array_replace_keys($array, $old[$i], $new[$i]);

            return $array;
        
        } else if(gettype($old) == "array" || gettype($new) == "array") {

            if(gettype($new) != gettype($old))
                throw new \Exception(__FUNCTION__."() : Argument #2 (\$new) must be of same type as argument #1 (\$old)");
        }

        $keys = array_keys($array);
        $idx  = array_search($old, $keys);
        
        array_splice($keys, $idx, 1, $new);
        return array_combine($keys, array_values($array));
    }

    function array_map_recursive($callback, $array) {

        $func = function ($item) use (&$func, &$callback) {
            return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
        };

        return array_map($func, $array);
    }

    function array_flatten($array = null)
    {
        $result = array();

        if (!\is_array($array)) {
            $array = func_get_args();
        }

        foreach ($array as $key => $value) {

            if (\is_array($value)) {
                $result = array_merge($result, array_flatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }

        return $result;
    }

    function array_key_missing($keys, array $array) 
    {
        if(!is_array($keys)) $keys = [$keys => null];

        $keys = array_keys($keys);
        if(array_is_associative($keys))
            throw new InvalidArgumentException("Provided keys must be either a key or an array (not associative): \"".preg_replace( "/\r|\n/", "", print_r($keys, true))."\"");

        return array_diff($array, $keys);
    }

    function array_union(...$arrays) 
    {
        $union = [];
        foreach($arrays as $array)
            $union += $array;

        return $union;
    }
}
