<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget\Set\SetInterface;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Route;
use Base\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\NetworkRepository;

/**
 * @ORM\Entity(repositoryClass=NetworkRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry
 */
class Network extends Widget implements IconizeInterface, SetInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-chart-network"]; }

    public function __construct(string $title, array $routes = [])
    {
        $this->routes = new ArrayCollection($routes);
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Route::class, orphanRemoval=true, cascade={"persist"})
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     */
    protected $routes;
    public function getRoutes(): Collection { return $this->routes; }
    public function addRoute(Route $route): self
    {
        if(!$this->routes->contains($route))
            $this->routes[] = $route;

        return $this;
    }

    public function removeRoute(Route $route): self
    {
        $this->routes->removeElement($route);
        return $this;
    }
}