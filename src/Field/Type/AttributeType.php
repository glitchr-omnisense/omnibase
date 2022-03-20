<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Entity\Layout\AttributeTranslation;
use Base\Form\FormFactory;
use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\PropertyAccess\PropertyAccess;

class AttributeType extends AbstractType implements DataMapperInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    
    /**
     * @var FormFactory
     */
    protected $formFactory = null;
    
    public function getBlockPrefix(): string { return 'attribute'; }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator, BaseService $baseService)
    {
        $this->baseService   = $baseService;
        $this->formFactory   = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();  
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        $resolver->setDefaults([
            'abstract_class' => null,
            'class'          => null,

            'recursive'    => false,
            "multiple"     => null,
            'filter'       => null, 
            'filter_code'  => null,
            'sortable'     => null, 

            'allow_add'    => true,
            'allow_delete' => true,
        ]);


        $resolver->setNormalizer('class', function (Options $options, $value) {

            if($value !== null && !is_instanceof($value, Attribute::class))
                throw new InvalidArgumentException("\"class\" option is \"".$value."\", but doesn't inherit from \"".Attribute::class."\"");
            
            return $value;
        });

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-attribute.js");
        $view->vars["multiple"]     = $this->formFactory->guessMultiple($form, $options);
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["allow_add"]    = $options["allow_add"];
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        // $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
        // $builder->setAttribute('prototype', $prototype->getForm());

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();
            $data = $event->getData();

            $options["abstract_class"]    = $options["abstract_class"] ?? AbstractAttribute::class;
            $options["multiple"] = $this->formFactory->guessMultiple($event, $options);
            $options["sortable"] = $this->formFactory->guessSortable($event, $options);

            $form->add("choice", SelectType::class, [
                "class"               => $options["abstract_class"],
                "autocomplete_fields" => ["code" => $options["filter_code"]], 
                "choice_filter"       => $options["filter"],
                "multiple"            => $options["multiple"],
                "href" => false,
                "sortable"            => $options["sortable"],
                "dropdownCssClass"    => "field-attribute-dropdown",
                "containerCssClass"   => "field-attribute-selection"
            ]);

            if($data !== null) {

                if($data instanceof Collection)
                    $data = $data->toArray();
                else if(!is_array($data))
                    $data = [$data];

                $fields   = array_transforms(fn($k, $v): array => [$v->getAttributePattern()->getCode(), array_merge($v->getAttributePattern()->getOptions(), ["label" => $v->getAttributePattern()->getLabel(), "help" => $v->getAttributePattern()->getHelp(), "form_type" => $v->getAttributePattern()::getType()])], $data);
                $intlData = array_transforms(fn($k, $v): array => [$v->getAttributePattern()->getCode(), $v->getTranslations()], $data);

                if(!empty($fields)) {

                    $form->add("intl", TranslationType::class, [
                        "multiple" => $options["multiple"],
                        "autoload" => false,
                        "fields"   => ["value" => $fields],
                        "translation_class" => AttributeTranslation::class,
                    ]);

                    $form->get("intl")->setData($intlData);
                }
            } 
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData)
            return;

        $choiceForm = iterator_to_array($forms)["choice"];
        if ($viewData instanceof PersistentCollection)
            $choiceForm->setData($viewData->map(fn($e) => $e->getAttributePattern()));
        else if($viewData instanceof Attribute)
            $choiceForm->setData($viewData->getAttributePattern());
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $form = current(iterator_to_array($forms))->getParent();
        $options = $form->getConfig()->getOptions();

        $options["class"]    = $attributeClass = $options["class"] ?? $this->formFactory->guessType($form, $options) ?? Attribute::class;
        $options["multiple"] = $options["multiple"] ?? $this->formFactory->guessMultiple($form, $options);

        $choiceForm     = iterator_to_array($forms)["choice"];
        $choiceMultiple = $choiceForm->getConfig()->getOption("multiple") ?? false;
        $choiceData     = $choiceForm->getData();
 
        if($choiceMultiple !=  $options["multiple"])
            throw new \Exception("Unexpected mismatching between choices and attributes");

        if(!$choiceMultiple) {
        
            $viewData = new ($attributeClass)($choiceData);

        } else {

            $bakData = is_object($viewData) ? clone $viewData : null;

            $viewData->clear();
            foreach($choiceData as $data) {

                $existingData = $bakData->filter(fn(Attribute $e) => $e->getAttributePattern() === $data)->first() ?? null;
                if($existingData) $viewData->add($existingData);
                else if ($data instanceof AbstractAttribute) $viewData->add(new ($attributeClass)($data));
                else throw new InvalidArgumentException("Invalid argument passed to attribute choice, expected class inheriting form ".AbstractAttribute::class);
            }

            if($viewData instanceof PersistentCollection) {

                $mappedBy =  $viewData->getMapping()["mappedBy"];
                $isOwningSide = $viewData->getMapping()["isOwningSide"];
                if(!$isOwningSide) {

                    foreach($viewData as $entry)
                        $this->propertyAccessor->setValue($entry, $mappedBy, $viewData->getOwner());
                }
            }
        }
    }
}
