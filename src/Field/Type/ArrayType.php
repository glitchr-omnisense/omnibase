<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Service\BaseService;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArrayType extends CollectionType
{
    public function __construct(BaseService $baseService, ClassMetadataManipulator $classMetadataManipulator)
    {
        parent::__construct($baseService);
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

            $prototypeOptions['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->baseService->getTranslator()->trans("@fields.array.value");
            $prototype = $builder->create($options['prototype_name'], FormType::class, ["label" => false])
                ->add($options["prototype_key"], TextType::class, ["label" => false, "attr" => ["placeholder" => $this->baseService->getTranslator()->trans("@fields.array.key")]])
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

        $target = $form->getParent();
        $targetPath = $options["pattern"] ? explode(".", $options["pattern"]) : [] ;

        dump(    $targetData = $this->classMetadataManipulator->getFieldValue($target->getData(), $options["pattern"]));
        foreach($targetPath as $path) {

            if(!$target->has($path)) break;

            $target = $target->get($path);
            $targetType = $target->getConfig()->getType()->getInnerType();

            if($targetType instanceof TranslationType) {

                $availableLocales = array_keys($target->all());
                $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0]);
                $target = $target->get($locale);
            }

            array_shift($targetPath);
        }

        $targetData = $target->getData();
        $targetPath = implode(".", $targetPath);

        if(!empty($targetPath)) {

            if($targetData === null || !is_object($target))
                throw new \Exception("Failed to find a property path \"$targetPath\" to pattern with data \"".get_class($targetData)."\"");

            $targetData = $this->classMetadataManipulator->getFieldValue($targetData, $targetPath);
        }

        $view->vars["pattern"] = $targetData;
        $view->vars["placeholder"] = $options["placeholder"];

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-array.js");
    }
}
