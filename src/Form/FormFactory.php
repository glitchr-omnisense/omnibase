<?php

namespace Base\Form;

use Base\Database\Entity\EntityHydrator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Form\Common\FormModelInterface;
use Base\Form\Common\FormTypeInterface;
use Base\Form\FormFlow;
use Base\Form\Traits\FormGuessTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface as SymfonyFormTypeInterface;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormRegistryInterface;

use Symfony\Component\Form\FormFactory as SymfonyFormFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FormFactory extends SymfonyFormFactory implements FormFactoryInterface
{
    use FormGuessTrait;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;
    /**
     * @var EntityHydratorInterface
     */
    protected $entityHydrator;
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(FormRegistryInterface $registry, ValidatorInterface $validator, EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator, EntityHydratorInterface $entityHydrator)
    {
        parent::__construct($registry);

        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityManager  = $entityManager; 
        $this->entityHydrator = $entityHydrator; 
        $this->validator = $validator;
    }

    public function createBuilder(string $type = FormType::class, mixed $data = null, array $options = []) : FormBuilderInterface
    {
        // I recommend not using entity data..
        // NB: https://blog.martinhujer.cz/symfony-forms-with-request-objects/
        if ($this->classMetadataManipulator->isEntity($data))
            throw new Exception("An entity \"" . get_class($data) . "\" is passed as data in \"".$type."\".\nThis is not recommended due to possible database flush conflict. Please use a DTO model.");

        return parent::createBuilder($type, $data, $options);
    }

    public function create(string $type = FormType::class, mixed $data = null, array $options = [], array $listeners = []): FormInterface
    {
        $formModelClass = null;
        if($options["data_class"] ?? null)
            $formModelClass = $options["data_class"];
        else if(class_implements_interface($type, FormTypeInterface::class))
            $formModelClass = $type::getModelClass();
        else if(class_implements_interface($type, SymfonyFormTypeInterface::class))
            $formModelClass = str_replace("\\Type\\", "\\Model\\", str_rstrip($type, "Type")."Model");
        
        if($formModelClass && !class_implements_interface($formModelClass, FormModelInterface::class))
            throw new Exception("Form model \"$formModelClass\" must exist and implement \"".FormModelInterface::class."\".");
        
        if(!$data) $data = cast_empty($formModelClass);
        else if(is_array($data)) $data = cast_from_array($data, $formModelClass);
        else $data = cast($data, $formModelClass);

        $formBuilder = $this->createBuilder($type, $data, $options);
        if ($formModelClass) {

            $formBuilder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

                $form = $event->getForm();

                $validationEntity = $form->getConfig()->getOption("validation_entity");
                if($this->classMetadataManipulator->isEntity($validationEntity)) {

                    $entity = $this->entityHydrator->hydrate($validationEntity, $form->getData(), []);
                    $metadata = $this->validator->getMetadataFor($validationEntity);

                    $validationGroups = $form->getConfig()->getOption("validation_groups");
                    if(!$validationGroups) {
                        $validationGroups[] = "Default";
                        $validationGroups[] = class_basename($validationEntity);
                    }

                    $constraints = [];
                    foreach($validationGroups ?? [] as $group)
                        $constraints = array_merge($constraints, $metadata->findConstraints($group));
                    
                    $errors = $this->validator->validate($entity, array_unique_object($constraints), $form->getConfig()->getOption("validation_groups"));
                    if (count($errors) > 0) $form->addError(new FormError((string) $errors));
                }
            });
        }

        foreach($listeners as $eventName => $eventListener) {

            $listeners = [];
            if(!is_array($eventListener)) 
                $eventListener = [$eventListener, 0];

            foreach($eventListener as $_) {

                if(!is_array($_)) $_ = [$_, 0];
                
                $listener = $_[0] ?? null;
                $priority = $_[1] ?? 0;

                if(!is_callable($listener))
                    throw new Exception("Invalid listener (".$eventName.") information provided for Form \"".$name."\".");

                $listeners[] = [$listener, $priority];
            } 

            foreach($listeners as $_) {

                list($listener, $priority) = $_;
                $formBuilder->addEventListener($eventName, $listener, $priority);
            }
        }

        return $formBuilder->getForm();
    }

}