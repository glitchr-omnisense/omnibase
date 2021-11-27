<?php

namespace Base\Security;

use App\Entity\User;
use Base\Annotations\Annotation\Hashify;
use Base\Entity\User\Notification;
use Base\Service\BaseService;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Google\ReCaptcha\Badge\CaptchaBadge;
use Google\ReCaptcha\Service\GrService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginFormAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'base_login';
    public const LOGOUT_ROUTE = 'base_logout';

    private $entityManager;
    private $csrfTokenManager;
    private $router;

    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router, CsrfTokenManagerInterface $csrfTokenManager, BaseService $baseService)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $entityManager->getRepository(User::class);

        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->baseService = $baseService;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse($this->router->generate(self::LOGIN_ROUTE));
    }

    public function supports(Request $request): ?bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): PassportInterface
    {
        $identifier = $request->get('login')["username"] ?? $request->get("username") ?? "";
        $password   = $request->get('login')["password"] ?? $request->get("password") ?? "";
        $request->getSession()->set(Security::LAST_USERNAME, $identifier);

        $badges   = [];
        if( array_key_exists("_remember_me", $request->get('login') ?? []) ) {
            $badges[] = new RememberMeBadge();
            if($request->get('login')["_remember_me"]) end($badges)->enable();
        }

        if( array_key_exists("_csrf_token", $request->get('login') ?? []) )
            $badges[] = new CsrfTokenBadge("login", $request->get('login')["_csrf_token"]);
        if( array_key_exists("password", $request->get('login') ?? []) )
            $badges[] = new PasswordUpgradeBadge($password, $this->userRepository);
        if( array_key_exists("_captcha", $request->get('login') ?? []) && class_exists(CaptchaBadge::class) )
            $badges[] = new CaptchaBadge("_captcha", $request->get('login')["_captcha"]);

        return new Passport(
            new UserBadge($identifier),
            new PasswordCredentials($password), $badges);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewall): ?Response
    {
        // Update client information
        if( ($user = $token->getUser()) ) {
            $user->setLocale();
            $user->setTimezone();

            $this->entityManager->flush();
        }

        // Check if target path provided via $_POST..
        $targetPath = 
            $request->request->get("_target_path") ??
            $request->getSession()->get('_security.main.target_path') ?? 
            $request->getSession()->get('_security.account.target_path');

        $targetRoute = 
            $this->baseService->getRoute($request->request->get("_target_path")) ??
            $this->baseService->getRoute($request->getSession()->get('_security.main.target_path')) ?? 
            $this->baseService->getRoute($request->getSession()->get('_security.account.target_path'));

            dump($request->request->get("_target_path"));
            dump($request->request->get("_security.main.target_path"));
            dump($request->request->get("_security.account.target_path"));

        if ($targetPath &&
            $targetRoute != LoginFormAuthenticator::LOGOUT_ROUTE &&
            $targetRoute != LoginFormAuthenticator::LOGIN_ROUTE )
            return $this->baseService->redirect($targetPath);

        return $this->baseService->redirectToRoute($this->baseService->getRoute("/"));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->router->generate(self::LOGIN_ROUTE));
    }
}
