<?php

namespace Base\Controller\Frontend;

use Base\BaseBundle;

use Base\Entity\User\Notification;
use Base\Enum\UserRole;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxy;
use Base\Form\Model\ContactModel;
use Base\Form\Type\ContactType;
use Base\Notifier\Notifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\BaseService;
use  Base\Service\SettingBag;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ContactController extends AbstractController
{
    /**
     * @var Notifier
     */
    protected $notifier;

    /**
     * @var FormProxy
     */
    protected $formProxy;

    public function __construct(FormProxy $formProxy, Notifier $notifier)
    {
        $this->formProxy = $formProxy;
        $this->notifier = $notifier;
    }

    /**
     * @Route("/contact", name="base_contact")
     */
    public function Contact(Request $request): Response
    {
        $user = $this->getUser();

        return $this->formProxy
            ->createProcessor("contact", ContactType::class, ["use_model" => true])
            ->setData($user)
            ->onDefault(function(FormProcessorInterface $formProcessor) use ($user) {

                return $this->render('client/contact/index.html.twig', [
                    'user' => $user,
                    "form" => $formProcessor->getForm()->createView()
                ]);
            })
            ->onSubmit(function(FormProcessorInterface $formProcessor, Request $request) use ($user) {

                /**
                 * @var ContactModel
                 */
                $contactModel = $formProcessor->getData();
                $this->notifier->sendContactEmail($contactModel);
                $this->notifier->sendContactEmailConfirmation($contactModel);

                return $this->render('client/contact/success.html.twig', []);
            })
            ->handleRequest($request)
            ->getResponse();
    }
}
