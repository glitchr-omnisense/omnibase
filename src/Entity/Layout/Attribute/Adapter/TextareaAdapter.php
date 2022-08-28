<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Base\Database\Annotation\DiscriminatorEntry;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\TextareaAdapterRepository;

/**
 * @ORM\Entity(repositoryClass=TextareaAdapterRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "textarea" )
 */

class TextareaAdapter extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return ["fas fa-align-left"]; }

    public static function getType(): string { return TextareaType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }
}
