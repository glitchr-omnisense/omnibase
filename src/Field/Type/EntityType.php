<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    protected static $entitySerializer = null;
    public static function getSerializer()
    {
        if(!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);

        return self::$entitySerializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'entity2';
    }

    public function setFieldValue($entity, string $property, $value)
    {
        $classMetadata = $this->classMetadataManipulator->getClassMetadata(get_class($entity));
        if($classMetadata->hasField($property))
            return $classMetadata->setFieldValue($entity, $property, $value);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->setValue($entity, $property, $value);
    }

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => null,
            'form_type' => null,
            'autoload' => false,
            'fields' => [],
            'excluded_fields' => [],
            'allow_recursive' => true,
            "multiple" => false,
            'allow_add' => false,
            'allow_delete' => false
        ]);

        $resolver->setNormalizer('class', function (Options $options, $value) {

            if (!$options["multiple"] && !empty($value))
                throw new \RuntimeException(sprintf('Unexpected "class" option detected (option used by CollectionType), while "multiple" is not set in "'.get_called_class().'". Please use "data_class" in this context'));

            if($options["multiple"] && $options["data_class"])
                throw new \RuntimeException("Unexpected \"data_class\" option combined with \"multiple\" option  detected.. This is not allowed in \"".get_called_class()."\"");

            return $value;
        });

        $resolver->setNormalizer('required', function (Options $options, $value) {
            if($options["multiple"]) return true;
            else return $value;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["multiple"] = $options["multiple"];
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["allow_add"] = $options["allow_add"];
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();

            // Combine allow_recursive with parent if already found in child
            foreach($options["fields"] as $fieldName => $fieldOptions) {

                if(array_key_exists("allow_recursive", $options["fields"][$fieldName])) {
                    $options["fields"][$fieldName]["allow_recursive"] 
                        = $options["fields"][$fieldName]["allow_recursive"] & $options["allow_recursive"];
                }
            }
            
            if($options["multiple"]) {

                $dataClass = $options["class"];
                unset($options["class"]);

                $form->add($form->getName(), CollectionType::class, [
                    'entry_type' => EntityType::class,
                    'entry_options' => array_merge($options, [
                        'data_class' => $dataClass,
                        'multiple' => false,
                        'label' => false
                    ]),

                    "data_class" => null,
                    'allow_add' => $options["allow_add"],
                    'allow_delete' => $options["allow_delete"],
                    'by_reference' => false,
                ]);

            } else {

                $dataClass = $this->classMetadataManipulator->getDataClass($form);
                $classMetadata = $this->classMetadataManipulator->getClassMetadata($dataClass);

                $fields = $options["fields"];
                if($options["autoload"])
                    $fields = $this->classMetadataManipulator->getFields($dataClass, $options["fields"], $options["excluded_fields"]);

                foreach ($fields as $fieldName => $field) {

                    // Fields to be excluded (in case autoload is disabled)
                    if(in_array($fieldName, $options["excluded_fields"]))
                        continue;

                    $fieldType = $field['form_type'] ?? (!empty($field['data']) ? HiddenType::class : null);
                    unset($field['form_type']);

                    $isNullable = $classMetadata->getFieldMapping($fieldName)["nullable"] ?? false;
                    if(!array_key_exists("required", $field) && $isNullable)
                        $field['required'] = false;
                    
                    $fieldRecursive = $field['allow_recursive'] ?? $options["allow_recursive"];
                    unset($field['allow_recursive']);

                    if ($fieldRecursive)
                        $form->add($fieldName, $fieldType, $field);
                }
            }
        });
    }

    public function mapDataToForms($parentData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $parentData) {
            return;
        }

        $data = $parentData;
        if ($data instanceof PersistentCollection) {

            $form = current(iterator_to_array($forms));
            $form->setData($data->toArray());

        } else if(is_object($entity = $data)) {

            $classMetadata = $this->classMetadataManipulator->getClassMetadata(get_class($entity));

            $childForms = iterator_to_array($forms);
            foreach($childForms as $fieldName => $childForm)
                $childForm->setData($classMetadata->getFieldValue($entity, $fieldName));
        }
    }

    public function mapFormsToData(\Traversable $forms, &$parentData): void
    {
        $childForms = iterator_to_array($forms);

        $form = current($childForms)->getParent();
        $dataClass = $form->getConfig()->getOption("data_class");

        $data = [];

        foreach($childForms as $fieldName => $childForm)
            $data[$fieldName] = $childForm->getData();

        if($dataClass) {

            $classMetadata = $this->classMetadataManipulator->getClassMetadata($dataClass);
            if(!$classMetadata)
                throw new \Exception("Entity \"$dataClass\" not found.");
            
            $fieldNames  = $classMetadata->getFieldNames();
            $fields = array_intersect_key($data, array_flip($fieldNames));
            $associations = array_diff_key($data, array_flip($fieldNames));

            if(!is_object($parentData) || get_class($parentData) != $dataClass)
                $parentData = self::getSerializer()->deserialize(json_encode($fieldNames), $dataClass, 'json');
            
            foreach ($fields as $property => $value)
                $this->setFieldValue($parentData, $property, $value);
            foreach($associations as $property => $value)
                $this->setFieldValue($parentData, $property, $value);

        } else if($parentData instanceof ArrayCollection || $parentData instanceof PersistentCollection) {

            $mappedBy =  $parentData->getMapping()["mappedBy"];
            $fieldName = $parentData->getMapping()["fieldName"];
            $isOwningSide = $parentData->getMapping()["isOwningSide"];

            if(array_key_exists($fieldName, $data)) {

                $child = $data[$fieldName];
                if(!$isOwningSide) {
                    foreach($parentData as $entry)
                        $this->setFieldValue($entry, $mappedBy, null);
                }

                $parentData->clear();
                foreach($child as $entry) {

                    $parentData->add($entry);
                    if(!$isOwningSide) $this->setFieldValue($entry, $mappedBy, $parentData->getOwner());
                }
            }
        }
    }
}
