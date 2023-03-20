<?php

namespace Base\Twig\Extension;

use Base\Service\Breadgrinder;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadgrinderTwigExtension extends AbstractExtension
{
    /**
     * @var Breadgrinder
     */
    protected $breadgrinder;

    public function __construct(Breadgrinder $breadgrinder)
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

        if ($breadcrumb === null) {
            throw new \Exception("Breadcrumb \"$name\" not found in the grinder machine.");
        }

        return $twig->render($breadcrumb->getTemplate(), ["breadcrumb" => $breadcrumb]);
    }
}
