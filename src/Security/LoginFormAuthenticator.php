<?php

namespace Base\Security;

use App\Entity\User;
use Base\Database\Annotation\Hashify;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'base_login';
    public const LOGOUT_ROUTE = 'base_logout';

    private $entityManager;
    private $csrfTokenManager;
    private $router;

    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function supports(Request $request)
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];

        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        if (empty($credentials))
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['username']]);
        if (!$user && property_exists(User::class, "username"))
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $credentials['username']]);

        if (!$user)
            throw new CustomUserMessageAuthenticationException('Login information provided are incorrect.');

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        if (! Hashify::isPasswordValid($user, "password", $credentials['password']) ) {

            throw new CustomUserMessageAuthenticationException('Login information provided are incorrect.');
            return false;
        }

        return true;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // Check if target path provided via $_POST..
        $targetPath = $_POST["_target_path"] ?? null;
        if ($targetPath) {

            $path = parse_url($targetPath, PHP_URL_PATH);
            if($this->router->match($path)['_route'] != self::LOGOUT_ROUTE)
                return new RedirectResponse($targetPath);
        }

        // Generic redirection rule
        return new RedirectResponse(
                    $request->getSession()->get('_security.main.target_path') ??
                    $request->getSession()->get('_security.account.target_path') ??
                    $request->headers->get('referer') ?? "/"
        );
    }

    protected function getLoginUrl()
    {
        return $this->router->generate(self::LOGIN_ROUTE);
    }
}
