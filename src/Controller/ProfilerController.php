<?php

namespace Base\Controller;

use Base\Notifier\NotifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class ProfilerController extends AbstractController
{
    /**
     * @var NotifierInterface
     * */
    protected NotifierInterface $notifier;

    public function __construct(NotifierInterface $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @Route("/_profiler/email", name="_profiler_email", priority=1)
     */
    public function Email(): Response
    {
        return $this->notifier->renderTestEmail($this->getUser());
    }

    /**
     * @Route("/_profiler/email/send", name="_profiler_email_send", priority=1)
     */
    public function SendEmail(): Response
    {
        $this->notifier->sendTestEmail($this->getUser());
        return $this->redirectToRoute("_profiler_email");
    }
}
