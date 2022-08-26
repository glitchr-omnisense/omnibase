<?php

namespace Base\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\User\SearchModel;

class UserSearchType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SearchModel::class
        ]);
    }
}
