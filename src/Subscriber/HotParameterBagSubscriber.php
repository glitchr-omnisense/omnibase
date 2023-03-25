<?php

namespace Base\Subscriber;

use Base\Service\HotParameterBag;
use Base\Service\SettingBagInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HotParameterBagSubscriber implements EventSubscriberInterface
{
    /**
     * @var ParameterBag
     */
    protected $parameterBag;
    /**
     * @var SettingBag
     */
    protected $settingBag;

    public function __construct($parameterBag, SettingBagInterface $settingBag)
    {
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommandRequest', 2048],
            KernelEvents::REQUEST  => ['onKernelRequest', 256]
        ];
    }

    public function onCommandRequest(ConsoleCommandEvent $event)
    {
        if (!$this->parameterBag instanceof HotParameterBag) {
            return;
        }
        if ($this->parameterBag->isReady()) {
            return;
        }

        try {

            array_map_recursive(function ($setting) {
                if ($setting === null) {
                    return;
                }
                if ($setting->getBag() === null) {
                    return;
                }

                $this->parameterBag->add([$setting->getBag() => $setting->getValue()]);

            }, $this->settingBag->allRaw(true, true));

        } catch(\PDOException $e) { return; }

        $this->parameterBag->markAsReady();
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!$this->parameterBag instanceof HotParameterBag) {
            return;
        }
        if ($this->parameterBag->isReady()) {
            return;
        }

        $allRaw = [];
        try { $allRaw = $this->settingBag->allRaw(true, true); }
        catch(\PDOException $e) { return; }

        array_map_recursive(function ($setting) {
            if ($setting === null) {
                return;
            }
            if ($setting->getBag() === null) {
                return;
            }

            $this->parameterBag->add([$setting->getBag() => $setting->getValue()]);
        }, $allRaw);

        $this->parameterBag->markAsReady();
    }
}
