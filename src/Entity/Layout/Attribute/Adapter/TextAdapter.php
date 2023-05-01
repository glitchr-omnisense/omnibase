<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\TextAdapterRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=TextAdapterRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "text" )
 */
class TextAdapter extends AbstractAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-paragraph"];
    }

    public static function getType(): string
    {
        return TextType::class;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function resolve(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $length;

    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * @param int|null $length
     * @return $this
     */
    /**
     * @param int|null $length
     * @return $this
     */
    public function setLength(?int $length)
    {
        $this->length = $length;
        return $this;
    }
}
