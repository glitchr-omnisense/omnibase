<?php

namespace Base\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\ThreadSearchModel;

class ThreadSearchbarType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ThreadSearchModel::class,
            'parent'     => null
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('parent_id', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class);
        $builder->add('generic', \Symfony\Component\Form\Extension\Core\Type\SearchType::class);
    }
}
