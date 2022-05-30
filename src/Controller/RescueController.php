<?php

namespace Base\Controller;

use App\Entity\User              as User;

use Base\Annotations\Annotation\Iconize;
use Base\Component\HttpFoundation\Referrer;
use Base\Form\Type\Security\LoginType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use Base\Security\RescueFormAuthenticator;
use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/* "abstract" (remove because of routes) */
class RescueController extends AbstractDashboardController
{
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
    public function LoginRescue(Request $request, Referrer $referrer, AuthenticationUtils $authenticationUtils, BaseService $baseService): Response
    {
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $targetPath = strval($referrer);
        $targetRoute = $baseService->getRouteName($targetPath);

        // Redirect to the right page when access denied
        if ( ($user = $this->getUser()) && $user->isPersistent() ) {

            if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {

                // Check if target path provided via $_POST..
                $targetPath = strval($referrer);
                $targetRoute = $baseService->getRouteName($targetPath);
                if ($targetPath && !in_array($targetRoute, [RescueFormAuthenticator::LOGOUT_ROUTE, RescueFormAuthenticator::LOGIN_ROUTE]) )
                    return $baseService->redirect($targetPath);

                return $this->redirectToRoute("dashboard");
            }
        }

        // Generate form
        $user = new User();
        $form = $this->createForm(LoginType::class, $user, ["identifier" => $lastUsername]);
        $form->handleRequest($request);

        $lastUsername = $authenticationUtils->getLastUsername();

        $logo = $baseService->getSettings()->get("base.settings.logo.backoffice")["_self"] ?? null;
        $logo = $logo ?? $baseService->getSettings()->get("base.settings.logo")["_self"] ?? null;

        return $this->render('@EasyAdmin/page/login.html.twig', [
            'last_username' => $lastUsername,
            'translation_domain' => 'forms',
            'target_path' => $baseService->generateUrl('dashboard'),
            'identifier_label' => '@forms.login.identifier',
            'password_label' => '@forms.login.password',
            'logo' => $logo,
            "page_title" => $baseService->getSettings()->getScalar("base.settings.title"),
            "identifier" => $lastUsername,
            "form" => $form->createView()
        ]);
    }
}
