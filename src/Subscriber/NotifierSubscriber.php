<?php

namespace Base\Subscriber;

use Base\Entity\User\Notification;
use Base\Enum\UserRole;
use Base\Notifier\Recipient\Recipient;
use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NotifierSubscriber implements EventSubscriberInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;
    
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;
    
    /** * @var bool */
    protected $debug;
    
    /** * @var string */
    protected $technicalRecipient;
    
    /** * @var bool */
    protected bool $technicalLoopback;
    
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, ParameterBagInterface $parameterBag, string $debug)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->debug                = $debug;

        $technicalEmail = $parameterBag->get("base.notifier.technical_recipient.email");
        $technicalPhone = $parameterBag->get("base.notifier.technical_recipient.phone");
        $this->technicalRecipient = ($technicalEmail || $technicalPhone) ? new Recipient($technicalEmail, $technicalPhone) : null;
        $this->technicalLoopback  = $parameterBag->get("base.notifier.technical_loopback");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest']
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;        
        if(!$this->authorizationChecker->isGranted(UserRole::ADMIN));

        $notification = null;
        if ($this->debug && !$this->technicalLoopback) $notification = new Notification("@notifications.notifier.no_loopback");
        if(!$this->debug &&  $this->technicalLoopback) $notification = new Notification("@notifications.notifier.no_debug", [$this->technicalEmail]);

        $notification?->send("warning");
    }
}
