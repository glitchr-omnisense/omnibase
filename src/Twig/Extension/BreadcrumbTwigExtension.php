<?php

namespace Base\Twig\Extension;

use Base\Form\FormProxy;
use Base\Model\PaginationInterface;
use Base\Service\BaseService;
use Base\Service\BreadcrumbGrinder;
use Base\Service\PaginatorInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadcrumbTwigExtension extends AbstractExtension
{
    public function __construct(Environment $twig, BreadcrumbGrinder $breadcrumbGrinder)
    {
        $this->twig = $twig;
        $this->breadcrumbGrinder = $breadcrumbGrinder;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('render_breadcrumb', [$this, 'renderBreadcrumb'], ['is_safe' => ['all']]),
        ];
    }

    public function renderBreadcrumb(string $name, array $parameters = []): ?string
    {
        $breadcrumb = $this->breadcrumbGrinder->get($name);
        if($breadcrumb === null) 
            throw new \Exception("Breadcrumb \"$name\" not found in grinder machine.");

        return $this->twig->render($breadcrumb->getTemplate(), ["breadcrumb" => $breadcrumb]);
    }

    public function getName()
    {
        return 'breadcrumb_extension';
    }
}
