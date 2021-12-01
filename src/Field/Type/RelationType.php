<?php

namespace Base\Field\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RelationType extends EntityType
{
    public function getParent() : ?string
    {
        return SelectType::class;
    }
}
