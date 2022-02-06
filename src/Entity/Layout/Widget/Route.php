<?php

namespace Base\Entity\Layout\Widget;

use App\Enum\UserRole;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\RouteRepository;

/**
 * @ORM\Entity(repositoryClass=RouteRepository::class)
 * @DiscriminatorEntry( value = "route" )
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 */

class Route extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return $this->getRouteIcons(); } 
    public static function __iconizeStatic() : ?array { return ["fas fa-road"]; } 

    public function __toString() { return "<a href='".$this->generate()."'>".$this->getTitle()."</a>"; }
    
    public function __construct(string $title, string $route, array $routeParameters = []) 
    {
        parent::__construct($title);
        $this->route = $route;
        $this->routeParameters = $routeParameters;
    }
    
    /**
     * @ORM\Column(type="text")
     */
    protected $route;
    public function getRoute(): ?string { return $this->route; }
    public function getRouteIcons() { return $this->getIconService()->getRouteIcons($this->route); }
    public function setRoute(?string $route): self
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    protected $routeParameters;
    public function getRouteParameters(): ?array { return $this->routeParameters; }
    public function setRouteParameters(?array $routeParameters): self
    {
        $this->routeParameters = $routeParameters;
        return $this;
    }

    public function getPath() 
    {
        $route = $this->getRouter()->getRouteCollection()->get($this->route);
        return $route ? $route->getPath() : null;
    }

    public function getUrl(): ?string { return $this->generate(); }

    public function generate(): ?string
    {
        try { return $this->getRouter()->generate($this->route, $this->routeParameters ?? []); }
        catch (\Exception $e) { 

            if($this->getService()->isGranted(UserRole::EDITOR)) {

                return "@widget_route(.".$this->route.", [".implode(",", $this->routeParameters)."]) not found";
            }
        }
        
        return null;
    }
}
