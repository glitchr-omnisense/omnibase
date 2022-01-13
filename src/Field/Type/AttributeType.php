<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Entity\Layout\AttributeTranslation;
use Base\Form\FormFactory;
use Base\Service\BaseService;
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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        $resolver->setDefaults([
            'class'        => null,
            'recursive'    => false,
            "multiple"     => null,
            'filter_code'  => null, 
            'sortable'     => null, 

            'allow_add'    => true,
            'allow_delete' => true,
        ]);

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-attribute.js");
        $view->vars["multiple"] = $options["multiple"];
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["allow_add"] = $options["allow_add"];
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        // $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
        // $builder->setAttribute('prototype', $prototype->getForm());

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();
            $data = $event->getData();

            $options["class"]    = $options["class"] ?? AbstractAttribute::class;
            $options["multiple"] = $this->formFactory->guessMultiple($event, $options);
            $options["sortable"] = $this->formFactory->guessSortable($event, $options);

            $form->add("choice", SelectType::class, [
                "class"               => $options["class"],
                "autocomplete_fields" => ["code" => $options["filter_code"]], 

                "multiple"            => $options["multiple"],
                
                "sortable"            => $options["sortable"],
                "dropdownCssClass"    => "field-attribute-dropdown",
                "containerCssClass"   => "field-attribute-selection"
            ]);

            if($data !== null) {
                
                $fields   = array_transforms(fn($k, $v): array => [$v->getAttributePattern()->getCode(), array_merge($v->getAttributePattern()->getOptions(), ["label" => $v->getAttributePattern()->getLabel(), "help" => $v->getAttributePattern()->getHelp(), "form_type" => $v->getAttributePattern()::getType()])], $data->toArray());
                $intlData = array_transforms(fn($k, $v): array => [$v->getAttributePattern()->getCode(), $v->getTranslations()], $data->toArray());

                if(!empty($fields)) {

                    $form->add("intl", TranslationType::class, [
                        "translation_class" => AttributeTranslation::class,
                        "multiple" => true,
                        "only_fields" => ["value"],
                        "fields" => ["value" => $fields]
                    ]);

                    $form->get("intl")->setData($intlData);
                }
            } 
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData) {
            return;
        }

        $choiceForm = iterator_to_array($forms)["choice"];
        if ($viewData instanceof PersistentCollection)
            $choiceForm->setData($viewData->map(fn($e) => $e->getAttributePattern()));
        else if(is_object($entity = $viewData))
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

        $bakData = clone $viewData;
        if($choiceMultiple) {

            $viewData->clear();
            foreach($choiceData as $data) {

                $existingData = $bakData->filter(fn(Attribute $e) => $e->getAttributePattern() === $data)->first() ?? null;
                if($existingData) $viewData->add($existingData); // I use "clone" here, to make sure collection gets refreshed
                else if ($data instanceof AbstractAttribute) $viewData->add(new ($attributeClass)($data));
                else throw new InvalidArgumentException("Invalid argument passed to attribute choice, expected class inheriting form ".AbstractAttribute::class);
            }

            if($viewData instanceof PersistentCollection) {

                $mappedBy =  $viewData->getMapping()["mappedBy"];
                $isOwningSide = $viewData->getMapping()["isOwningSide"];
                if(!$isOwningSide) {

                    foreach($viewData as $entry)
                        $this->setFieldValue($entry, $mappedBy, $viewData->getOwner());
                }
            }

        } else {
            
            if ($viewData->getAttributePattern() === $choiceData)
                $viewData->add(new ($attributeClass)($choiceData));
        }

    }

    protected static $entitySerializer = null;

    public static function getSerializer()
    {
        if(!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);

        return self::$entitySerializer;
    }

    public function setFieldValue($entity, string $property, $value)
    {
        $classMetadata = $this->classMetadataManipulator->getClassMetadata(get_class($entity));
        if($classMetadata->hasField($property))
            return $classMetadata->setFieldValue($entity, $property, $value);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->setValue($entity, $property, $value);
    }
}
