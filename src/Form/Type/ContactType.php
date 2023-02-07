<?php

namespace Base\Form\Type;

use Base\Field\Type\AvatarType;
use Base\Field\Type\PasswordType;
use Base\Form\Model\ContactModel;
use Base\Form\Model\UserProfileModel;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Util\StringUtil;

class ContactType extends AbstractType
{
    public function getBlockPrefix():string { return "_base_".StringUtil::fqcnToBlockPrefix(static::class) ?: ''; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ContactModel::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name'   , TextType::class);
        $builder->add('email'  , EmailType::class);
        $builder->add('subject', TextType::class, ["required" => false]);
        $builder->add('message', TextType::class);
    }
}
