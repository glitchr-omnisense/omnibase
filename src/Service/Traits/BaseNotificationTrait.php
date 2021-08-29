<?php

namespace Base\Service\Traits;

use App\Entity\User;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Base\Entity\User\Notification;
use Base\Service\BaseService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

trait BaseNotificationTrait
{
    public function setNotifier(NotifierInterface $notifier, ?ChannelPolicyInterface $notifierPolicy = null)
    {
        if (BaseService::$notifier) return $this;

        // Update user notifier
        BaseService::$notifier = $notifier;
        BaseService::$notifierPolicy = $notifierPolicy;

        // Address support only once..
        BaseService::$notifier->addAdminRecipient(new Recipient($this->getMail()));

        // Add additional admin users.
        foreach ($this->getAdminUsers() as $adminUser)
            BaseService::$notifier->addAdminRecipient($adminUser->getRecipient());
    }

    public function getAdminUsers() {

        $roles = $this->getParameterBag("base.notifier.admin_recipients");
        if(!$roles) return null;

        $userRepository = $this->getEntityManager()->getRepository(User::class);
        return $userRepository->findByRoles($roles);
    }

    public function getMail()
    {
        $name = $this->getTranslator()->trans("mail") ?? null;
        if (!$name) {

            $domain = explode(".", $this->getDomain());
            array_pop($domain);

            $name = implode(".", $domain);
        }

        // Mail is not defined in messages, because it is defined in services.yaml
        return $name . " <" . $this->getParameterBag('base.mail') . ">";
    }
}
