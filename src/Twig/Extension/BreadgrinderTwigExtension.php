<?php

namespace Base\Twig\Extension;

use Base\Service\BreadgrinderInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadgrinderTwigExtension extends AbstractExtension
{
    /**
     * @var BreadgrinderInterface
     */
    protected BreadgrinderInterface $breadgrinder;

    public function __construct(BreadgrinderInterface $breadgrinder)
    {
        $this->breadgrinder = $breadgrinder;
    }

    public function getName()
    {
        return 'breadcrumb_extension';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_breadcrumb', [$this, 'renderBreadcrumb'], ["needs_environment" => true, 'is_safe' => ['all']]),
        ];
    }

    public function renderBreadcrumb(Environment $twig, string $name, Request $request, array $options = []): ?string
    {
        $breadcrumb = $this->breadgrinder->grind($name, $options);
        $breadcrumb->compute($request);

        return $twig->render($breadcrumb->getTemplate(), ["breadcrumb" => $breadcrumb]);
    }
}
