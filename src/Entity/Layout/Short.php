<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Service\Model\IconizeInterface;

use Base\Annotations\Annotation\Slugify;
use Base\Service\Model\LinkableInterface;

use Base\Annotations\Annotation\Randomize;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\ShortRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Base\Database\Annotation\Cache;

#[ORM\Entity(repositoryClass: ShortRepository::class)]
#[Cache(usage:"NONSTRICT_READ_WRITE", associations:"ALL")]
class Short implements TranslatableInterface, IconizeInterface, LinkableInterface
{
    use TranslatableTrait;

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-compress-alt fa-rotate-45"];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $slug = $this->getSlug();
        if (!$slug) {
            return null;
        }

        $routeParameters = array_merge($routeParameters, ["slug" => $slug]);

        return $this->getRouter()->generate("short_redirect", $routeParameters, $referenceType);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getLabel() ?? $this->getUrl() ?? "";
    }

    public function __construct(string $url = "", ?string $label = null)
    {
        $this->setUrl($url);
        $this->setLabel($label);
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(type:"string", length:255)]
    #[Randomize]
    #[Slugify(separator:"-")]
    protected $slug;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * @param string|null $slug
     * @return $this
     */
    public function setSlug(?string $slug)
    {
        $this->slug = $slug;
        return $this;
    }
}
