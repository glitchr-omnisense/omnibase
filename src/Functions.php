<?php 

namespace {

    use Base\BaseBundle;

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

    function camel_to_snake(string $input, string $separator = "_") { return mb_strtolower(str_replace('.'.$separator, '.', preg_replace('/(?<!^)[A-Z]/', $separator.'$0', $input))); }
    function snake_to_camel(string $input, string $separator = "_") { return lcfirst(str_replace(' ', '', mb_ucwords(str_replace($separator, ' ', $input)))); }

    function is_uuid(string $uuid) { return is_string($uuid) && !(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1); }
    function synopsis($object) { return class_synopsis($object); }
    function class_synopsis($object)
    {
        if (!$object) return dump("Object passed is null");
        $objectID = (is_object($object)) ? "Object: 0x" . spl_object_hash($object) . "\n" : "";

        if (!is_object($object) && !is_string($object)) return dump($object);

        $className    = (is_string($object) ? $object : get_class($object));
        if(!class_exists($className)) 
            return dump("Class \"$className\" not found.");
        
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
                        (is_object($value) && !method_exists($value, '__toString')) ? get_class($value)."(not is_stringeable)" : $value);

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

    function str_shorten(?string $str, int $length = 100, string $separator = " [..] "): ?string
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
    const  BINARY_PREFIX = array("", "ki", "mi", "gi", "ti", "pi", "ei", "zi", "yi");
    const DECIMAL_PREFIX = array("", "k",  "m",  "g",  "t",  "p",  "e",  "z",  "y");

    function byte2bit(int $num): int { return 8*$num; } // LMFAO !
    function bit2byte(int $num): int { return $num/8; } // LMFAO !
    function byte2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string { return dec2str($num/8, $unitPrefix).BYTE_PREFIX[0]; }
    function  bit2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string { return dec2str($num, $unitPrefix).BIT_PREFIX[0]; }
    function  dec2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string
    {
             if ($unitPrefix == DECIMAL_PREFIX) $divider = 1000;
        else if ($unitPrefix == BINARY_PREFIX)  $divider = 1024;
        else throw new \Exception("Unknown prefix found: \"$unitPrefix\"");
        $unitPrefix = [''] + $unitPrefix;

        $factor   = (int) floor(log($num) / log($divider));
        $quotient = (int) ($num / ($divider ** $factor));

        $rest     = $num - $divider*$quotient;
        if($rest < 0) $factor--;

        $quotient = (int) ($num / ($divider ** $factor));
        return strval($factor > 0 ? $quotient.@mb_ucfirst($unitPrefix[$factor]) : $num);
    }

    function str2dec(string $str): int
    {
        $val = trim($str);
        if(!preg_match('/^([bo]{0,2})([a-z]{0,2})([0-9]*)/i', strrev($val), $matches))
            throw new \Exception("Failed to parse string \"".$str."\"");
        
        $val        = intval($matches[3] == "" ? 1 : strrev($matches[3]));
        $unitPrefix = mb_strtolower(strrev($matches[2]));
        $units      = strrev($matches[1]);

        if(in_array($units,  BIT_PREFIX)) $val *= 1; // LMFAO !
        if(in_array($units, BYTE_PREFIX)) $val *= 8;
        if ($unitPrefix) {

            $binFactor = array_search($unitPrefix, BINARY_PREFIX);
            $decFactor = array_search($unitPrefix, DECIMAL_PREFIX);
            if( ! (($decFactor !== false) xor ($binFactor !== false)) )
                throw new \Exception("Unexpected prefix unit \"$unitPrefix\" for \"".$str."\"");

            if($decFactor !== false) $val *= 1000**($decFactor);
            if($binFactor !== false) $val *= 1024**($binFactor);
        }
        
        return intval($val);
    }

    function path_suffix(string|array|null $path, $suffix, $separator = "_"): string
    {
        if($path === null) return $path;
     
        if(!is_array($suffix)) $suffix = [$suffix];
        $suffix = implode($separator, array_filter($suffix));
     
        if(is_array($path))
            return array_map(fn($p) => path_suffix($p, $suffix, $separator), $path);
        
        $path = pathinfo($path);
        $path["dirname"] = $path["dirname"] ?? null;
        if($path["dirname"]) $path["dirname"] .= "/";
        $path["extension"] = $path["extension"] ?? null;
        if($path["extension"]) $path["extension"] = ".".$path["extension"];

        $filename = $path["filename"] ?? null;
        $suffix = ($filename && $suffix) ? $separator.$suffix : $suffix;
        return $path["dirname"].$path["filename"].$suffix.$path["extension"];
    }
    
    function path_prefix(string|array|null $path, $prefix, $separator = "_")
    {
        if($path === null) return $path;
     
        if(!is_array($prefix)) $prefix = [$prefix];
        $prefix = implode($separator, array_filter($prefix));

        if(is_array($path))
            return array_map(fn($p) => path_prefix($p, $prefix, $separator), $path);
        
        $path = pathinfo($path);
        $path["dirname"] = $path["dirname"] ?? null;
        if($path["dirname"]) $path["dirname"] .= "/";
        $path["extension"] = $path["extension"] ?? null;
        if($path["extension"]) $path["extension"] = ".".$path["extension"];

        $filename = $path["filename"] ?? null;
        $prefix = ($filename && $prefix) ? $separator.$prefix : $prefix;

        return $path["dirname"].$prefix.$path["filename"].$path["extension"];
    }
    
    function str_strip(string $str, string $prefix = "", string $suffix = "")
    {
        if(0 === strpos($str, $prefix)) 
            $str = substr($str, strlen($prefix));
        
        if(strlen($str) === strpos($str, $suffix)+strlen($suffix)) 
            $str = substr($str, 0, strlen($str)-strlen($prefix));

        return $str;
    }

    function begin(object|array &$array) 
    {
        $first = array_key_first($array);
        return $first !== null ? $array[$first] : null;
    }

    function head(object|array &$array):mixed { return array_slice($array, 0, 1)[0] ?? null; }
    function tail(object|array &$array):array  { return array_slice($array, 1   ); }

    function closest(array $array, $position = -1) { return $array[$position] ?? ($position < 0 ? ($array[0] ?? false) : end($array)); }
    function is_html(?string $str)  { return $str != strip_tags($str); }
    function is_stringeable($value) { return (!is_object($value) && !is_array($value)) || method_exists($value, '__toString'); }

    function get_alias(array|object|string|null $arrayOrObjectOrClass): string
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => get_alias($a), $arrayOrObjectOrClass);

        $arrayOrObjectOrClass = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        if(!class_exists($arrayOrObjectOrClass)) return false;

        return BaseBundle::getAlias($arrayOrObjectOrClass);
    }

    function alias_exists(array|object|string|null $arrayOrObjectOrClass): bool
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => alias_exists($a), $arrayOrObjectOrClass);

        $class = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        if(!class_exists($class)) return false;

        return BaseBundle::getAlias($class) != $class;
    }

    function class_implements_interface(array|object|string|null $arrayOrObjectOrClass, $interface)
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => class_implements_interface($a, $interface), $arrayOrObjectOrClass);

        $class = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        if(!class_exists($class)) return false;

        $classImplements = class_implements($class); 
        return array_key_exists($interface, $classImplements);
    }

    function class_basename(array|object|string|null $arrayOrObjectOrClass)
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => class_basename($a), $arrayOrObjectOrClass);

        $class = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        return str_replace("/", "\\", basename(str_replace('\\', '/', $class)));
    }

    function class_dirname(array|object|string|null $arrayOrObjectOrClass)
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => class_dirname($a), $arrayOrObjectOrClass);

        $class = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        $dirname = str_replace("/", "\\", dirname(str_replace('\\', '/', $class)));
        return $dirname == "." ? "" : $dirname;
    }

    function is_cli(): bool { return (php_sapi_name() == "cli"); }
    function mb_ucfirst(string $str, ?string $encoding = null): string { return mb_strtoupper(mb_substr($str, 0, 1, $encoding)).mb_substr($str, 1, null, $encoding); }
    function mb_ucwords(string $str, ?string $encoding = null): string { return mb_convert_case($str, MB_CASE_TITLE, $encoding); }
    function html_attributes(array $attributes =[]) { return trim(implode(" ", array_map(fn($k) => trim($k)."=\"".$attributes[$k]."\"", array_keys(array_filter($attributes))))); }

    function browser_name()    : string { return get_browser2()["name"]; }
    function browser_platform(): string { return get_browser2()["platform"]; }
    function browser_version() : string { return get_browser2()["version"]; } 

    function get_browser2(?string $userAgent = null)
    {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'];
        
        $platform = "unknown";
        if (preg_match('/android/i', $userAgent))
            $platform = 'android';
        elseif (preg_match('/linux/i', $userAgent))
            $platform = 'linux';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent))
            $platform = 'apple';
        elseif (preg_match('/windows|win32/i', $userAgent))
            $platform = 'windows';

        $name = "Unknown";
        if(preg_match('/MSIE/i',$userAgent) && !preg_match('/Opera/i',$userAgent))
            $name = "MSIE";
        else if(preg_match('/Firefox/i',$userAgent))
            $name = "Firefox";
        else if(preg_match('/OPR/i',$userAgent))
            $name = "Opera";
        else if(preg_match('/Chrome/i',$userAgent) && !preg_match('/Edge/i',$userAgent))
            $name = "Chrome";
        else if(preg_match('/Safari/i',$userAgent) && !preg_match('/Edge/i',$userAgent))
            $name = "Safari";
        else if(preg_match('/Netscape/i',$userAgent))
            $name = "Netscape";
        else if(preg_match('/Edge/i',$userAgent))
            $name = "Edge";
        else if(preg_match('/Trident/i',$userAgent))
            $name = "MSIE";

        $device = "computer";
        if (preg_match('/tablet|ipad/i', $userAgent))
            $device = 'tablet';
        else if (preg_match('/mobile|iphone|ipod/i', $userAgent))
            $device = 'mobile';


        $known = implode("|", ['Version', $name, 'other']);
        preg_match_all('#(?<browser>' . $known .')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#', $userAgent, $matches);

        $version = "";
        if (count($matches['browser']) == 1) $version = $matches['version'][0];
        else {

            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($userAgent,"Version") < strripos($userAgent,$name)) $version = $matches['version'][0];
            else $version = $matches['version'][1];
        }

        if (!$version) $version = "?";

        return [
            'user_agent' => $userAgent,
            'name'       => $name,
            'version'    => $version,
            'device'     => $device,
            'platform'   => $platform
        ];
    }

    function array_append_recursive()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        if (!is_array($base)) $base = empty($base) ? array() : array($base);

        foreach ($arrays as $append) {
            if (!is_array($append)) $append = array($append);
            foreach ($append as $key => $value) {

                if (!array_key_exists($key, $base) and !is_numeric($key)) {
                    $base[$key] = $append[$key];
                    continue;
                }

                if (is_array($value) or is_array($base[$key])) {
                    $base[$key] = array_append_recursive($base[$key], $append[$key]);
                } else if (is_numeric($key)) {
                    if (!in_array($value, $base)) $base[] = $value;
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }

    function browser_supports_webp(): bool
    {
        if(strpos( $_SERVER['HTTP_ACCEPT'] ?? [], 'image/webp' ) !== false)
            return true;

        if(browser_name() == "Safari" && version_compare("14.0", browser_version()) < 0)
            return true;
        if(browser_name() == "Chrome" && version_compare("23.0", browser_version()) < 0)
            return true;
        if(browser_name() == "Firefox" && version_compare("65.0", browser_version()) < 0)
            return true;
        if(browser_name() == "Edge" && version_compare("1809", browser_version()) < 0)
            return true;
        if(browser_name() == "Opera" && version_compare("12.1", browser_version()) < 0)
            return true;

        return false;
    }

    function pathinfo_relationship(string $path)
    {
        $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);
        if(empty($extension)) return null;
        
        switch($extension) {

            case "ico": return "icon";
            case "css": return "stylesheet";
            case "js": return "javascript";

            default: return "preload";
        }
    }

    function is_associative(array $arr): bool
    {
        if(!$arr) return false;

        $keys = array_keys($arr);
        foreach($keys as $key)
            if(gettype($key) != "integer") return true;

        return $keys !== range(0, count($arr) - 1);
    }

    function array_is_nested($a)
    {
        $rv = array_filter($a, 'is_array');
        if (count($rv) > 0) return true;
        return false;
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

    function count_leaves(array $array)
    {
        $counter = 0;
        array_map_recursive(function($k) use (&$counter) { $counter++; }, $array);
        return $counter;
    }

    define("FORMAT_IDENTITY",     0); // "no changes"
    define("FORMAT_TITLECASE",    1); // Lorem Ipsum Dolor Sit Amet
    define("FORMAT_SENTENCECASE", 2); // Lorem ipsum dolor sit amet
    define("FORMAT_LOWERCASE",    3); // lorem ipsum dolor sit amet
    define("FORMAT_UPPERCASE",    4); // LOREM IPSUM DOLOR SIT AMET
    
    function castcase(string $str, int $format = 0): string
    {
        switch($format) {

            case FORMAT_TITLECASE:
                return mb_ucwords(mb_strtolower($str));
                break;

            case FORMAT_SENTENCECASE:
                return mb_ucfirst(mb_strtolower($str));
                break;

            case FORMAT_LOWERCASE:
                return mb_strtolower($str);
                break;

            case FORMAT_UPPERCASE:
                return mb_strtoupper($str);
                break;

            default:
            case FORMAT_IDENTITY:
                return $str;
        }
    }

    function array_transforms(callable $callback, array $array, bool $checkReturnType = true): array {

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

            if($ret === null) continue;
            list($tKey, $tEntry) = [$ret[0] ?? count($tArray), $ret[1] ?? $entry];
            $tArray[$tKey] = $tEntry;

            $counter++;
        }

        return $tArray;
    }

    function array_filter_recursive(array $array, ?callable $callback = null, int $mode = 0) 
    {
        return array_transforms(function($k,$v,$i,$_) use ($callback, $mode):?array {
            return [$k, is_array($v) ? array_transforms($_, array_filter($v, $callback, $mode)) : $v];
        }, $array);
    }

    function array_slice_recursive(array $array, int $offset, ?int $length, bool $preserve_keys = false): array
    {
        $offsetCounter = 0;
        $lengthCounter = 0;
        return array_transforms(function($k, $v, $i, $callback) use (&$offsetCounter, $offset, &$lengthCounter, $length):?array {

            if(is_array($v)) {
                $v = array_transforms($callback, $v);
                $array = empty($v) ? null : [$k, $v];
                return $array;
            }

            $array = ($offsetCounter < $offset || ($lengthCounter >= $length)) ? null : [$k,$v];
            if($array !== null) $lengthCounter++;

            $offsetCounter++;
            return $array;

        }, $array);

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
 
    function array_class($objectOrClass, array $haystack): string|int|false 
    {
        $className = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        foreach($haystack as $key => $item)
            if($item instanceof $className) return $key;

        return false;
    }

    function array_class_last($objectOrClass, array $haystack): string|int|false 
    {
        $haystack = array_reverse($haystack);
        if(is_associative($haystack)) 
            return array_class($objectOrClass, $haystack);

        $position = array_class($objectOrClass, $haystack);
        if($position === false) return false;

        return count($haystack) - $position - 1;
    }

    function array_search_last(mixed $needle, array $haystack, bool $strict = false): string|int|false 
    {
        $haystack = array_reverse($haystack);
        if(is_associative($haystack)) 
            return array_search($needle, $haystack, $strict);

        $position = array_search($needle, $haystack, $strict);
        if($position === false) return false;

        return count($haystack) - $position - 1;
    }

    function array_search_recursive(mixed $needle, array $haystack):array|false {

        foreach ($haystack as $key => $value) {

            if($value === $needle) return [$key];
            if( is_array($value) && ($current = array_search_recursive($needle, $value)) )
                return  array_merge([$key], $current);
        }

        return false;
    }

    function array_keys_remove  (array $array, ...$keys  ) { return array_filter($array, fn($k) => !in_array($k, $keys), ARRAY_FILTER_USE_KEY); }
    function array_values_remove(array $array, ...$values) { return array_filter($array, fn($v) => !in_array($v, $values)); }
    function array_values_insert(array $array, ...$values) 
    {
        foreach($values as $value)
            if(!in_array($value, $array)) $array[] = $value;
        
        return $array;
    }

    function array_values_insert_any(array $array, ...$values) 
    {
        foreach($values as $value)
            $array[] = $value;
        
        return $array;
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
            $matches = array_transforms(fn($k, $x): array => [trim($x[3]), trim($x[1] . $x[2])], $matches);

        return $matches;
    }

    function array_unique_end($array) 
    {
        $len = count($array);
        return array_transforms(fn($k,$v):array => [$len-$k-1,$v], array_unique(array_reverse($array)));
    }

    function array_unique_map(callable $callback, array $array, int $flags = SORT_STRING): array
    {
        $arrayMask = array_fill_keys(array_keys(array_unique(array_map($callback, $array), $flags)), null);
        return array_intersect_key($array, $arrayMask);
    }

    function cast_from_array(array $array, string $newClass) { return unserialize(str_replace('O:8:"stdClass"','O:'.strlen($newClass).':"'.$newClass.'"',serialize((object) $array) )); }
    function cast_empty(string $newClass) { return unserialize(str_replace('O:8:"stdClass"','O:'.strlen($newClass).':"'.$newClass.'"', serialize((object) []) )); }
    function cast($object, $newClass, ...$args)
    {
        $reflClass      = new \ReflectionClass($object);
        $reflProperties = $reflClass->getProperties();

        $newObject    = new $newClass(...$args);
        $reflNewClass = new \ReflectionClass($newObject);
        foreach ($reflNewClass->getProperties() as $reflNewProperty) {

            $reflNewProperty->setAccessible(true);
            
            $reflProperty = array_filter($reflProperties, fn($p) => $p->getName() == $reflNewProperty->getName());
            $reflProperty = begin($reflProperty) ?? null;
            if ($reflProperty) {

                $reflProperty->setAccessible(true);

                $value = $reflProperty->getValue($object);
                $reflNewProperty->setValue($newObject, $value);
            }
        }

        return $newObject;
    }

    function is_serialized($string): bool { return ($string == 'b:0;' || @unserialize($string) !== false); }
    function is_serializable($object): bool
    {
        try { serialize($object); }
        catch (Exception $e) { return false; }

        return true;
    }
}
