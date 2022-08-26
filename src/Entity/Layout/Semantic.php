<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\SemanticRepository;
use Base\Traits\BaseTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @ORM\Entity(repositoryClass=SemanticRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Semantic implements TranslatableInterface, IconizeInterface
{
    use BaseTrait;
    use TranslatableTrait;

    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-award"]; }

    public function __toLink(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->generate($referenceType);
    }

    public function __construct(?string $routeName, array $routeParameters = [], ?string $label = null)
    {
        $this->routeName = $routeName;
        $this->routeParameters = $routeParameters;
        $this->setLabel($label);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $routeName;
    public function getRouteName(): ?string { return $this->routeName; }
    public function setRouteName(?string $routeName): self
    {
        $this->routeName = $routeName;
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
        $route = $this->getRouter()->getRouteCollection()->get($this->routeName);
        return $route ? $route->getPath() : null;
    }

    public function getRoute(): ?string { return $this->getRouter()->getRoute($this->getUrl()); }
    public function getRouteIcons() { return $this->getIconProvider()->getRouteIcons($this->routeName); }
    public function getUrl(): ?string { return $this->generate(); }

    public function match(string $keyword) { return in_array($keyword, $this->keywords); }
    public function generate(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        try { return $this->getRouter()->generate($this->routeName, $this->routeParameters ?? [], $referenceType); }
        catch (\Exception $e) { }

        return null;
    }

    public function highlight(string $search, array $attributes = [])
    {
        foreach($this->getKeywords() as $keyword)
            $search = $this->highlightByWord($search, $keyword, $attributes);

        return $search;
    }

    public function highlightByWord(string $search, string $word, array $attributes)
    {
        if(!$this->match($word)) return $word;

        return str_replace($word, "<a href='".$this->generate()."' ".html_attributes($attributes).">".$word."</a>", $search);
    }
}
