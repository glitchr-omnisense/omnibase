<?php

namespace Base\Service;

use Base\Model\Breadcrumb;
use Symfony\Component\Routing\RouterInterface;

class BreadcrumbGrinder implements BreadcrumbGrinderInterface
{
    protected $breadcrumbs = [];

    protected $router;
    public function __construct(RouterInterface $router, BaseService $baseService)
    {
        $this->router = $router;
        $this->baseService = $baseService;
    }

    public function create(string $name, array $options = [], ?string $template = null): Breadcrumb
    {
        if($this->breadcrumbs[$name])
            throw new \Exception("Breadcrumb \"$name\" already exists.");

        // Some default parameters
        $options["class"]      = $options["class"]      ?? $this->baseService->getParameterBag("base.breadcrumb.class");
        $options["class_item"] = $options["class_item"] ?? $this->baseService->getParameterBag("base.breadcrumb.class_item");
        $options["separator"]  = $options["separator"]  ?? $this->baseService->getParameterBag("base.breadcrumb.separator");

        $this->breadcrumbs[$name] = new Breadcrumb($this->router, $options, $template);

        return ($this->breadcrumbs[$name]);
    }

    public function has(string $name): bool { return array_key_exists($name, $this->breadcrumbs); }
    public function get(string $name): ?Breadcrumb
    {
        if(!$this->has($name)) return null;
        return $this->breadcrumbs[$name];
    }
}
