<?php

namespace Base\Field\Type;

use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StateType extends AbstractType
{
    public function getParent() : ?string { return SelectType::class; }
    public function getBlockPrefix(): string { return 'state'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class'      => ThreadState::class,
            'empty_data' => ThreadState::DRAFT
        ]);
    }
}
