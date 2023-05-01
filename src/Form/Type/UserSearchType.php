<?php

namespace Base\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\UserSearchModel;

/**
 *
 */
class UserSearchType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UserSearchModel::class
        ]);
    }
}
