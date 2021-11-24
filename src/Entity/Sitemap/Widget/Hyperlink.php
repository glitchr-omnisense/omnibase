<?php

namespace Base\Entity\Sitemap\Widget;


use Symfony\Component\Validator\Constraints as Assert;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Sitemap\Widget;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Widget\HyperlinkRepository;
/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry( value = "hyperlink" )
 */

class Hyperlink extends Widget 
{
    /**
     * @ORM\Column(type="text")
     * @Assert\Url()
     */
    protected $url;

    public function getUrl(): string { return $this->url; }
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }
}