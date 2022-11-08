<?php

namespace Base\Form\Type;

use Base\Field\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Util\StringUtil;

class UserProfileType extends AbstractType
{
    public function getBlockPrefix():string { return "_base_".StringUtil::fqcnToBlockPrefix(static::class) ?: ''; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UserProfileModel::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('avatar'  , SearchType::class, ["required" => false]);
        $builder->add('email', SearchType::class, ["required" => false]);
        $builder->add('plainPassword', PasswordType::class, ["required" => false]);
        
        // $builder->add('content', SelectType::class, [ // Webpack required
        //     "required" => false,
        //     "class" => Destination::class
        // ]);
    }
}
