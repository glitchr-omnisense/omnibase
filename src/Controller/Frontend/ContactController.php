<?php

namespace Base\Controller\Frontend;

use Base\BaseBundle;

use Base\Enum\UserRole;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxy;
use Base\Form\Type\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\BaseService;
use  Base\Service\SettingBag;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ContactController extends AbstractController
{
    protected $formProxy;
    public function __construct(FormProxy $formProxy)
    {
        $this->formProxy = $formProxy;
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

                return $this->render('@Base/client/contact/index.html.twig', [
                    'user' => $user,
                    "form" => $formProcessor->getForm()->createView()
                ]);
            })
            ->onSubmit(function(FormProcessorInterface $formProcessor, Request $request) use ($user) {

                $user = $formProcessor->hydrate($user);
                $this->entityManager->flush();

                return $this->redirectToRoute('@Base/client/success.html.twig');
            })
            ->handleRequest($request)
            ->getResponse();
    }
}
