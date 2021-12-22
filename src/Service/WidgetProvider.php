<?php

namespace Base\Service;

use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\Widget\Slot;
use Base\Repository\Sitemap\Widget\SlotRepository as WidgetSlotRepository;
use Base\Repository\Sitemap\WidgetRepository;
use Symfony\Contracts\Cache\CacheInterface;

class WidgetProvider implements WidgetProviderInterface
{    
    public const __CACHE__ = true;
    protected function isCacheEnabled() 
    {
        if(!self::__CACHE__) return false;
        if(!$this->cache)    return false;
        if(is_cli())   return false;

        return true;
    }

    public function __construct(CacheInterface $cache, WidgetRepository $widgetRepository, WidgetSlotRepository $widgetSlotRepository)
    {
        $this->cache = $cache;
        $this->widgetRepository = $widgetRepository;
        $this->widgetSlotRepository = $widgetSlotRepository;
    }

    protected $widgets = [];
    public function get(string $slug): ?Widget { return $this->getWidget($slug); }
    public function getWidget(string $slug): ?Widget
    {
        if($this->hasCache(Widget::class, $slug))
            return $this->getCache(Widget::class, $slug);

        $this->widgets[$slug] = $this->widgets[$slug] ?? $this->widgetRepository->findOneBySlug($slug);
        $this->applyCache(Widget::class, $slug, $this->widget[$slug]);

        return $this->widgets[$slug];
    }

    protected $widgetSlots = [];
    public function getSlot(string $path): ?Slot
    {
        if($this->hasCache(Slot::class, $path))
            return $this->getCache(Slot::class, $path);

        $this->slots[$path] = $this->slots[$path] ?? $this->widgetSlotRepository->findOneByInstanceOfAndPath(Slot::class, $path);
        $this->applyCache(Slot::class, $path, $this->slots[$path]);

        return $this->slots[$path];
    }

    protected function applyCache($class, string $identifier, $widget)
    {
        $class = camel_to_snake(class_basename($class));
        $item = $this->cache->getItem("app.".$class."[".$identifier."]");
        if($this->isCacheEnabled())
            $this->cache->save( $item->set($widget) );
        
        return true;
    }
    
    protected function hasCache($class, string $identifier)
    {
        $class = camel_to_snake(class_basename($class));
        return $this->cache->getItem("app.".$class."[".$identifier."]")->isHit();
    }

    protected function getCache($class, string $identifier) 
    {
        $class = camel_to_snake(class_basename($class));
        $item = $this->cache->getItem("app.".$class."[".$identifier."]");
        return $item->get();
    }

    protected function deleteCache($class, string $identifier)
    {
        $class = camel_to_snake(class_basename($class));
        $this->cache->delete("app.".$class."[".$identifier."]");
        return $this;
    }
}