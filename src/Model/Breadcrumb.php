<?php

namespace Base\Model;

use Base\Service\TranslatorInterface;
use Base\Traits\BaseTrait;
use Iterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class Breadcrumb implements BreadcrumbInterface, Iterator
{
    protected array $items   = [];
    protected array $options = [];

    protected $router = null;
    protected $template = "@Base/breadcrumb/default.html.twig";

    protected $iterator  = 0;

    public function rewind(): void { $this->iterator = 0; }
    public function next(): void   { $this->iterator++; }
    public function key()          { return $this->iterator; }
    public function valid(): bool  { return $this->getLength() > $this->iterator; }
    public function current()      { return $this->getItem($this->iterator); }

    public function __construct(RouterInterface $router, TranslatorInterface $translator, array $options = [], ?string $template = null)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->options = $options;
        if($template) 
            $this->template = $template;
    }

    public function compute(Request $request)
    {
        $route      = $request->attributes->get('_route');
        $controller = $request->attributes->get('_controller');
        $params     = $request->attributes->get('_route_params');

        dump($route, $controller, $params, $this->getRouter());
        return $this;
    }

    public function getRouter(): RouterInterface { return $this->router; }
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
        return $this;
    }

    public function getTranslator(): TranslatorInterface { return $this->translator; }
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
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
        foreach($options as $key => $option)
            $this->addOption($key, $option);
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    public function getOption(string $name) { return $this->options[$name]; }
    public function addOption(string $name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }
    
    public function removeOption(string $name)
    {
        if(array_key_exists($name, $this->options)) unset($this->options[$name]);
        return $this;
    }

    protected function getFormattedItem(string $label, ?string $route = null, array $routeParameters = [])
    {
        return [
            "label"      => $label,
            "url"        => ($route ? $this->router->generate($route, $routeParameters) : null)
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
