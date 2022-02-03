<?php

namespace Base\Entity\Layout\Widget;

use App\Enum\UserRole;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\LinkRepository;

/**
 * @ORM\Entity(repositoryClass=RouteRepository::class)
 * @DiscriminatorEntry( value = "route" )
 */

class Route extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return $this->getController()->__iconize(); } 
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

    public function generate(): ?string
    {
        dump("generate..");
        try { return $this->getRouter()->generate($this->route, $this->routeParameters ?? []); }
        catch (\Exception $e) { 

            if($this->getService()->isGranted(UserRole::EDITOR)) {

                return "@widget_route(.".$this->route.", [".implode(",", $this->routeParameters)."]) not found";
            }
        }
        
        return null;
    }
}
