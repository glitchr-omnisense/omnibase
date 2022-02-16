<?php

namespace Base\Field\Type;

use App\Enum\UserRole;
use Base\Annotations\Annotation\Uploader;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Factory\EntityHydrator;
use Base\Form\FormFactory;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
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

class AssociationFileType extends AbstractType implements DataMapperInterface
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
    
    public function getBlockPrefix(): string { return 'associationfile'; }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator, EntityHydrator $entityHydrator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityHydrator = $entityHydrator;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor(); 
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => null,
            'form_type' => FileType::class,

            'entity_file'    => null,
            'entity_inherit' => false,
            'entity_data'    => [],

            "multiple"     => null,
            'allow_delete' => true,
            'href'         => null,

            'max_filesize' => null,
            'max_files'    => null,
            'mime_types'   => null,
            'sortable'     => null
        ]);

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });

        $resolver->setNormalizer('form_type', function (Options $options, $value) {

            if(is_a($value, FileType::class, true)) return $value;
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
        // 
        // Set controller url
        $options["class"]    = $this->formFactory->guessType($form, $options);
        $options["multiple"] = $this->formFactory->guessMultiple($form, $options);
        $options["sortable"] = $this->formFactory->guessSortable($form, $options);
        $isNullable = $this->classMetadataManipulator->getMapping($options["class"], $form->getName())["nullable"] ?? false;
        
        $crudController = AbstractCrudController::getCrudControllerFqcn($options["class"]);

        $href = null;
        if($options["href"] === null && $crudController && $this->getService()->isGranted(UserRole::ADMIN)) {

            $href = $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController($crudController)
                    ->setAction(Action::EDIT)
                    ->setEntityId("{0}")
                    ->generateUrl();
        }
        $view->vars["href"]     = $options["href"] ?? $href;

        $view->vars["multiple"]     = $options["multiple"];
        $view->vars["allow_delete"] = $isNullable;
        $view->vars["required"]     = !$isNullable;

        $data = $form->getData();
        $view->vars["entityId"] = json_encode(array_transforms(function($k, $e) use ($options):array {

            $path = PropertyAccess::createPropertyAccessor()->getValue($e, $options["entity_file"]);
            if($path instanceof Collection) $path = $path->toArray();

            $path = is_array($path) ? begin($path) ?? null : $path;
            return [basename($path), $e->getId()];

        }, ($data instanceof Collection) ? $data->toArray() : []));
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $data = $event->getData();

            $options["class"]    = $this->formFactory->guessType($event, $options);
            $options["multiple"] = $this->formFactory->guessMultiple($event, $options);
            $options["sortable"] = $this->formFactory->guessSortable($event, $options);
            
            $fieldName = $options["entity_file"];
            $isNullable = $this->classMetadataManipulator->getMapping($options["class"], $fieldName)["nullable"] ?? false;

            $form->add($fieldName, $options["form_type"], [
                'class'         => $options["class"],
                'allow_delete'  => $isNullable,
                'required'      => !$isNullable,
                'multiple'      => $options["multiple"] ?? false,
                'href'          => $options["href"] ?? null,
                'max_filesize'  => $options["max_filesize"],
                'max_files'     => $options["max_files"],
                'mime_types'    => $options["mime_types"],
                'sortable'      => $options["sortable"]
            ]);

            $files = array_filter(array_map(function($e) use ($fieldName) {

                $public = Uploader::getPublic($e, $fieldName);
                return is_array($public) ? begin($public) ?? null : $public;

            }, $options["multiple"] ? $data->toArray() : [$data]));

            $form->get($fieldName)->setData($files);
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void { }
    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $parentForm = current(iterator_to_array($forms))->getParent();
        $parentEntity = $parentForm->getParent() ? $parentForm->getParent()->getData() : null;

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

            $files = $options["multiple"] ? $form->getData() : array_filter([$form->getData()]);
            foreach($files ?? [] as $key => $file) {

                if($file instanceof File) {

                    $entityInheritance = $options["entity_inherit"] ? $parentEntity : null;
                    $entityData = is_callable($options["entity_data"]) ? null : $options["entity_data"];
                    $entity = $this->entityHydrator->hydrate($options["data_class"], $entityInheritance ?? [], ["uuid"]);
                    $entity = $this->entityHydrator->hydrate($entity, $entityData ?? []);

                    $this->propertyAccessor->setValue($entity, $fieldName, $file);

                    $newData[] = $entity;
                    
                } else {

                    $filteredData = $options["multiple"] 
                        ? $viewData->filter(fn($e) => basename($this->propertyAccessor->getValue($e, $fieldName)))
                        : [basename($this->propertyAccessor->getValue($viewData, $fieldName))];

                    $entity = $filteredData instanceof ArrayCollection ? $filteredData->first() : first($filteredData);
                    $entity = $entity === false ? null : $entity;
                }

                $entity = $entity && is_callable($options["entity_data"]) ? $options["entity_data"]($entity, $parentEntity) : $entity;
                if($entity !== null) $data[$key] = $entity;
            }
        }
        
        if($options["multiple"]) {

            if($viewData instanceof PersistentCollection) {

                $mappedBy =  $viewData->getMapping()["mappedBy"];
                $fieldName = $viewData->getMapping()["fieldName"];
                $isOwningSide = $viewData->getMapping()["isOwningSide"];
                
                if(!$isOwningSide) {

                    foreach($viewData as $entry)
                        $this->propertyAccessor->setValue($entry, $mappedBy, null);
                }

                $viewData->clear();
                foreach($data as $entry) {

                    if(!$isOwningSide) 
                        $this->propertyAccessor->setValue($entry, $mappedBy, $viewData->getOwner());
                }

            } else $viewData = $data;

        } else if($data->first()) {

            $this->entityHydrator->hydrate($viewData, $data->first());
        }
    }
}
