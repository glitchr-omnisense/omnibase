<?php

namespace Base\Security\Voter;

use Base\Routing\RouterInterface;
use Base\Security\LoginFormAuthenticator;
use Base\Security\RescueFormAuthenticator;
use Base\Service\LocalizerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RouteVoter extends Voter
{
    public const    VALIDATE_IP = "VALIDATE_IP";
    public const  VALIDATE_HOST = "VALIDATE_HOST";
    public const  VALIDATE_PATH = "VALIDATE_PATH";

    /**
     * @var Router
     * */
    protected $router;
    /**
     * @var ParameterBag
     * */
    protected $parameterBag;
    /**
     * @var Localizer
     * */
    protected $localizer;

    protected ?array $permittedHosts;
    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, LocalizerInterface $localizer)
    {
        $this->router       = $router;
        $this->parameterBag = $parameterBag;
        $this->localizer = $localizer;

        $this->permittedHosts   = array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localizer->getLocale());
        $this->permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localizer->getLocaleLang());
        $this->permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localizer->getDefaultLocale());
        $this->permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localizer->getDefaultLocaleLang());
        $this->permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", null) ?? [];

        $this->permittedHosts = array_transforms(fn ($k, $a): ?array => $a["env"] == $this->router->getEnvironment() ? [$k, $a["regex"]] : null, $this->permittedHosts) ?? [];
        if (!$this->router->keepMachine() && !$this->router->keepSubdomain() && !$this->router->keepDomain()) {
            $this->permittedHosts[] = "^$";
        }
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return class_implements_interface($this->router, RouterInterface::class) && $subject instanceof Route && in_array($attribute, [self::VALIDATE_IP, self::VALIDATE_PATH, self::VALIDATE_HOST]);
    }

    protected $pool = [];
    protected function voteOnAttribute(string $attribute, mixed $route, TokenInterface $token): bool
    {
        $url = get_url();
        $pool[$attribute] ??= [];
        if (array_key_exists($url, $pool[$attribute])) {
            return $pool[$attribute][$url];
        }

        //
        // Select proper ballot
        switch ($attribute) {
            case self::VALIDATE_IP:

                $parse = parse_url2($url);
                $pool[$attribute][$url] = !array_key_exists("ip", $parse) || $this->parameterBag->get("base.access_restriction.ip_access");
                break;

            case self::VALIDATE_PATH:

                $urlButQuery = explode("?", $url)[0] ?? "";
                $format = str_ends_with($urlButQuery, "/") ? SANITIZE_URL_KEEPSLASH : SANITIZE_URL_STANDARD;

                $pool[$attribute][$url] = $url == sanitize_url($url, $format) || $url == sanitize_url($url);
                break;

            case self::VALIDATE_HOST:

                $hostFallback = $this->router->getHostFallback();
                if (!$hostFallback) {
                    $pool[$attribute][$url] = true;
                    break;
                }

                if (!$route->getHost() && $this->router->getHost() != $hostFallback) {
                    $pool[$attribute][$url] = false;
                    break;
                }

                $routeName = $this->router->getRouteName();
                if (LoginFormAuthenticator::isSecurityRoute($routeName)) {
                    $pool[$attribute][$url] = true;
                    break;
                }
                if (RescueFormAuthenticator::isSecurityRoute($routeName)) {
                    $pool[$attribute][$url] = true;
                    break;
                }

                $parse = parse_url2($url);
                $allowedHost = empty($this->permittedHosts) || !$this->router->keepDomain();
                foreach ($this->permittedHosts as $permittedHost) {
                    $allowedHost |= preg_match("/" . $permittedHost . "/", $parse["host"] ?? null);
                }

                if (!$allowedHost) {
                    $pool[$attribute][$url] = false;
                    break;
                }

                if (array_key_exists("machine", $parse) && !$this->router->keepMachine()) {
                    $pool[$attribute][$url] = false;
                    break;
                }

                if (array_key_exists("subdomain", $parse) && !$this->router->keepSubdomain()) {
                    $pool[$attribute][$url] = !array_key_exists("machine", $parse) && $this->router->keepMachine();
                    break;
                }

                $pool[$attribute][$url] = $allowedHost;
                break;

            default:
                $pool[$attribute][$url] = false;
        }

        return $pool[$attribute][$url];
    }
}
