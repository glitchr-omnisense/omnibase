<?php

namespace Base\Form\Extension;

use Base\Form\Common\FormModelInterface;
use Base\Form\FormFlowInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;

class FormTypeFlowExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'form_flow'      => true,
            'form_flow_id' => '_flow_token'
        ]);

        $resolver->setNormalizer('form_flow_id', function(Options $options, $value) {

            $formType = null;
            if(class_implements_interface($options["data_class"], FormModelInterface::class))
                $formType = camel2snake(str_rstrip(class_basename($options["data_class"]::getTypeClass()),"Type"));

            return $value == "_flow_token" ? $formType : $value;
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options["form_flow"]) return;
        if (!$builder->getForm()->isRoot()) return;
        if (!$builder->getForm() instanceof FormFlowInterface) return;

        /**
         * @var FormFlowInterface
         */
        $form = $builder->getForm();
        $step = $form->getStep($options);
        $token = $form->getToken($options);

        $builder->add($options['form_flow_name'], HiddenType::class, [
                    'mapped' => false,
                    "attr" => ["value" => $token."#". $step]
                ]);

        if(array_key_exists($step, $form->flowCallbacks))
            call_user_func($form->flowCallbacks[$step], $builder, $options);
    }
}
