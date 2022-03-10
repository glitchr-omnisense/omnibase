<?php

namespace Base\Service;

use Psr\Container\ContainerInterface;

class ParameterBag extends \Symfony\Component\DependencyInjection\ParameterBag\ContainerBag implements ParameterBagInterface, ContainerInterface
{
    public const __SEPARATOR__ = ".";
    
    public function get(string $key = "", array $bag = null): array|bool|string|int|float|null
    {
        if($bag == null) $bag = $this->all();
        if (array_key_exists($key, $bag))
            return $bag[$key];

        $array = [];
        for ($i = 0; parent::has($key . self::__SEPARATOR__ . $i); $i++)
            $array[] = parent::get($key . self::__SEPARATOR__ . $i);

        if (!empty($array)) return $array;

        // Associative array stored
        if (($paths = preg_grep('/' . $key . '\.[0-9]*\.[.*]*/', array_keys($bag)))) {

            foreach ($paths as $path)
                $this->set($path, $bag[$path], $array);

            foreach (explode(self::__SEPARATOR__, $key) as $key)
                $array = &$array[$key];

            return $array;
        }

        // No parameter
        return null;
    }

    function set($path, $value, array &$bag = null)
    {
        foreach (explode(self::__SEPARATOR__, $path) as $key)
            $bag = &$bag[$key];

        $bag = $value;
        return $this;
    }
}