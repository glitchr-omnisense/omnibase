<?php

namespace Base\Entity\Layout;

use Base\Database\Entity\Extension\TranslationInterface;
use Base\Database\Entity\Extension\TranslationTrait;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TooltipIntl implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    protected $title;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;

        return $this;
    }
    
    #[ORM\Column(type:"text", nullable:true)]
    protected $content;

    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content)
    {
        $this->content = $content;

        return $this;
    }
}
