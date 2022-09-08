<?php

namespace Base\Subscriber;

use Base\Service\HotParameterBag;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HotParameterBagSubscriber implements EventSubscriberInterface
{
    public function __construct($parameterBag, SettingBagInterface $settingBag)
    {
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST  => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        // if(!$event->isMainRequest()) return;
        // if(!$this->parameterBag instanceof HotParameterBag) return;

        // array_map_recursive(function($setting) {

        //     if($setting === null) return;
        //     if($setting->getBag() === null) return;

        //     $this->parameterBag->add([$setting->getBag() => $setting->getValue()]);

        // }, $this->settingBag->allRaw(true, true));

        // $this->parameterBag->markAsReady();
    }
}
