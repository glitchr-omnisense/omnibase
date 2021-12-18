<?php

namespace Base\Form;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\User\Notification;
use Base\Form\Traits\FlowFormTrait;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Base\Traits\SingletonTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;

class FormFactory extends \Symfony\Component\Form\FormFactory
{
    /**
     * @var EntityMAnager
     */
    protected $entityManager;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;
    
    public function __construct(EntityManager $entityManager, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function create(string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = []) : FormInterface
    {
        // I recommend not using entity data..
        // NB: https://blog.martinhujer.cz/symfony-forms-with-request-objects/
        if ($this->classMetadataManipulator->isEntity($data))
            throw new Exception("Form data is an entity \"" . get_class($data) . "\". This is not recommended..");

        return parent::create($type, $data, $options);
    }

    // THIS HAS TO BE REPLACED BY SYMFONY TYPE GUESSER...
    public const GUESS_FROM_FORM     = "GUESS_FROM_FORM";
    public const GUESS_FROM_PHPDOC   = "GUESS_FROM_PHPDOC";
    public const GUESS_FROM_DATA     = "GUESS_FROM_DATA";
    public const GUESS_FROM_VIEW     = "GUESS_FROM_VIEW";
    
    public function guessType(FormInterface|FormEvent $form, ?array $options = null) :?string {

        if($form instanceof FormEvent) {
            $data = $form->getData();
            $form = $form->getForm();
        } else {
            $data = $form->getData();
        }

        $options = $options ?? $form->getConfig()->getOptions();

        $class = null;
        $options["guess_priority"] = $options["guess_priority"] ?? [
            self::GUESS_FROM_FORM, 
            self::GUESS_FROM_PHPDOC, 
            self::GUESS_FROM_DATA,
            self::GUESS_FROM_VIEW
        ];
        
        foreach($options["guess_priority"] as $priority) {

            switch($priority) {

                case self::GUESS_FROM_FORM:

                    $class = $options["class"] ?? null;
                    if($class) break;

                    $parentDataClass = null;
                    $formParent = $form->getParent();
                    if($formParent) $parentDataClass = $formParent->getConfig()->getOption("data_class") 
                                        ?? get_class($formParent->getConfig()->getType()->getInnerType())
                                        ?? null;

                    if($this->classMetadataManipulator->isEntity($parentDataClass)) {

                        // Associations can help to guess the expected returned values
                        if($this->classMetadataManipulator->hasAssociation($parentDataClass, $form->getName())) {
                            $class = $this->classMetadataManipulator->getAssociationTargetClass($parentDataClass, $form->getName());

                        } else if($this->classMetadataManipulator->hasField($parentDataClass, $form->getName())) {

                            // Doctrine types as well.. (e.g. EnumType or SetType)
                            $fieldType = $this->classMetadataManipulator->getTypeOfField($parentDataClass, $form->getName());
                            $doctrineType = $this->classMetadataManipulator->getDoctrineType($fieldType);
                            if($this->classMetadataManipulator->isEnumType($doctrineType) || $this->classMetadataManipulator->isSetType($doctrineType))
                                $class = get_class($doctrineType);
                        }
                    }

                    break;

                case self::GUESS_FROM_DATA:

                    if($data instanceof PersistentCollection) $class = $data->getTypeClass()->getName();
                    else if($data instanceof ArrayCollection || is_array($data)) $class = null;
                    else $class = is_object($data) ? get_class($data) : null;

                    break;

                case self::GUESS_FROM_VIEW:

                    // Simple case, data view from current form (handle ORM Proxy management)
                    if (null !== $dataClass = $form->getConfig()->getDataClass()) {

                        if (false === $pos = strrpos($dataClass, '\\__CG__\\'))
                            return $dataClass;

                        return substr($dataClass, $pos + 8);
                    }

                    // Advanced case, loop parent form to get closest data view assuming data is inherited (e.g. TranslationType)
                    // NB: This is not a access to the corresponding expected guess.. but the closest (e.g.)
                    $formParent = $form->getParent();
                    while (null !== $formParent) {

                        if (null === ($data = $formParent->getConfig()->getDataClass())) {
                            $formParent = $formParent->getParent();
                            continue;
                        }

                        if (is_subclass_of($data, Collection::class) || is_array($data)) {
                            $formParent = $formParent->getParent();
                            continue;
                        }

                        // Associations can help to guess the expected returned values
                        if($this->classMetadataManipulator->hasAssociation($data, $form->getName())) 
                            return $this->classMetadataManipulator->getAssociationTargetClass($data, $form->getName());

                        // Doctrine types as well.. (e.g. EnumType or SetType)
                        $fieldType = $this->classMetadataManipulator->getTypeOfField($data, $form->getName());
                        $doctrineType = $this->classMetadataManipulator->getDoctrineType($fieldType);

                        if($this->classMetadataManipulator->isEnumType($doctrineType) || $this->classMetadataManipulator->isSetType($doctrineType))
                            return get_class($doctrineType);

                        $formParent = $formParent->getParent();
                        break;
                    }
                    break;

                case self::GUESS_FROM_PHPDOC:
                    // To be implemented..
            
                    break;
            }

            if($class) break;
        }

        return $class ?? $options["class"];
    }

    public function guessIfMultiple(FormInterface|FormBuilderInterface $form, $options)
    {
        if($options["multiple"] === null && $options["class"]) {
            
            $target = $options["class"];
            $entityField = $form->getName();

            if($this->classMetadataManipulator->isEntity($target)) {

                $entity = $form->getParent()->getViewData();
                return $entity ? $this->classMetadataManipulator->isToManySide($entity, $entityField) : false;

            } else if($this->classMetadataManipulator->isEnumType($target)) {

                return false;

            } else if($this->classMetadataManipulator->isSetType($target)) {

                return true;
            }
        }

        return $option["multiple"] ?? false;
    }

    public function guessChoices($options)
    {
        if (!$options["choices"]) {

            if($this->classMetadataManipulator->isEnumType($options["class"])) return $options["class"]::getPermittedValues();
            if($this->classMetadataManipulator->isSetType ($options["class"])) return $options["class"]::getPermittedValues();
        }

        return $options["choices"] ?? null;
    }

    public function guessChoiceAutocomplete($options)
    {
        if($options["choices"]) return false;
        if($options["autocomplete"] === null && $options["class"]) {
            
            $target = $options["class"];
            if($this->classMetadataManipulator->isEntity($target))
                return true;
            if($this->classMetadataManipulator->isEnumType($target))
                return false;
            if($this->classMetadataManipulator->isSetType($target))
                return false;
        }

        return $option["autocomplete"] ?? false;
    }

    public function guessChoiceFilter($options, $data)
    {
        if ($options["choice_filter"] === null) {
            
            $options["choice_filter"] = [];
            if(is_array($data)) {
                foreach($data as $entry)
                    if(is_object($entry)) $options["choice_filter"][] = get_class($entry);
            } else {
                if(is_object($data)) $options["choice_filter"][] = get_class($data);
            }

            if(!$options["choice_filter"]  && $options["class"]) {
                $options["choice_filter"][] = $options["class"];
            }
        }

        return $option["choice_filter"] ?? [];
    }
}
