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

            if(!array_key_exists($key, $bag))
                throw new \Exception("Failed to read \"$path\": key not found");

            $bag = &$bag[$key];
        }

        return $bag;
    }

    public static function write($path, $value, array &$bag = [])
    {
        foreach (explode(".", $path) as $key)
            $bag = &$bag[$key];

        $bag = $value;
    }

    public static function normalize(?string $path, array $bag) {

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
        foreach($bag as $entry) {

            $array = &$values;
            foreach (explode(".", $entry) as $key)
                $array = &$array[$key];

            $array["_self"] = $entry;
        }

        return $values;
    }

    public static function denormalize(array $bag, ?string $path = null) {

        if($path) {

            foreach(explode(".", $path) as $value)
                $bag = $bag[$value];
        }

        $bag = array_transforms(
            fn($k, $v):?array => [str_replace(["_self.", "._self", "_self"], "", $k), $v],
            array_flatten(".", $bag, -1, ARRAY_FLATTEN_PRESERVE_KEYS)
        );

        foreach($bag as $key => $entry)
        {
            $matches = [];
            if(preg_match("/(.*)[0-9]+$/", $key, $matches)) {

                $path = $matches[1];
                if(!array_key_exists($path, $bag))
                    $bag[$path] = [];

                $bag[$path][] = $entry;
                unset($bag[$key]);
            }
        }

        return array_filter($bag);
    }
}