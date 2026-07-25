<?php

namespace Base\Controller;

use Base\Attributes\Attribute\Iconize;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\SecurityLoginType;
use Base\Service\ReferrerInterface;
use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The admin-firewall login ("rescue") entry point. Self-sufficient: it
 * renders the admin extension's login page when base-bundle-admin is
 * installed (@Admin/security/login.html.twig), and otherwise a fully
 * self-contained fallback bundled here that depends on nothing external -
 * no admin package, no third-party admin UI.
 */
class RescueController extends AbstractController
{
    public function __construct(
        protected RouterInterface $router,
        protected SettingBagInterface $settingBag,
        protected Environment $twig,
        protected TranslatorInterface $translator,
        protected FormProxyInterface $formProxy,
    ) {
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
            $targetUrl = $referrer->getUrl() ?? $this->adminUrl();
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

                // Prefer the admin extension's login page; degrade to the
                // self-contained fallback bundled here when it isn't
                // installed. NB: @Admin/page/* (not @Admin/security/*) - the
                // app's own templates/ dir is also mounted on the @Admin
                // namespace, so a @Admin/security/login.html.twig would be
                // shadowed by the app's front-end templates/security/login.
                $template = $this->twig->getLoader()->exists('@Admin/page/login.html.twig')
                    ? '@Admin/page/login.html.twig'
                    : '@Base/security/rescue.html.twig';

                return $this->render($template, [
                    'last_username' => $lastUsername,
                    'translation_domain' => 'forms',
                    'target_path' => $this->adminUrl(),
                    'identifier_label' => '@forms.login.identifier',
                    'password_label' => '@forms.login.password',
                    'logo' => $logo,
                    'error' => null,
                    'identifier' => $lastUsername,
                    'form' => $formProcessor->getForm()->createView(),
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

    /**
     * The admin landing route, or "/" if no admin dashboard is mounted -
     * keeps a base-bundle-only install (no admin package, no `admin`
     * route) from fataling on the post-login redirect.
     */
    private function adminUrl(): string
    {
        try {
            return $this->router->generate('admin');
        } catch (\Throwable) {
            return '/';
        }
    }
}
