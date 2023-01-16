<?php

namespace Base\Form\Type;

use Base\Field\Type\AvatarType;
use Base\Field\Type\PasswordType;
use Base\Form\Model\UserProfileModel;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
        $builder->add('avatar'       , AvatarType::class, ["required" => false]);
        $builder->add('email'        , EmailType::class, ["required" => false]);
        $builder->add('plainPassword', PasswordType::class, ["required" => false]);
    }
}
