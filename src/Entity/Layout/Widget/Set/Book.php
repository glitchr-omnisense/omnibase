<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\OrderColumn;
use Base\Entity\Layout\Widget\Set\SetInterface;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Page;
use Base\Service\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\BookRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=BookRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry
 */
class Book extends Widget implements IconizeInterface, SetInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-book"]; }

    public function __construct(?string $title = null, array $pages = [])
    {
        $this->pages = new ArrayCollection($pages);
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Page::class, orphanRemoval=true, cascade={"persist"})
     * @OrderColumn
     */
    protected $pages;
    public function getPages(): Collection { return $this->pages; }
    public function addPage(Page $page): self
    {
        if (!$this->pages->contains($page)) {
            $this->pages[] = $page;
        }

        return $this;
    }

    public function removePage(Page $page): self
    {
        $this->pages->removeElement($page);
        return $this;
    }
}