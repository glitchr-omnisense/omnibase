<?php

namespace Base\Subscriber;

use Base\Service\HotParameterBag;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ParameterBagSubscriber implements EventSubscriberInterface
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
        if(!$this->parameterBag instanceof HotParameterBag) return;

        $settingBag = array_flatten(".", $this->settingBag->getRaw(), -1, ARRAY_FLATTEN_PRESERVE_KEYS);
        foreach($settingBag as $setting) {

            if($setting->getBag() === null) continue;
            $this->parameterBag->add([$setting->getBag() => $setting->getValue()]);
        }
    }
}
