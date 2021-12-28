<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Form\FormFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class AssociationFileType extends AbstractType implements DataMapperInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    
    /**
     * @var FormFactory
     */
    protected $formFactory = null;
    
    public function getBlockPrefix(): string { return 'associationfile'; }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => null,
            'form_type' => FileType::class,

            'entity_file' => null,
            'entity_values'     => [],

            "multiple"     => null,
            'allow_delete' => true,
            'href' => null,

            'max_filesize' => null,
            'max_files'    => null,
            'mime_types'   => null
        ]);

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });

        $resolver->setNormalizer('form_type', function (Options $options, $value) {

            if(is_parent_of($value, FileType::class)) return $value;
            throw new \Exception("Option \"form_type\" must inherit from FileType");
        });

        $resolver->setNormalizer('class', function (Options $options, $value) {

            if($value === null) throw new \Exception("Option \"class\" must be defined");
            return $value;
        });

        $resolver->setNormalizer('entity_file', function (Options $options, $value) {

            if($value === null) throw new \Exception("Option \"entity_file\" must be pointing to the file field");
            return $value;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["multiple"]     = $options["multiple"];
        $view->vars["allow_delete"] = $options["allow_delete"];

        $data = $form->getData();
        $view->vars["entityId"] = json_encode(array_key_transforms(function($k,$e) use ($options):array {

            $path = PropertyAccess::createPropertyAccessor()->getValue($e, $options["entity_file"]);
            return [basename($path), $e->getId()];

        }, ($data instanceof Collection) ? $data->toArray() : []));
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $data = $event->getData();
            
            $options["class"]    = $options["class"] ?? $this->formFactory->guessType($event, $options);
            $options["multiple"] = $options["multiple"]   ?? $this->formFactory->guessMultiple($event, $options);

            $fieldName = $options["entity_file"];
            $form->add($fieldName, $options["form_type"], [
                'class'        => $options["class"],
                'allow_delete' => $options["allow_delete"] ?? true,
                'multiple'     => $options["multiple"] ?? false,
                'href'         => $options["href"] ?? null,

                'max_filesize' => $options["max_filesize"],
                'max_files'    => $options["max_files"],
                'mime_types'   => $options["mime_types"]
            ]);

            $files = array_map(fn($e) => Uploader::getPublic($e, $fieldName), $data->toArray());
            $form->get($fieldName)->setData($files);
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void { }
    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $parentForm = current(iterator_to_array($forms))->getParent();
        $options = $parentForm->getConfig()->getOptions();

        $options["data_class"] = $options["data_class"] ?? $this->formFactory->guessType($parentForm, $options);
        $options["multiple"]   = $options["multiple"]   ?? $this->formFactory->guessMultiple($parentForm, $options);

        $classMetadata = $this->classMetadataManipulator->getClassMetadata($options["data_class"]);
        if(!$classMetadata)
            throw new \Exception("Entity \"".$options['data_class']."\" not found.");

        $newData = [];
        $data = new ArrayCollection();

        $fieldName = $options["entity_file"];
        $form = iterator_to_array($forms)[$fieldName] ?? null;
        if($form) {

            $files = $form->getData() ?? [];
            foreach($files as $key => $file) {

                if($file instanceof File) {

                    $entityValues = is_callable($options["entity_values"]) ? null : $options["entity_values"];
                    $entity = self::getSerializer()->deserialize(json_encode($entityValues ?? []), $options["data_class"], 'json');

                    $fieldName = $classMetadata->getFieldName($fieldName);
                    $classMetadata->setFieldValue($entity, $fieldName, $file);
                    $newData[] = $entity;

                } else {

                    $filteredData = $viewData->filter(fn($e) => $classMetadata->getFieldValue($e, $fieldName) == $file);
                    $entity = $filteredData instanceof ArrayCollection ? $filteredData->first() : null;
                }

                $data[$key] = $entity;
            }
        }

        if($options["multiple"]) {

            if($viewData instanceof PersistentCollection) {

                $mappedBy =  $viewData->getMapping()["mappedBy"];
                $fieldName = $viewData->getMapping()["fieldName"];
                $isOwningSide = $viewData->getMapping()["isOwningSide"];
                
                if(!$isOwningSide) {

                    foreach($viewData as $entry)
                        $this->setFieldValue($entry, $mappedBy, null);
                }

                $viewData->clear();
                foreach($data as $entry) {

                    if(!$isOwningSide) 
                        $this->setFieldValue($entry, $mappedBy, $viewData->getOwner());
                }

            } else $viewData = $data;

            foreach($viewData as $key => $data)
                $viewData[$key] = is_callable($options["entity_values"]) ? $options["entity_values"]($data) : $data;

        } else $viewData = $data->first();
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

    public function getFieldValue($entity, string $property)
    {
        $classMetadata = $this->classMetadataManipulator->getClassMetadata(get_class($entity));
        if($classMetadata->hasField($property))
            return $classMetadata->getFieldValue($entity, $property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->getValue($entity, $property);
    }
}
