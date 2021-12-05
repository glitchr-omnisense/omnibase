<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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

class EntityType extends AbstractType implements DataMapperInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    
    public function getBlockPrefix(): string { return 'entity2'; }

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        $resolver->setDefaults([
            'class' => null,
            'form_type' => null,
            'autoload' => true,

            'fields' => [],
            'only_fields' => [],
            'excluded_fields' => [],
            
            'recursive' => false,
            "multiple" => false,
            'inline' => false,
            'row_inline' => false,

            'allow_add' => true,
            'allow_delete' => true,
        ]);

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });

        $resolver->setNormalizer('autoload', function (Options $options, $value) {
            return $options["fields"] ? false : $value;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["multiple"] = $options["multiple"];
        $view->vars["inline"] = $options["inline"];
        $view->vars["row_inline"] = $options["row_inline"];
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["allow_add"] = $options["allow_add"];
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
                    "entry_inline" => $options["inline"],
                    "entry_row_inline" => $options["row_inline"],
                    'entry_type' => EntityType::class,
                    'entry_options' => array_merge($options, [
                        'data_class' => $dataClass,
                        'multiple' => false,
                        'label' => false
                    ]),
                ];
                
                if ($options["allow_add"] !== null) 
                    $collectionOptions['allow_add'] = $options["allow_add"];
                if ($options["allow_delete"] !== null) 
                    $collectionOptions['allow_delete'] = $options["allow_delete"];

                $form->add($form->getName(), CollectionType::class, $collectionOptions);

            } else {

                $dataClass = $options["class"] ?? $this->classMetadataManipulator->getDataClass($form);
                if(!$dataClass)
                    throw new \RuntimeException(
                        'Unable to get "class" or compute "data_class" from form "'.$form->getName().'" or any of its parents. '.
                        'Please define "class" option in the main EntityType you defined or make sure there is a way to guess the expected output information');

                $classMetadata = $this->classMetadataManipulator->getClassMetadata($dataClass);
                
                $fields = $options["fields"];
                if($options["autoload"])
                    $fields = $this->classMetadataManipulator->getFields($dataClass, $options["fields"], $options["excluded_fields"]);

                foreach ($fields as $fieldName => $field) {

                    // Fields to be excluded (in case autoload is disabled)
                    if($options["only_fields"] && !in_array($fieldName, $options["only_fields"]))
                        continue;
                    if(in_array($fieldName, $options["excluded_fields"]))
                        continue;

                    if($options["recursive"] && array_key_exists($form->getName(), $field))
                        $field = $field[$form->getName()];

                    $fieldType = $field['form_type'] ?? (!empty($field['data']) ? HiddenType::class : null);
                    unset($field['form_type']);

                    $isNullable = $classMetadata->getFieldMapping($fieldName)["nullable"] ?? false;
                    if(!array_key_exists("required", $field) && $isNullable)
                        $field['required'] = false;
                    
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

            $classMetadata = $this->classMetadataManipulator->getClassMetadata(get_class($entity));

            $childForms = iterator_to_array($forms);
            foreach($childForms as $fieldName => $childForm)
                $childForm->setData($classMetadata->getFieldValue($entity, $fieldName));
        }
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $form = current(iterator_to_array($forms))->getParent();

        $data = new ArrayCollection();
        foreach(iterator_to_array($forms) as $fieldName => $childForm)
            $data[$fieldName] = $childForm->getData();
    
        $dataClass = $form->getConfig()->getOption("data_class");
        if($dataClass) {

            $classMetadata = $this->classMetadataManipulator->getClassMetadata($dataClass);
            if(!$classMetadata)
                throw new \Exception("Entity \"$dataClass\" not found.");
            
            $fieldNames  = $classMetadata->getFieldNames();
            $fields = array_intersect_key($data->toArray(), array_flip($fieldNames));
            $associations = array_diff_key($data->toArray(), array_flip($fieldNames));

            if(!is_object($viewData) || get_class($viewData) != $dataClass)
                $viewData = self::getSerializer()->deserialize(json_encode($fieldNames), $dataClass, 'json');
            
            foreach ($fields as $property => $value)
                $this->setFieldValue($viewData, $property, $value);
            foreach($associations as $property => $value)
                $this->setFieldValue($viewData, $property, $value);

        } else if($viewData instanceof ArrayCollection) {
  
            foreach(iterator_to_array($forms) as $fieldName => $childForm) {

                foreach($childForm as $key => $value)
                    $viewData[$key] = $value->getViewData();
            }

        } else if($viewData instanceof PersistentCollection) {

            $mappedBy =  $viewData->getMapping()["mappedBy"];
            $fieldName = $viewData->getMapping()["fieldName"];
            $isOwningSide = $viewData->getMapping()["isOwningSide"];

            if($data->containsKey($fieldName)) {

                $child = $data[$fieldName];
                if(!$isOwningSide) {
                    foreach($viewData as $entry)
                        $this->setFieldValue($entry, $mappedBy, null);
                }

                $viewData->clear();
                foreach($child as $entry) {

                    $viewData->add($entry);
                    if(!$isOwningSide) $this->setFieldValue($entry, $mappedBy, $viewData->getOwner());
                }
            }
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
