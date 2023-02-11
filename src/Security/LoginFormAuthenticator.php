<?php

namespace Base\Security;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Base\Routing\RouterInterface;
use Base\Service\ReferrerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Google\Badge\CaptchaBadge;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE          = 'security_login';
    public const LOGOUT_ROUTE         = 'security_logout';
    public const LOGOUT_REQUEST_ROUTE = 'security_logoutRequest';

    /**
     * @var ReferrerInterface
     */
    protected $referrer;
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;
    /**
     * @var RouterInterface
     */
    protected $router;
    /**
     * @var UserRepository
     */
    protected $userRepository;

    public function __construct(ReferrerInterface $referrer, EntityManagerInterface $entityManager, RouterInterface $router, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->referrer       = $referrer;
        $this->entityManager  = $entityManager;
        $this->authorizationChecker  = $authorizationChecker;
        $this->userRepository = $entityManager->getRepository(User::class);

        $this->router           = $router;
    }

    public static function isSecurityRoute(Request|string $routeNameOrRequest)
    {
        return in_array(is_string($routeNameOrRequest) ? $routeNameOrRequest : $routeNameOrRequest->attributes->get('_route'), [
            static::LOGIN_ROUTE,
            static::LOGOUT_ROUTE,
            static::LOGOUT_REQUEST_ROUTE
        ]);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $this->referrer->setUrl($request->getUri());

        $route = $this->authorizationChecker->isGranted("EXCEPTION_ACCESS") ? RescueFormAuthenticator::LOGIN_ROUTE : static::LOGIN_ROUTE;
        return new RedirectResponse($this->router->generate($route));
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') == static::LOGIN_ROUTE && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = $request->get('security_login')["identifier"] ?? $request->get('_base_security_login')["identifier"] ?? $request->get("identifier") ?? "";
        $password   = $request->get('security_login')["password"] ?? $request->get('_base_security_login')["password"] ?? $request->get("password") ?? "";
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        $badges   = [];
        if( array_key_exists("_remember_me", $request->get('security_login') ?? $request->get('_base_security_login') ?? []) ) {

            $badges[] = new RememberMeBadge();
            if($request->get('security_login')["_remember_me"] ?? $request->get('_base_security_login')["_remember_me"])
                end($badges)->enable();
        }

        if( array_key_exists("password", $request->get('security_login') ?? $request->get('_base_security_login') ?? []) )
            $badges[] = new PasswordUpgradeBadge($password, $this->userRepository);
        if( array_key_exists("_captcha", $request->get('security_login') ?? $request->get('_base_security_login') ?? []) && class_exists(CaptchaBadge::class) )
            $badges[] = new CaptchaBadge("_captcha", $request->get('security_login')["_captcha"] ?? $request->get('_base_security_login')["_captcha"]);

        return new Passport(
            new UserBadge($identifier),
            new PasswordCredentials($password), $badges);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewall): ?Response
    {
        // Update client information
        if( ($user = $token->getUser()) ) {

            $permittedRoles = UserRole::getPermittedValues();
            foreach($user->getRoles() as $role)
                if(!in_array($role, $permittedRoles)) $user->removeRole($role);

            $user->setTimezone();
            $user->setLocale();
            $user->kick(0);

            $this->entityManager->flush();
        }

        // Check if target path provided via $_POST..
        $targetPath = $this->referrer;
        $request->getSession()->remove("_target_path");

        if ($targetPath->getUrl() && $targetPath->sameSite() && $this->authorizationChecker->isGranted("EXCEPTION_ACCESS", $targetPath))
        {
            return $this->router->redirect($targetPath->getUrl());
        }
        $defaultTargetPath = $request->getSession()->get('_security.'.$this->router->getRouteFirewall()->getName().'.target_path');
        return $this->router->redirectToRoute($this->router->getRouteName($defaultTargetPath ?? "/"));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate(static::LOGIN_ROUTE);
    }
}