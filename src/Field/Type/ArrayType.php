<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Service\BaseService;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArrayType extends CollectionType
{
    public function __construct(Environment $twig, TranslatorInterface $translator, ClassMetadataManipulator $classMetadataManipulator)
    {
        parent::__construct($twig, $translator);
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getBlockPrefix(): string { return 'array'; }
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            "target" => null,
            "associative" => null,
            "prototype_key" => null,
            "pattern" => null,
            "length" => 0,
            "placeholder" => []
        ]);

        $resolver->setNormalizer('target',       function(Options $options, $value) {

            if($options["pattern"] !== null && $value !== null)
            throw new \Exception("Option \"target\" cannot be set at the same time as \"pattern\"");
        });

        $resolver->setNormalizer('length',        fn(Options $options, $value) => $options["pattern"] ? $this->getNumberOfArguments($options["pattern"]) : $value);
        $resolver->setNormalizer('allow_add',     fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete',  fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('prototype_key', fn(Options $options, $value) => $value ?? ($options["associative"] ? "__prototype_key__" : null));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if($options['prototype_key'] && $options["prototype"]) {

            $prototypeOptions = $options['entry_options'];
            if (null !== $options['prototype_data'])
                $prototypeOptions['data'] = $options['prototype_data'];

            if (null !== $options['entry_required'])
                $prototypeOptions['required'] = $options['entry_required'];

            // $prototypeOptions['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->translator->trans("@fields.array.value");
            $prototype = $builder->create($options['prototype_name'], FormType::class, ["label" => false])
                ->add($options["prototype_key"], TextType::class, ["label" => false, "attr" => ["placeholder" => $this->translator->trans("@fields.array.key")]])
                ->add($options['prototype_name'], $options['entry_type'], $prototypeOptions);

            $builder->setAttribute('prototype', $prototype->getForm());

        } else {

            parent::buildForm($builder, $options);
        }
    }

    public function getNumberOfArguments($pattern):int { return preg_match_all('/\{[0-9]*\}/i', $pattern); }
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);

        $view->vars['length'] = is_string($options["length"]) ? explode(".", $options["length"]) : $options["length"];
        $view->vars["pattern"] = $options["pattern"];
        $view->vars["placeholder"] = $options["placeholder"];

        $this->twig->addHtmlContent("javascripts:body", "bundles/base/form-type-array.js");
    }
}
