<?php

namespace Base\Traits;

trait BagTrait
{
    public static function read(?string $path, array $bag = [])
    {
        if($path === null) return $bag;

        $pathArray = explode(".", $path);
        foreach ($pathArray as $index => $key) {

            if($key == "_self" && $index != count($pathArray)-1)
                throw new \Exception("Failed to read \"$path\": _self can only be used as tail parameter");

            if(!array_key_exists($key.".", $bag))
                return null;

            $bag = &$bag[$key."."];
        }

        if(array_key_exists("_self", $bag)) return $bag["_self"];
        return array_transforms(function($k, $v, $c):?array {

            if($k == "_self") return null;
            return is_array($v) && count($v) == 1 && array_key_exists("_self", $v) ? [rtrim($k,"."), $v["_self"]] : [rtrim($k,"."), array_transforms($c, $v)];

        }, $bag);
    }

    public static function write($path, $value, array &$bag = [])
    {
        foreach (explode(".", $path) as $key)
            $bag = &$bag[$key];

        $bag = $value;
    }

    protected static function normalize(?string $path, array $bag) : array {

        $values = [];

        // Generate default structure
        $array = &$values;
        if($path !== null) {

            $el = explode(".", $path);
            $last = count($el)-1;
            foreach ($el as $index => $key) {

                if($key == "_self" && $index != $last)
                    throw new \Exception("Failed to normalize \"$path\": \"_self\" key can only be used as tail parameter");

                if(!array_key_exists($key, $array)) $array[$key] = ["_self" => null];
                $array = &$array[$key];
            }
        }

        // Fill it with settings
        foreach($bag as $keys => $entry) {

            $array = &$values;
            foreach (explode(".", $keys) as $key)
                $array = &$array[$key."."];

            $array["_self"] = $entry;
        }

        return $values;
    }

    protected static function denormalize(array $bag, ?string $path = null) : array {

        if($path) {

            foreach(explode(".", $path) as $value)
                $bag = $bag[$value."."];
        }

        $bag = array_transforms(
            fn($k, $v):?array => [str_replace(["_self.", "._self", "_self"], "", $k), $v],
            array_flatten("", $bag, -1, ARRAY_FLATTEN_PRESERVE_KEYS, fn($k,$v):bool => is_numeric($k) || str_ends_with($k, "."))
        );

        return $bag;
    }
}