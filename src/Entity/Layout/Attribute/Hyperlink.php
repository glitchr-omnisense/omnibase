<?php

namespace Base\Entity\Layout\Attribute;

use Base\Database\Annotation\ColumnAlias;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Traits\TranslatableTrait;
use Base\Database\TranslatableInterface;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Entity\Layout\Attribute\Common\BaseAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\HyperlinkRepository;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */

class Hyperlink extends BaseAttribute implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public function __construct(AbstractAttribute $adapter, mixed $value = null)
    {
        parent::__construct($adapter);
        $this->setValue($value);
    }

    public        function __iconize()       : ?array { return $this->getHyperpattern() ? [$this->getHyperpattern()->getIcon()] : null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-link"]; }

    public function __toString()
    {
        $value = implode(", ", $this->getValue());
        $value = $value ? ": ".$value: "";
        return "<b>".($this->getHyperpattern() ?? "Hyperlink")." #".$this->getId()."</b> ".$value;
    }

    /**
      * @ColumnAlias(column = "adapter")
      * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
      */
    protected $hyperpattern;
    public function getHyperpattern(): HyperpatternAttribute { return $this->hyperpattern; }
    public function setHyperpattern(HyperpatternAttribute $hyperpattern): self
    {
        $this->hyperpattern = $hyperpattern;
        return $this;
    }

    public function generate(?string $locale = null) { return $this->getHyperpattern()->generate(...$this->translate($locale)->getValue()); }
    public function getLabel(): string { return $this->getHyperpattern()->getLabel(); }

    public function get(?string $locale = null): mixed { return $this->getValue($locale); }
    public function set(...$args): self { return array_key_exists("value", $args) ? $this->setValue($args["value"]) : $this; }
    public function resolve(?string $locale = null): mixed
    {
        return $this->adapter ? $this->adapter->resolve($this->translate($locale)->getValue()) : null;
    }
}
