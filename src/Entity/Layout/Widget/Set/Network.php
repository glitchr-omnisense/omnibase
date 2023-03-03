<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\OrderColumn;
use Base\Entity\Layout\Widget\Set\SetInterface;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Route;
use Base\Service\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\NetworkRepository;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=NetworkRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry
 */
class Network extends Widget implements IconizeInterface, SetInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-network-wired"]; }

    public function __construct(?string $title = null, array $routes = [])
    {
        $this->routes = new ArrayCollection($routes);
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Route::class, orphanRemoval=true, cascade={"persist"})
     * @OrderColumn
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