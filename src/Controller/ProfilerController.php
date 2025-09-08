<?php

namespace Base\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Notifier\NotifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 */
class ProfilerController extends AbstractController
{
    /**
     * @var NotifierInterface
     * */
    protected NotifierInterface $notifier;

    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    public function __construct(NotifierInterface $notifier, UserRepository $userRepository)
    {
        $this->notifier = $notifier;
        $this->userRepository = $userRepository;
    }

    #[Route("/_profiler/email", name: "_profiler_email", priority: 1)]
    public function Email(): Response
    {
        $mail = mailparse($this->notifier->getTechnicalRecipient()->getEmail());
        $user = $this->getUser();
        if(!$user) {
            $user = $this->userRepository->findByEmail(first($mail))?->getResult();
            if (!$user) {
                $user = new User();
                $user->setUsername(first($mail));
                $user->setEmail(first(array_keys($mail)));
            }
        }

        return $this->notifier->renderTestEmail($user);
    }

    #[Route("/_profiler/email/send", name: "_profiler_email_send", priority: 1)]
    public function SendEmail(): Response
    {
        $mail = mailparse($this->notifier->getTechnicalRecipient()->getEmail());
        $user = $this->getUser();
        if(!$user) {
            $user = $this->userRepository->findByEmail(first($mail))?->getResult();
            if (!$user) {
                $user = new User();
                $user->setUsername(first($mail));
                $user->setEmail(first(array_keys($mail)));
            }
        }

        $this->notifier->sendTestEmail($user);
        return $this->redirectToRoute("_profiler_email");
    }
}
