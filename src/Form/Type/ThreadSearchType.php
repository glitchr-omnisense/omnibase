<?php

namespace Base\Form\Type;

use App\Entity\Destination\Destination;
use Base\Field\Type\SelectType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\ThreadSearchModel;
use Symfony\Component\Form\Extension\Core\Type\SearchType;

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
        $builder->add('generic', SearchType::class, ["required" => false]);
        $builder->add('content', SearchType::class, ["required" => false]);
        $builder->add('title'  , SearchType::class, ["required" => false]);
        $builder->add('excerpt', SearchType::class, ["required" => false]);
        // $builder->add('content', SelectType::class, [ // Webpack required
        //     "required" => false,
        //     "class" => Destination::class
        // ]);
    }
}
