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
        if (!$this->router->keepMachine() && !$this->router->keepSubdomain() && $this->router->keepDomain()) {
            $this->permittedHosts[] = "^$";
        } // Special case if both subdomain and machine are unallowed
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

                $pool[$attribute][$url] = array_key_exists("ip", $parse);
                break;

            case self::VALIDATE_HOST:

                $hostFallback = $this->router->getHostFallback();
                $parse = parse_url2($url);
                $ipAddress = $parse["ip"] ?? null;
                if($ipAddress) {

                    if (!$this->parameterBag->get("base.router.ip_access")) {
                        $pool[$attribute][$url] = false;
                        break;
                    }

                } else {

                    if (array_key_exists("machine", $parse) && !$this->router->keepMachine()) {
                        $pool[$attribute][$url] = false;
                        break;
                    }

                    if (array_key_exists("subdomain", $parse) && !$this->router->keepSubdomain()) {
                        $pool[$attribute][$url] = !array_key_exists("machine", $parse) && $this->router->keepMachine();
                        break;
                    }
                }

                $allowedHost = empty($this->permittedHosts);
                foreach ($this->permittedHosts as $permittedHost) {
                    $allowedHost |= preg_match("/" . $permittedHost . "/", $parse["host"] ?? null);
                }

                $pool[$attribute][$url] = $allowedHost;
                break;

            case self::VALIDATE_PATH:

                $urlButQuery = explode("?", $url)[0] ?? "";
                $format = str_ends_with($urlButQuery, "/") ? SANITIZE_URL_KEEPSLASH : SANITIZE_URL_STANDARD;

                $pool[$attribute][$url] = $url == sanitize_url($url, $format) || $url == sanitize_url($url);
                break;

            default:
                $pool[$attribute][$url] = false;
        }

        return $pool[$attribute][$url];
    }
}
