<?php

namespace Base\Security\Voter;

use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RouteVoter extends Voter
{
    const    ROUTE_IP = "ROUTE_IP";
    const  ROUTE_HOST = "ROUTE_HOST";

    public function __construct(AdvancedRouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->router       = $router;
        $this->parameterBag = $parameterBag;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return class_implements_interface($this->router, AdvancedRouterInterface::class) && $subject instanceof Route && in_array($attribute, [self::ROUTE_IP, self::ROUTE_HOST]);
    }

    protected function voteOnAttribute(string $attribute, mixed $route, TokenInterface $token): bool
    {
        $url = parse_url2(get_url());

        //
        // Select proper ballot
        switch($attribute) {

            case self::ROUTE_IP:
                return !array_key_exists("ip", $url) || !$this->parameterBag->get("base.host_restriction.ip_access");

            case self::ROUTE_HOST:

                if($route->getHost()) return true;

                $reduce = !$this->router->keepMachine() || !$this->router->keepSubdomain();
                if(array_key_exists("machine", $url) && $reduce) return false;

                $permittedSubdomains = $this->parameterBag->get("base.host_restriction.permitted_subdomains") ?? [];
                foreach($permittedSubdomains ?? [] as $permittedSubdomain)
                    if(preg_match("/".$permittedSubdomain."/", $url["subdomain"] ?? null)) return true;

                return false;

            default:
                return false;
        }
    }
}