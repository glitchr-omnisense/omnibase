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
        if(!class_exists($className)) return dump("Class \"$className\" not found.");
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

        return  dump(
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

    const     BIT_PREFIX = array("b");
    const    BYTE_PREFIX = array("B", "O", "o");
    const  BINARY_PREFIX = array("ki", "mi", "gi", "ti", "pi", "ei", "zi", "yi");
    const DECIMAL_PREFIX = array("k",  "m",  "g",  "t",  "p",  "e",  "z",  "y");

    function byte2bit(int $num): int { return 8*$num; } // LOL !
    function bit2byte(int $num): int { return $num/8; } // LOL LOL !
    function byte2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string { return dec2str($num, $unitPrefix)."B"; }
    function  bit2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string { return dec2str($num, $unitPrefix)."b"; }
    function  dec2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string
    {
             if ($unitPrefix == DECIMAL_PREFIX) $divider = 1000;
        else if ($unitPrefix == BINARY_PREFIX)  $divider = 1024;
        else throw new \Exception("Unknown prefix found: \"$unitPrefix\"");
        $unitPrefix = [''] + $unitPrefix;

        $factor   = (int) floor(log($num) / log($divider));
        $quotient = (int) ($num / ($divider ** $factor));

        $rest     = $num - $divider*$quotient;
        if($rest > 0) $quotient--;
        if($rest > 0) $factor--;

        return strval($factor > 0 ? $quotient.@ucfirst($unitPrefix[$factor]) : $num);
    }

    function str2dec(string $str): int
    {
        $val = trim($str);
        if(!preg_match('/^([bo]{0,2})([a-z]{0,2})([0-9]*)/i', strrev($val), $matches))
            throw new \Exception("Failed to parse string \"".$str."\"");
        
        $val        = intval($matches[3] == "" ? 1 : strrev($matches[3]));
        $unitPrefix = strtolower(strrev($matches[2]));
        $units      = strrev($matches[1]);

        if(in_array($units,  BIT_PREFIX)) $val *= 1; // LOL !
        if(in_array($units, BYTE_PREFIX)) $val *= 8;
        if ($unitPrefix) {
           
            $binFactor = array_search($unitPrefix, [""]+BINARY_PREFIX);
            $decFactor = array_search($unitPrefix, [""]+DECIMAL_PREFIX);
            if($decFactor xor $binFactor)
                throw new \Exception("Unexpected prefix unit \"$unitPrefix\" for \"".$str."\"");

            $val *= 1000**$decFactor;
            $val *= 1024**$binFactor;
        }
        
        return intval($val);
    }

    function begin(object|array &$array) 
    {
        $first = array_key_first($array);
        return $first !== null ? $array[$first] : null;
    }

    function closest(array $array, $position = -1)
    {
        return $array[$position] ?? ($position < 0 ? ($array[0] ?? false) : end($array));
    }

    function stringeable($value) {
        return (!is_object($value) && !is_array($value)) || method_exists($value, '__toString');
    }

    function class_implements_interface($objectOrClass, $interface)
    {
        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        if(!class_exists($class)) return false;

        $classImplements = class_implements($class); 
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

    function browser_supports_webp() { return strpos( $_SERVER['HTTP_ACCEPT'] ?? [], 'image/webp' ) !== false; }

    function is_associative(array $arr): bool
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

    function array_map_recursive(callable $callback, array $array) :array {

        $func = function ($item) use (&$func, &$callback) {
            return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
        };

        return array_map($func, $array);
    }

    function array_key_transforms(callable $callback, array $array, bool $checkReturnType = true): array {

        $reflection = new ReflectionFunction($callback);
        if($checkReturnType) {

            if (!$reflection->getReturnType() || $reflection->getReturnType()->getName() != 'array') 
                throw new \Exception('Callable function must use "array" return type');
        }

        $tArray = [];
        $counter = 0;
        foreach($array as $key => $entry) {
        
            switch($reflection->getNumberOfParameters()) {
                case 0:
                    throw new InvalidArgumentException('Missing arguments in the callable function (must be between 1 and 4)');

                case 1:
                    $ret = call_user_func($callback, $key);
                    break;

                case 2:
                    $ret = call_user_func($callback, $key, $entry);
                    break;

                case 3:
                    $ret = call_user_func($callback, $key, $entry, $counter);
                    break;

                case 4:
                    $ret = call_user_func($callback, $key, $entry, $counter, $callback);
                    break;

                default:
                    throw new InvalidArgumentException('Too many arguments passed to the callable function (must be between 1 and 4)');
            }

            if($ret == null) {
                $tArray[$key] = $entry[$key];
                continue;
            }

            list($tKey, $tEntry) = [$ret[0] ?? $key, $ret[1] ?? $entry];
            $tArray[$tKey] = $tEntry;

            $counter++;
        }

        return $tArray;
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
        if(is_associative($keys))
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

    function mailparse(string $addresses): array
    {
        $regex = '/(?:\w*:)*\s*(?:"([^"]*)"|([^,;\/""<>]*))?\s*(?:(?:[,;\/]|<|\s+|^)([^<@\s;,]+@[^>@\s,;\/]+)>?)\s*/';
        if (preg_match_all($regex, $addresses, $matches, PREG_SET_ORDER) > 0)
            $matches = array_key_transforms(fn($k, $x): array => [trim($x[3]), trim($x[1] . $x[2])], $matches);

        return $matches;
    }

    function array_unique_map(callable $callback, array $array, int $flags = SORT_STRING): array
    {
        $arrayMask = array_fill_keys(array_keys(array_unique(array_map($callback, $array), $flags)), null);
        return array_intersect_key($array, $arrayMask);
    }
}
