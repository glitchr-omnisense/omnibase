<?php

namespace Base\Form\Type;

use Base\Field\Type\FileType;
use Base\Field\Type\SubmitType;
use Base\Form\Model\ContactModel;
use Google\Service\GrService;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Util\StringUtil;

/**
 *
 */
class ContactType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $defaults = ['data_class' => ContactModel::class];

        $resolver->setDefaults($defaults);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class);
        $builder->add('email', EmailType::class);
        $builder->add('subject', TextType::class, ["required" => false]);
        $builder->add('message', TextareaType::class);
        $builder->add('attachments', FileType::class, ["required" => false, "multiple" => true, "dropzone" => null]);
        $builder->add('submit', SubmitType::class, ["confirmation" => true]);
        $builder->add('reset', ResetType::class);
    }
}
