<?php

namespace Base\Entity\Layout\Attribute;

use Base\Database\Annotation\ColumnAlias;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Traits\TranslatableTrait;
use Base\Database\TranslatableInterface;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Entity\Layout\Attribute\Adapter\HyperpatternAdapter;
use Base\Entity\Layout\Attribute\Common\AbstractAttribute;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\HyperlinkRepository;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry
 */
class Hyperlink extends AbstractAttribute implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public function __construct(AbstractAdapter $adapter, mixed $value = null)
    {
        parent::__construct($adapter);
        $this->setValue($value);
    }

    public function __iconize(): ?array
    {
        return $this->adapter ? [$this->adapter->getIcon()] : null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-link"];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $value = implode(", ", $this->getValue());
        $value = $value ? ": " . $value : "";
        return "<b>" . ($this->adapter ?? "Hyperlink") . " #" . $this->getId() . "</b> " . $value;
    }

    /**
     * @ColumnAlias(column = "adapter")
     */
    protected $hyperpattern;

    public function getHyperpattern(): ?HyperpatternAdapter
    {
        return $this->hyperpattern;
    }

    public function setHyperpattern(?HyperpatternAdapter $hyperpattern): self
    {
        $this->hyperpattern = $hyperpattern;
        return $this;
    }

    /**
     * @param string|null $locale
     * @return mixed
     */
    public function generate(?string $locale = null)
    {
        return $this->adapter->generate(...$this->getValue($locale));
    }

    public function getLabel(): string
    {
        return $this->adapter->getLabel();
    }

    public function get(?string $locale = null): mixed
    {
        return $this->getValue($locale);
    }

    public function set(...$args): self
    {
        return array_key_exists("value", $args) ? $this->setValue($args["value"]) : $this;
    }

    public function resolve(?string $locale = null): mixed
    {
        return $this->adapter ? $this->adapter->resolve($this->getValue($locale)) : null;
    }
}
