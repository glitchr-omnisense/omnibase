<?php

namespace Base\Model;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class Breadcrumb implements BreadcrumbInterface
{
    protected array $items   = [];
    protected array $options = [];

    protected $router = null;
    protected $template = "@Base/breadcrumb/default.html.twig";

    public function __construct(RouterInterface $router, array $options = [], ?string $template = null)
    {
        $this->router = $router;
        $this->options = $options;
        if($template) $this->template = $template;
    }

    public function getRouter(): RouterInterface { return $this->router; }
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
        return $this;
    }

    public function getTemplate() :string { return $this->template; }
    public function setTemplate(string $template)
    {
        $this->template = $template;
        return $this;
    }

    public function getOptions(): array { return $this->options; }
    public function addOptions(array $options)
    {
        foreach($options as $key=> $option)
            $this->addOption($key, $option);
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }
    public function addOption(string $name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }
    public function removeOption(string $name)
    {
        if(array_key_exists($name, $this->options))
            unset($this->options[$name]);
        return $this;
    }

    protected function getFormattedItem(string $label, ?string $route = null, array $routeParameters = [])
    {
        return [
            "label"      => $label,
            "url"        => ($route ? $this->router->generate($route, $routeParameters) : null),
            "separator"  => $this->getOptions()["separator"]  ?? null,
            "class"      => $this->getOptions()["class"]      ?? null,
            "item_class" => $this->getOptions()["item_class"] ?? null,
        ];
    }

    public function prependItem(string $label, ?string $route = null, array $routeParameters = []) 
    {
        array_unshift($this->items, $this->getFormattedItem($label, $route, $routeParameters));
        return $this;
    }

    public function appendItem(string $label, ?string $route = null, array $routeParameters = [])
    {
        $this->items[] = $this->getFormattedItem($label, $route, $routeParameters);
        return $this;
    }

    public function getLength() { return count($this->items); }
    public function getItems() { return $this->items; }

    public function getFirstItem() { return $this->getItem(0); }
    public function getLastItem() { return $this->getItem($this->getLength()-1); }
    public function getItem(int $index)
    {
        return $this->items[$index] ?? null;
    }

}
