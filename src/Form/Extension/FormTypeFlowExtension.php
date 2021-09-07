<?php

namespace Base\Form\Extension;

use Base\Traits\FlowFormTrait;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Form\AbstractTypeExtension;

class FormTypeFlowExtension extends AbstractTypeExtension
{
    use FlowFormTrait;

    protected $defaultEnabled;
    public function __construct(bool $defaultEnabled = false)
    {
        $this->defaultEnabled = $defaultEnabled;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'flow_form'      => $this->defaultEnabled,
            'flow_form_name' => '_flow_token'
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options["flow_form"]) return;
        if (!$builder->getForm()->isRoot()) return;

        $step = self::getStep($options);
        $token = self::getToken($options);

        $builder->add($options['flow_form_name'], HiddenType::class, [
                    'mapped' => false,
                    "attr" => ["value" => $token."#". $step]
                ]);

        $flowFormId  = $options['flow_form_id'] ?? "";
        if(isset(self::$flowCallbacks[$flowFormId]) && isset(self::$flowCallbacks[$flowFormId][$step]))
            call_user_func(self::$flowCallbacks[$flowFormId][$step], $builder, $options);
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
