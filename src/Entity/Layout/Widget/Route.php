<?php

namespace Base\Entity\Layout\Widget;

use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Exception\MissingContractException;
use Base\Entity\Layout\Widget;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\LinkableInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\RouteRepository;
use Exception;
use Base\Database\Attribute\Cache;

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

    public function __toLink(array $routeParameters = [], int $referenceType = Link::ABSOLUTE_PATH): ?string
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

    #[ORM\Column(type: "json", nullable: true)]
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
     * The path its route is declared with ("/articles/{slug}"): the Routes
     * contract (C4b), the router's route collection under Symfony.
     *
     * @return string|null
     */
    public function getPath()
    {
        return $this->routeName === null ? null : Runtime::routes()->pattern($this->routeName);
    }

    /**
     * The name of the route its address leads to (the Routes contract, C4b).
     * It handed the router's Route object to a string return type before,
     * which threw for every route found.
     */
    public function getRoute(): ?string
    {
        $url = $this->getUrl();

        return $url === null ? null : Runtime::routes()->match($url);
    }

    /**
     * Its route's icons (the Routes contract, C4b: the IconProvider's under
     * Symfony); null without a route.
     *
     * @return array|mixed|null
     */
    public function getRouteIcons()
    {
        return $this->routeName === null ? null : Runtime::routes()->icons($this->routeName);
    }

    public function getUrl(): ?string
    {
        return $this->generate();
    }

    /**
     * Its route's address through the Urls contract (Base\Contract\Link, C4b),
     * not BaseTrait's router: the same string under Symfony. A route the
     * router cannot make is null, as before; a registry with no Urls is said
     * aloud, never taken for a missing route.
     */
    public function generate(int $referenceType = Link::ABSOLUTE_PATH): ?string
    {
        try {
            return Link::to($this->routeName, $this->routeParameters ?? [], $referenceType);
        } catch (MissingContractException $e) {
            throw $e;
        } catch (Exception $e) {
        }

        return null;
    }
}
