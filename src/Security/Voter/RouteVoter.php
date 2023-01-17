<?php

namespace Base\Security\Voter;

use Base\Routing\RouterInterface;
use Base\Security\LoginFormAuthenticator;
use Base\Security\RescueFormAuthenticator;
use Base\Service\LocaleProviderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RouteVoter extends Voter
{
    const    VALIDATE_IP = "VALIDATE_IP";
    const  VALIDATE_HOST = "VALIDATE_HOST";
    const  VALIDATE_PATH = "VALIDATE_PATH";

    /** 
     * @var Router 
     * */
    protected $router;
    /** 
     * @var ParameterBag 
     * */
    protected $parameterBag;
    /** 
     * @var LocaleProvider 
     * */
    protected $localeProvider;
    
    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, LocaleProviderInterface $localeProvider)
    {
        $this->router       = $router;
        $this->parameterBag = $parameterBag;
        $this->localeProvider = $localeProvider;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return class_implements_interface($this->router, RouterInterface::class) && $subject instanceof Route && in_array($attribute, [self::VALIDATE_IP, self::VALIDATE_PATH, self::VALIDATE_HOST]);
    }

    protected function voteOnAttribute(string $attribute, mixed $route, TokenInterface $token): bool
    {
        $url = get_url();

        //
        // Select proper ballot
        switch($attribute) {

            case self::VALIDATE_IP:
                $parse = parse_url2($url);

                return !array_key_exists("ip", $parse) || $this->parameterBag->get("base.access_restriction.ip_access");

            case self::VALIDATE_PATH:

                $urlButQuery   = explode("?", $url)[0] ?? "";
                $format = str_ends_with($urlButQuery, "/") ? SANITIZE_URL_KEEPSLASH : SANITIZE_URL_STANDARD;
                return $url == sanitize_url($url, $format) || $url == sanitize_url($url);

            case self::VALIDATE_HOST:

                $hostFallback = $this->router->getHostFallback();
                if(!$hostFallback) return true;

                if(!$route->getHost() && $this->router->getHost() != $hostFallback)
                    return false;
    
                $permittedHosts   = array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localeProvider->getLocale());
                $permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localeProvider->getLang());
                $permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localeProvider->getDefaultLocale());
                $permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", $this->localeProvider->getDefaultLang());
                $permittedHosts ??= array_search_by($this->parameterBag->get("base.router.permitted_hosts"), "locale", null) ?? [];
                $permittedHosts = array_transforms(fn($k, $a): ?array => $a["env"] == $this->router->getEnvironment() ? [$k, $a["regex"]] : null, $permittedHosts);

                if(!$this->router->keepMachine() && !$this->router->keepSubdomain())
                    $permittedHosts[] = "^$"; // Special case if both subdomain and machine are unallowed

                $parse = parse_url2($url);

                $allowedHost = empty($permittedHosts);
                foreach($permittedHosts as $permittedHost)
                    $allowedHost |= preg_match("/".$permittedHost."/", $parse["host"] ?? null);

                $routeName = $this->router->getRouteName();
                if(LoginFormAuthenticator::isSecurityRoute($routeName))
                    return true;
                if(RescueFormAuthenticator::isSecurityRoute($routeName))
                    return true;

                if(!$allowedHost) return false;

                if(array_key_exists("machine",   $parse) && !$this->router->keepMachine()  ) return false;
                if(array_key_exists("subdomain", $parse) && !$this->router->keepSubdomain())
                    return !array_key_exists("machine",   $parse) && $this->router->keepMachine();

                return $allowedHost;

            default:
                return false;
        }
    }
}
