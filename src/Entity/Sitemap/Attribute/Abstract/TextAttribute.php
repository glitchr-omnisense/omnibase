<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\Abstract\TextAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=TextAttributeRepository::class)
 * @DiscriminatorEntry( value = "text" )
 */

class TextAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __staticIconize() : ?array { return ["fas fa-paragraph"]; } 

    public static function getType(): string { return TextType::class; }
    public static function getOptions(): array { return []; }

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

    public function getFormattedValue($value): mixed
    {
        return $value;
    }
}
