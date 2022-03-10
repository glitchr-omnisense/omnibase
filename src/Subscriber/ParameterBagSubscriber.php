<?php

namespace Base\Subscriber;

use Base\Service\BaseSettings;
use Base\Service\HotParameterBag;
use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ParameterBagSubscriber implements EventSubscriberInterface
{
    public function __construct(ParameterBagInterface $parameterBag, BaseSettings $baseSettings)
    {
        $this->parameterBag = $parameterBag;
        $this->baseSettings = $baseSettings;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if(!$this->parameterBag instanceof HotParameterBag) return;

        $settings = array_flatten($this->baseSettings->getRaw(), ARRAY_FLATTEN_PRESERVE_KEYS);
        foreach($settings as $setting) {

            if($setting->getBag() === null) continue;
            $this->parameterBag->add([$setting->getBag() => $setting->getValue()]);
        }
    }
}
