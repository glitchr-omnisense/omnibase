<?php

namespace Base\Entity\Layout\Widget;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\LinkableInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\RouteRepository;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Base\Database\Annotation\Cache;

#[ORM\Entity(repositoryClass: RouteRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry]
class Route extends Widget implements IconizeInterface, LinkableInterface
{
    public function __iconize(): ?array
    {
        return $this->getRouteIcons();
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-road"];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $this->setRouteParameters(array_merge($this->getRouteParameters(), $routeParameters));
        return $this->generate($referenceType);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getTitle() ?? "";
    }

    public function __construct(?string $title = null, ?string $routeName = null, array $routeParameters = [])
    {
        parent::__construct($title);

        $this->routeName = $routeName;
        $this->routeParameters = $routeParameters;
    }

    #[ORM\Column(type: "text")]
    protected $routeName;

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): self
    {
        $this->routeName = $routeName;
        return $this;
    }

    #[ORM\Column(type: "array", nullable: true)]
    protected $routeParameters;

    public function getRouteParameters(): ?array
    {
        return $this->routeParameters;
    }

    public function setRouteParameters(?array $routeParameters): self
    {
        $this->routeParameters = $routeParameters;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        $route = $this->getRouter()->getRouteCollection()->get($this->routeName);
        return $route?->getPath();
    }

    public function getRoute(): ?string
    {
        return $this->getRouter()->getRoute($this->getUrl());
    }

    /**
     * @return array|mixed|null
     */
    public function getRouteIcons()
    {
        return $this->getIconProvider()->getRouteIcons($this->routeName);
    }

    public function getUrl(): ?string
    {
        return $this->generate();
    }

    public function generate(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        try {
            return $this->getRouter()->generate($this->routeName, $this->routeParameters ?? [], $referenceType);
        } catch (Exception $e) {
        }

        return null;
    }
}
