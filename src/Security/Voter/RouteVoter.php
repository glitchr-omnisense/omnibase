<?php

namespace Base\Security\Voter;

use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RouteVoter extends Voter
{
    const    VALIDATE_IP = "VALIDATE_IP";
    const  VALIDATE_HOST = "VALIDATE_HOST";

    public function __construct(AdvancedRouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->router       = $router;
        $this->parameterBag = $parameterBag;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return class_implements_interface($this->router, AdvancedRouterInterface::class) && $subject instanceof Route && in_array($attribute, [self::VALIDATE_IP, self::VALIDATE_HOST]);
    }

    protected function voteOnAttribute(string $attribute, mixed $route, TokenInterface $token): bool
    {
        $url = parse_url2();

        //
        // Select proper ballot
        switch($attribute) {

            case self::VALIDATE_IP:
               return !array_key_exists("ip", $url) || $this->parameterBag->get("base.host_restriction.ip_access");

            case self::VALIDATE_HOST:

                if($route->getHost()) return true;

                if(array_key_exists("machine",   $url) && !$this->router->keepMachine()  ) return false;
                if(array_key_exists("subdomain", $url)) {

                    if(!$this->router->keepSubdomain())
                        return !array_key_exists("machine",   $url) && $this->router->keepMachine();

                    $permittedSubdomains = $this->parameterBag->get("base.host_restriction.permitted_subdomains") ?? [];
                    foreach($permittedSubdomains ?? [] as $permittedSubdomain)
                        if(preg_match("/".$permittedSubdomain."/", $url["subdomain"] ?? null)) return true;
                }

                return false;

            default:
                return false;
        }
    }
}