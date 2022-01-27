<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\TextAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=TextAttributeRepository::class)
 * @DiscriminatorEntry( value = "text" )
 */

class TextAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-paragraph"]; } 

    public static function getType(): string { return TextType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $length;

    public function getLength():?int     { return $this->length; }
    public function setLength(?int $length)
    {
        $this->length = $length;
        return $this;
    }
}
