<?php

namespace Base\Form\Type\Thread;

use App\Entity\Thread;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\AbstractType;
use Base\Form\Data\Thread\SearchData;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SearchbarType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SearchData::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('generic', \Symfony\Component\Form\Extension\Core\Type\SearchType::class);
    }
}
