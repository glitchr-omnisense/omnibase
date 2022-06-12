<?php

namespace Base\Controller;

use App\Entity\User              as User;

use Base\Annotations\Annotation\Iconize;
use Base\Service\ReferrerInterface;
use Base\Form\Type\Security\LoginType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use Base\Security\RescueFormAuthenticator;
use Base\Service\BaseService;
use Base\Service\SettingBag;
use Base\Service\SettingBagInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/* "abstract" (remove because of routes) */
class RescueController extends AbstractDashboardController
{
    public function __construct(RouterInterface $router, SettingBagInterface $settingBag)
    {
        $this->router = $router;
        $this->settingBag = $settingBag;
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setFaviconPath("/favicon.ico");
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
                return $this->redirect($referrer->getUrl() ?? $this->router->generate("dashboard"));
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
            'target_path' => $this->router->generate('dashboard'),
            'identifier_label' => '@forms.login.identifier',
            'password_label' => '@forms.login.password',
            'logo' => $logo,
            "page_title" => $this->settingBag->getScalar("base.settings.title.backoffice"),
            "identifier" => $lastUsername,
            "form" => $form->createView()
        ]);
    }
}
