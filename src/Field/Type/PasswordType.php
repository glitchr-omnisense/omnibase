<?php


namespace Base\Field\Type;

use Base\Model\AutovalidateInterface;
use Base\Service\BaseService;
use Base\Validator\Constraints\Password;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;
use Symfony\Component\Form\Extension\Core\Type\PasswordType as SymfonyPasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordType extends AbstractType implements AutovalidateInterface, DataMapperInterface
{
    public function __construct(TranslatorInterface $translator, BaseService $baseService)
    {
        $this->translator = $translator;
        $this->baseService = $baseService;
    }

    public function getBlockPrefix(): string { return 'password2'; }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["inline"]       = $options["inline"];
        $view->vars["hint"]         = $options["hint"];
        $view->vars["repeater"]     = $options["repeater"];
        $view->vars["revealer"]     = $options["revealer"];
        $view->vars["min_length"]   = $options["min_length"];
        $view->vars["min_strength"] = $options["min_strength"];
        $view->vars["autocomplete"] = $options["autocomplete"];
        $view->vars["suggestions"]  = $options["suggestions"];
        $view->vars["required"]     = $options["required"] ?? true;

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-password.js");
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'inline'            => true,
            'hint'              => true,
            'revealer'          => false,
            'repeater'          => true,
            'autocomplete'      => "on",
            'suggestions'       => true,
            'suggestions'       => false,
            "min_length"        => Password::MIN_LENGTH_FALLBACK,
            "min_strength"      => Password::MIN_STRENGTH_FALLBACK,
            'options'           => [],
            'options[repeater]' => [],
            'invalid_message'   => '@fields.password.invalid_message'
        ]);

        $resolver->setNormalizer('revealer', function (Options $options, $value) {
            return $value || !$options["repeater"];
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);

        if (!isset($options['options']['error_bubbling']))
            $options['options']['error_bubbling'] = $options['error_bubbling'];
        if (!isset($options['options']['label']))
            $options["options"]["label"] = $options["label"];
        if (!isset($options['options']['help']))
            $options["options"]["help"] = $options["help"];

        $builder->add('plain', SymfonyPasswordType::class, array_merge([
            "label" => $this->translator->trans("@fields.password.first"),
            "mapped" => true,
            "constraints" => [new Password(["min_strength" => $options["min_strength"], "min_length" => $options["min_length"]])]
        ], $options["options"]));

        if($options["repeater"]) {
            $builder->add('plain_repeater', SymfonyPasswordType::class, array_merge(["label" => $this->translator->trans("@fields.password.second"), "mapped" => true], $options['options[repeater]']));
            $builder->addViewTransformer(new ValueToDuplicatesTransformer(["plain", "plain_repeater"]));
        }
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        $plainPasswordType = iterator_to_array($forms)["plain"];
        if($plainPasswordType) $plainPasswordType->setData($viewData);
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $plainPasswordType = iterator_to_array($forms)["plain"];
        $viewData = $plainPasswordType->getViewData();
    }
}
