<?php

namespace Base\Controller;

use App\Entity\User as User;

use Base\Annotations\Annotation\Iconize;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Service\ReferrerInterface;
use Base\Form\Type\Security\LoginType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use Base\Service\SettingBagInterface;
use Base\Twig\Environment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/* "abstract" (remove because of routes) */
class RescueController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController
{
    public function __construct(RouterInterface $router, SettingBagInterface $settingBag, Environment $twig)
    {
        $this->router = $router;
        $this->settingBag = $settingBag;
        $this->twig = $twig;
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
        if ( ($user = $this->getUser()) ) {

            if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
                return $this->redirect($referrer->getUrl() ?? $this->router->generate("backoffice"));
        }

        // Generate form
        $user = new User();
        $form = $this->createForm(LoginType::class, $user, ["identifier" => $lastUsername]);
        $form->handleRequest($request);

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
            "form" => $form->createView()
        ]);
    }
}
