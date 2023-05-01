<?php

namespace Base\Twig\Extension;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 *
 */
final class ClassTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_short_class', [$this, 'getShortClass']),
            new TwigFunction('get_class', [$this, 'getClass'])
        ];
    }

    public function getName()
    {
        return 'class_twig_extension';
    }

    public function getClass($object)
    {
        return get_class($object);
    }

    public function getShortClass($object)
    {
        $formattedClassName = explode('\\', get_class($object));
        return lcfirst(end($formattedClassName));
    }
}
