<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Factory\EntityHydrator;
use Base\Form\FormFactory;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\PropertyAccess\PropertyAccess;

class AssociationType extends AbstractType implements DataMapperInterface
{
    use BaseTrait;
    
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    
    /**
     * @var FormFactory
     */
    protected $formFactory = null;
    
    public function getBlockPrefix(): string { return 'association'; }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator, EntityHydrator $entityHydrator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityHydrator   = $entityHydrator;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();  
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        $resolver->setDefaults([
            'class'     => null,
            'form_type' => null,
            'autoload'  => true,
            'href'      => null,

            'fields' => [],
            'length' => 0,
            'excluded_fields' => [],

            'recursive' => false,
            "multiple" => false,
            'inline' => false,
            'row_inline' => false,

            'allow_add' => true,
            'allow_delete' => true,
            'allow_entity' => false,
        ]);

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['href']         = $options["href"];
        $view->vars["inline"]       = $options["inline"];
        $view->vars["multiple"]     = $options["multiple"];
        $view->vars["allow_add"]    = $options["allow_add"];
        $view->vars["inline"]       = $options["inline"];
        $view->vars["row_inline"]   = $options["row_inline"];
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars['length']       = $options["length"];
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();

            if($options["multiple"]) {

                $dataClass = $options["class"];
                unset($options["class"]);

                $collectionOptions = [
                    "data_class" => null,
                    'by_reference' => false,
                    'length' => $options["length"],
                    "entry_inline" => $options["inline"],
                    "entry_row_inline" => $options["row_inline"],
                    'entry_type' => AssociationType::class,
                    'entry_label' => function($i, $className = null, $href = null) { 
                        return $this->getTranslator()->entity($className). " #".$i; 
                    },
                    'entry_options' => array_merge($options, [
                        'allow_entity' => $options["allow_entity"],
                        'data_class'   => $dataClass,
                        'multiple'     => false,
                        'href'         => $options["href"] ?? null,
                    ]),
                ];
                
                if ($options["allow_add"] !== null) 
                    $collectionOptions['allow_add'] = $options["allow_add"];
                if ($options["allow_delete"] !== null) 
                    $collectionOptions['allow_delete'] = $options["allow_delete"];

                $form->add("collection", CollectionType::class, $collectionOptions);

            } else {

                $dataClass = $options["class"] ?? $this->formFactory->guessType($event, $options);
                if(!$dataClass)
                    throw new \RuntimeException(
                        'Unable to get "class" or compute "data_class" from form "'.$form->getName().'" or any of its parents. '.
                        'Please define "class" option in the main AssociationType you defined or make sure there is a way to guess the expected output information');

                $fields = $this->classMetadataManipulator->getFields($dataClass, $options["fields"], $options["excluded_fields"]);
                if(!$options["autoload"])
                    $fields = array_filter($fields, fn($k) => array_key_exists($k, $options["fields"]), ARRAY_FILTER_USE_KEY);

                foreach ($fields as $fieldName => $field) {

                    if($options["recursive"] && array_key_exists($form->getName(), $field))
                    $field = $field[$form->getName()];

                    $fieldType = $field['form_type'] ?? null;
                    unset($field['form_type']);

                    $isNullable = $this->classMetadataManipulator->getMapping($dataClass, $fieldName)["nullable"] ?? false;
                    if(!array_key_exists("required", $field) && $isNullable) $field['required'] = !$isNullable;

                    $fieldEntity = $field['allow_entity'] ?? $options["allow_entity"] ?? false;
                    unset($field['allow_entity']);

                    if ($fieldEntity || ($fieldType !== null && $fieldType != AssociationType::class))
                        $form->add($fieldName, $fieldType, $field);
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

        $data = $viewData;

        if ($data instanceof Collection) {

            $form = current(iterator_to_array($forms));
            $form->setData($data);

        } else if(is_object($entity = $data)) {

            $childForms = iterator_to_array($forms);
            foreach($childForms as $fieldName => $childForm) {

                $value = $this->propertyAccessor->getValue($entity, $fieldName);
                if(empty($value)) $value = null;

                $childFormType = get_class($childForm->getConfig()->getType()->getInnerType());

                switch($childFormType) {
                    case ArrayType::class:
                        if(is_serialized($value)) $value = unserialize($value);
                        else $value = $value !== null && !is_array($value) ? [$value] : $value;
                        break;

                    case IntegerType::class:
                    case NumberType::class:
                    case PercentType::class:
                        $value = intval($value);
                        break;
                }

                $childForm->setData($value);
            }
        }
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $form    = current(iterator_to_array($forms))->getParent();
        $options = current(iterator_to_array($forms))->getParent()->getConfig()->getOptions();

        $data = new ArrayCollection();
        foreach(iterator_to_array($forms) as $fieldName => $childForm)
            $data[$fieldName] = $childForm->getData();

        $options["class"]    = $options["class"] ?? $this->formFactory->guessType($form, $options);
        $options["multiple"] = $options["multiple"]   ?? $this->formFactory->guessMultiple($form, $options);

        if(!$options["multiple"] && $this->classMetadataManipulator->isEntity($options["class"])) {

            $classMetadata = $this->classMetadataManipulator->getClassMetadata($options["class"]);
            if(!$classMetadata)
                throw new \Exception("Entity \"".$options["class"]."\" not found.");

            $fieldNames  = array_values($classMetadata->getFieldNames());
            $fields = array_intersect_key($data->toArray(), array_flip($fieldNames));
            $associations = array_diff_key($data->toArray(), array_flip($fieldNames));

            $viewData = is_object($viewData) ? $viewData : $this->entityHydrator->hydrate($options["class"], []);
            foreach ($fields as $property => $value)
                try { $this->propertyAccessor->setValue($viewData, $property, $value); } catch(Exception $e) {}
            foreach($associations as $property => $value)
                try { $this->propertyAccessor->setValue($viewData, $property, $value); } catch(Exception $e) {}

        } else if($viewData instanceof PersistentCollection) {

            $mappedBy =  $viewData->getMapping()["mappedBy"];
            $fieldName = $viewData->getMapping()["fieldName"];
            $isOwningSide = $viewData->getMapping()["isOwningSide"];

            if($data->containsKey($fieldName)) {

                $child = $data[$fieldName];
                if(!$isOwningSide) {

                    foreach($viewData as $entry)
                        $this->propertyAccessor->setValue($entry, $mappedBy, null);
                }

                $viewData->clear();
                foreach($child as $entry) {

                    $viewData->add($entry);
                    if(!$isOwningSide) 
                        $this->propertyAccessor->setValue($entry, $mappedBy, $viewData->getOwner());
                }
            }

        } else if($options["multiple"]) {

            $viewData = new ArrayCollection();
            foreach(iterator_to_array($forms) as $fieldName => $childForm) {
                
                foreach($childForm as $key => $value)
                    $viewData[$key] = $value->getViewData();
            }

        } else {

            $viewData = current(iterator_to_array($forms))->getViewData();
        }
    }
}
