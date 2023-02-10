<?php

namespace Base\Controller;

use App\Entity\User as User;

use Base\Annotations\Annotation\Iconize;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Form\Common\FormModelInterface;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\SecurityLoginType;
use Base\Service\ReferrerInterface;
use Base\Service\ImageServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class RescueController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController
{
    /**
     * @var RouterInterface 
     * */
    protected $router;
    
    /**
     * @var ImageServiceInterface 
     * */
    protected $imageService;

    /**
     * @var SettingBagInterface 
     * */
    protected $settingBag;

    /**
     * @var Environment 
     * */
    protected $twig;

    /**
     * @var TranslatorInterface 
     * */
    protected $translator;

    /**
     * @var FormProxyInterface 
     * */
    protected $formProxy;

    public function __construct(RouterInterface $router, ImageServiceInterface $imageService, SettingBagInterface $settingBag, Environment $twig, TranslatorInterface $translator, FormProxyInterface $formProxy)
    {
        $this->twig = $twig;
        $this->router = $router;
        $this->settingBag = $settingBag;

        $this->imageService = $imageService;
        $this->translator = $translator;
        $this->formProxy  = $formProxy;
    }

    public function configureDashboard(): Dashboard
    {
        $logo  = $this->settingBag->getScalar("base.settings.logo.backoffice");
        if(!$logo) $logo = $this->settingBag->getScalar("base.settings.logo");
        if(!$logo) $logo = "bundles/base/logo.svg";

        $title  = $this->settingBag->getScalar("base.settings.title")  ?? $this->translator->trans("backoffice.title", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        return Dashboard::new()
            ->setFaviconPath("favicon.ico")
            ->setTitle($title);
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/rescue", name="security_rescue")
     * @Iconize({"fas fa-lock","fas fa-unlock"})
     */
    public function LoginRescue(Request $request, ReferrerInterface $referrer, AuthenticationUtils $authenticationUtils): Response
    {
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Redirect to the right page when access denied
        if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->redirect($referrer->getUrl() ?? $this->router->generate("backoffice"));

        // Generate form
        $formProcessor = $this->formProxy
            ->createProcessor("form:login:rescue", SecurityLoginType::class, ["identifier" => $lastUsername])
            ->onDefault(function(FormProcessorInterface $formProcessor) use ($authenticationUtils) { 

                $lastUsername = $authenticationUtils->getLastUsername();
                $logo = $this->settingBag->get("base.settings.logo.backoffice")["_self"] ?? null;
                $logo = $logo ?? $this->settingBag->get("base.settings.logo")["_self"] ?? null;

                return $this->render('@EasyAdmin/page/login.html.twig', [
                    'last_username' => $lastUsername,
                    'translation_domain' => 'forms',
                    'target_path' => $this->router->generate('backoffice'),
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
}
