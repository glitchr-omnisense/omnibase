<?php

namespace Base\Twig\Extension;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Entity\User\Notification;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

use Twig\Environment;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 *
 */

use Twig\Extra\Intl\IntlExtension;
use Symfony\Component\DependencyInjection\Container;

final class ClassTwigExtension extends AbstractExtension
{
    public function getFunctions()
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