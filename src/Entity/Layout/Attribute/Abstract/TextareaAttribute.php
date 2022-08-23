<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Service\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\TextareaAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=TextareaAttributeRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "textarea" )
 */

class TextareaAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-align-left"]; }

    public static function getType(): string { return TextareaType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }
}
