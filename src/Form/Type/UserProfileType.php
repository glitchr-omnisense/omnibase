<?php

namespace Base\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Util\StringUtil;

class UserProfileType extends AbstractType
{
    public function getBlockPrefix():string { return "_base".StringUtil::fqcnToBlockPrefix(static::class) ?: ''; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UserProfileModel::class
        ]);
    }
}
