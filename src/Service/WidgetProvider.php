<?php

namespace Base\Service;

use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\Widget\Slot;
use Base\Repository\Sitemap\Widget\SlotRepository as WidgetSlotRepository;
use Base\Repository\Sitemap\WidgetRepository;
use Symfony\Contracts\Cache\CacheInterface;

class WidgetProvider implements WidgetProviderInterface
{
    // NB: Cache is disabled, because I should the implementation using secondary level cache
    public const __CACHE__ = false; 
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

        $this->uuidByPath = $this->cache->getItem(class_basename(self::class)."[Slot][uuidByPath]")->get() ?? [];
    }

    protected $widgets = [];
    public function get(string $uuid): ?Widget { return $this->getWidget($uuid); }
    public function getWidget(string $uuid): ?Widget
    {
        if($this->hasCache($uuid))
            return $this->getCache($uuid);

        $this->widgets[$uuid] = $this->widgets[$uuid] ?? $this->widgetRepository->findOneByUuid($uuid);
        foreach($this->widget[$uuid]->getTranslations() as $t) 
            $t->fetchAll();

        $this->applyCache($uuid, $this->widget[$uuid]);

        return $this->widgets[$uuid];
    }

    protected $uuidByPath = [];
    public function getSlot(string $path): ?Slot { return $this->getWidgetSlot($path); }
    public function getWidgetSlot(string $path): ?Slot
    {
        $uuid = $this->uuidByPath[$path] ?? null;
        if($this->hasCache($uuid))
            return $this->getCache($uuid);
        
        $slot = $this->widgetSlotRepository->findOneByPath($path);
        
        $this->uuidByPath[$path] = $slot ? $slot->getUuid() : null;
        $item = $this->cache->getItem(class_basename(self::class)."[Slot][uuidByPath]");
        if ($this->isCacheEnabled())
            $this->cache->save( $item->set($this->uuidByPath) );

        $uuid = $this->uuidByPath[$path] ?? null;
        $this->widgets[$uuid] = $slot;

        $this->applyCache($uuid, $this->widgets[$uuid]);

        return $this->widgets[$uuid];
    }

    public function getUuidByPath(string $path) { return $this->uuidByPath[$path] ?? null; }
    protected function applyCache(?string $uuid, $widget)
    {
        if($uuid === null) return false;
        $item = $this->cache->getItem(class_basename(self::class)."[".$uuid."]");
        if ($this->isCacheEnabled()) {

            $this->cache->save( $item->set($widget) );
            if($widget instanceof Slot) {

                foreach($widget->getWidgets() as $subWidget)
                    $this->cache->save( $item->set($subWidget) );
            }
        }
        
        return true;
    }
    
    protected function hasCache(?string $uuid): bool
    {
        if($uuid === null) return false;
        return $this->isCacheEnabled() && $this->cache->getItem(class_basename(self::class)."[".$uuid."]")->isHit();
    }

    protected function getCache(?string $uuid)
    {
        if($uuid === null) return null;

        $item = $this->cache->getItem(class_basename(self::class)."[".$uuid."]");
        // dump($uuid, $item->get());
        return $item->get();
    }

    public function deleteCache(?string $uuid)
    {
        if($uuid === null) return $this;
        $this->cache->delete(class_basename(self::class)."[".$uuid."]");
        if(array_key_exists($uuid, $this->widgets))
            unset($this->widgets[$uuid]);
 
        return $this;
    }
}