<?php

namespace Base\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\ThreadSearchModel;

class ThreadSearchType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ThreadSearchModel::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('generic', \Symfony\Component\Form\Extension\Core\Type\SearchType::class);
        $builder->add('content', \Symfony\Component\Form\Extension\Core\Type\SearchType::class);
        $builder->add('title'  , \Symfony\Component\Form\Extension\Core\Type\SearchType::class);
        $builder->add('excerpt', \Symfony\Component\Form\Extension\Core\Type\SearchType::class);
    }
}
