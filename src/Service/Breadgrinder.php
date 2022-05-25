<?php

namespace Base\Service;

use Base\Model\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class Breadgrinder implements BreadgrinderInterface
{
    protected $breadcrumbs = [];

    protected $router;
    public function __construct(RouterInterface $router, TranslatorInterface $translator, BaseService $baseService)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->baseService = $baseService;
    }

    public function has(string $name): bool { return array_key_exists($name, $this->breadcrumbs); }
    public function grind(string $name, array $options = [], ?string $template = null): Breadcrumb
    {
        // Some default parameters (if not created yet)
        if(!$this->has($name)) {

            $options["class"]      = $options["class"]      ?? $this->baseService->getParameterBag("base.breadcrumb.class");
            $options["class_item"] = $options["class_item"] ?? $this->baseService->getParameterBag("base.breadcrumb.class_item");
            $options["separator"]  = $options["separator"]  ?? $this->baseService->getParameterBag("base.breadcrumb.separator");
        }

        // Prepare object
        $this->breadcrumbs[$name] = $this->breadcrumbs[$name] ?? new Breadcrumb($this->router, $this->translator);
        if($options)  $this->breadcrumbs[$name]->addOptions($options);
        if($template) $this->breadcrumbs[$name]->setTemplate($template);

        return $this->breadcrumbs[$name];
    }
}
