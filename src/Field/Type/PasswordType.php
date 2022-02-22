<?php


namespace Base\Field\Type;

use Base\Model\AutovalidateInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordType extends AbstractType implements AutovalidateInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['always_empty'] || !$form->isSubmitted()) {
            $view->vars['value'] = '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'invalid_message' => 'The password is invalid.',
            'always_empty' => true,
            'trim' => false,
            'repeater' => null,
            'revealer' => false
        ]);

        $resolver->setNormalizer('repeater', function (Options $options, $value) {
            return $value === null ? !$options["revealer"] : $value;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return TextType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'password';
    }

    // ->setFormTypeOptions([
    //     'type' => PasswordType::class,
    //     'first_options' => [
    //         'label' => "New Password",
    //         'attr' => [
    //             "autocomplete" => "new-password"
    //         ]

    //     ],
    //     'second_options' => [
    //         'label' => "Confirm New Password",
    //         'attr' => [
    //             "autocomplete" => "new-password"
    //         ]
    //     ]
    // ]);
}
