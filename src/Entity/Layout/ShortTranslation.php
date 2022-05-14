<?php

namespace Base\Entity\Layout;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity()
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class ShortTranslation implements TranslationInterface
{
    use BaseTrait;
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $label;
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $url = null;
    public function isReacheable(): bool { return valid_response($this->url); }
    public function getUrl(): ?string { return $this->getSettings()->url($this->url); }
    public function setUrl(?string $url)
    {
        if(is_url($url)) $this->url = $url;
        else $this->url = null;

        return $this;
    }
}
