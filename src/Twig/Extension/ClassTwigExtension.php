<?php

namespace Base\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 */
final class ClassTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_class', 'get_class'),
            new TwigFunction('class_exists', 'class_exists'),
            new TwigFunction('get_short_class', [$this, 'getShortClass']),
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'class_twig_extension';
    }

    /**
     * @param $object
     * @return string
     */
    public function getShortClass($object)
    {
        $formattedClassName = explode('\\', get_class($object));

        return lcfirst(end($formattedClassName));
    }
}
