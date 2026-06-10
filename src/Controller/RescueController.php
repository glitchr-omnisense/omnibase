<?php

namespace Base\Controller;

use Base\Attributes\Attribute\Iconize;
use Base\Controller\Admin\AbstractDashboardController;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\SecurityLoginType;
use Base\Service\ReferrerInterface;
use Base\Service\MediaServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class RescueController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController
{
    /**
     * @var RouterInterface
     * */
    protected RouterInterface $router;

    /**
     * @var MediaServiceInterface
     * */
    protected MediaServiceInterface $mediaService;

    /**
     * @var SettingBagInterface
     * */
    protected SettingBagInterface $settingBag;

    /**
     * @var Environment
     * */
    protected Environment $twig;

    /**
     * @var TranslatorInterface
     * */
    protected TranslatorInterface $translator;

    /**
     * @var FormProxyInterface
     * */
    protected FormProxyInterface $formProxy;

    public function __construct(RouterInterface $router, MediaServiceInterface $mediaService, SettingBagInterface $settingBag, Environment $twig, TranslatorInterface $translator, FormProxyInterface $formProxy)
    {
        $this->twig = $twig;
        $this->router = $router;
        $this->settingBag = $settingBag;

        $this->mediaService = $mediaService;
        $this->translator = $translator;
        $this->formProxy = $formProxy;
    }

    public function configureDashboard(): Dashboard
    {
        $logo = $this->settingBag->getScalar("base.settings.logo.admin");
        if (!$logo) {
            $logo = $this->settingBag->getScalar("base.settings.logo");
        }
        if (!$logo) {
            $logo = "bundles/base/images/logo.svg";
        }

        $title = $this->settingBag->getScalar("base.settings.title") ?? $this->translator->trans("admin.title", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        return Dashboard::new()
            ->setFaviconPath("favicon.ico")
            ->setTitle($title);
    }

    /**
     * Link to this controller to start the "connect" process
     */

    #[Route("/rescue", name: "security_rescue")]
    #[Iconize(["fa-solid fa-lock", "fa-solid fa-unlock"])]
    public function LoginRescue(Request $request, ReferrerInterface $referrer, AuthenticationUtils $authenticationUtils): Response
    {
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Redirect to the right page when access denied
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $targetUrl = $referrer->getUrl() ?? $this->router->generate("admin");
            $referrer->clear();

            return $this->redirect($targetUrl);
        }

        // Generate form
        $formProcessor = $this->formProxy
            ->createProcessor("form:login:rescue", SecurityLoginType::class, ["identifier" => $lastUsername])
            ->onDefault(function (FormProcessorInterface $formProcessor) use ($authenticationUtils) {
                $lastUsername = $authenticationUtils->getLastUsername();
                $logo = $this->settingBag->get("base.settings.logo.admin")["_self"] ?? null;
                $logo = $logo ?? $this->settingBag->get("base.settings.logo")["_self"] ?? null;

                return $this->render('@EasyAdmin/page/login.html.twig', [
                    'last_username' => $lastUsername,
                    'translation_domain' => 'forms',
                    'target_path' => $this->router->generate('admin'),
                    'identifier_label' => '@forms.login.identifier',
                    'password_label' => '@forms.login.password',
                    'logo' => $logo,
                    "identifier" => $lastUsername,
                    "form" => $formProcessor->getForm()->createView()
                ]);
            })
            ->handleRequest($request);

        return $formProcessor->getResponse();
    }

    #[Route("/rescue-request", name: "admin_rescue", priority: -1)]
    public function index(): Response
    {
        return $this->redirectToRoute("security_rescue");
    }
}
