<?php

namespace Base\Entity\Layout\Widget;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Entity\Layout\Widget;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\LinkableInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\PageRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=PageRepository::class)
 * @DiscriminatorEntry
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @AssertBase\UniqueEntity(fields={"slug"}, groups={"new", "edit"})
 */
class Page extends Widget implements IconizeInterface, LinkableInterface
{
    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-file-alt"];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $routeParameters = array_merge($routeParameters, ["slug" => $this->getSlug()]);

        return $this->getRouter()->generate("widget_page", $routeParameters, $referenceType);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getTitle();
    }

    public function __construct(?string $title = null, ?string $slug = null)
    {
        parent::__construct($title);
        $this->setSlug($slug);
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="translations.title")
     * @AssertBase\NotBlank(groups={"new", "edit"})
     */
    protected $slug;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }
}
