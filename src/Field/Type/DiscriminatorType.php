<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Enum\UserRole;
use Base\Form\FormFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;


class DiscriminatorType extends AbstractType
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    
    /**
     * @var FormFactory
     */
    protected $formFactory = null;
    
    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getParent() : ?string { return SelectType::class; }
    public function getBlockPrefix(): string { return 'discriminator'; }

    public function configureOptions(OptionsResolver $resolver) {

        $resolver->setDefaults([
        ]);
    }
}
