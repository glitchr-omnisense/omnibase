<?php

namespace Base\Form;

use Base\Database\Factory\ClassMetadataManipulator;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;

class FormFactory extends \Symfony\Component\Form\FormFactory
{
    /**
     * @var EntityManager
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

                    // Simple case, data view from current form (handle ORM Proxy management)
                    $dataClass = $form->getConfig()->getOption("data_class");

                    while (null !== $formParent) {

                        $parentDataClass = $formParent->getConfig()->getOption("data_class") 
                            ?? get_class($formParent->getConfig()->getType()->getInnerType())
                            ?? null;

                        if($this->classMetadataManipulator->isEntity($parentDataClass)) {

                            $class = $this->classMetadataManipulator->getTargetclass($parentDataClass, $form->getName());
                        }

                        if($class) break;

                        $formParent = $formParent->getParent();
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

                        if($this->classMetadataManipulator->isEntity($data))
                            $class = $this->classMetadataManipulator->getTargetClass($data, $form->getName());

                        $formParent = $formParent->getParent();
                    }

                    break;

                case self::GUESS_FROM_PHPDOC:
                    // To be implemented..
            
                    break;
            }

            if($class) break;
        }

        return $class ?? $options["class"] ?? null;
    }

    public function guessMultiple(FormInterface|FormEvent|FormBuilderInterface $form, ?array $options = null)
    {
        if ($form instanceof FormEvent)
            $form = $form->getForm();

        $options = $options ?? $form->getConfig()->getOptions();

        if($options["multiple"] === null && ($options["class"] !== null || $options["data_class"] !== null)) {

            $target = $options["class"] ?? $options["data_class"] ?? null;

            if($this->classMetadataManipulator->isEntity($target)) {

                $parentForm = $form->getParent();
                if(!$parentForm) return false;

                $entityField = $form->getName();
                $entity = $parentForm->getConfig()->getOption("data_class");

                if($this->classMetadataManipulator->hasAssociation($entity, $entityField) )
                    return $this->classMetadataManipulator->isToManySide($entity, $entityField);
                else if($this->classMetadataManipulator->hasField($entity, $entityField))
                    return $this->classMetadataManipulator->getTypeOfField($entity, $entityField) == "array";

            } else if($this->classMetadataManipulator->isEnumType($target)) {

                return false;

            } else if($this->classMetadataManipulator->isSetType($target)) {

                return true;
            }
        }

        return $options["multiple"] ?? false;
    }

    public function guessSortable(FormInterface|FormEvent|FormBuilderInterface $form, ?array $options = null)
    {
        if ($form instanceof FormEvent)
            $form = $form->getForm();

        $options = $options ?? $form->getConfig()->getOptions();
        if ($options["sortable"] === null) {

            $options["sortable"] = true;
        }

        return $options["sortable"] ?? false;
    }

    public function guessChoices(FormInterface|FormBuilderInterface $form, ?array $options = null)
    {
        $options = $options ?? $form->getConfig()->getOptions();

        if (!$options["choices"]) {

            $class = $options["class"];

            $permittedValues = null;
            if($this->classMetadataManipulator->isEnumType($class))
                $permittedValues = $class::getPermittedValuesByClass();
            else if($this->classMetadataManipulator->isSetType ($class))
                $permittedValues = $class::getPermittedValuesByClass();
            else if(array_key_exists("choice_loader", $options) && $options["choice_loader"] instanceof ChoiceLoaderInterface)
                $permittedValues = $options["choice_loader"] ? $options["choice_loader"]->loadChoiceList()->getStructuredValues() : null;

            if($permittedValues === null) return null;
            return count($permittedValues) == 1 ? begin($permittedValues) : $permittedValues;
        }

        return $options["choices"] ?? null;
    }


    public function guessChoiceAutocomplete(FormInterface|FormBuilderInterface $form, ?array $options = null)
    {
        $options = $options ?? $form->getConfig()->getOptions();
        
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

        return $options["autocomplete"] ?? false;
    }

    public function guessChoiceFilter(FormInterface|FormBuilderInterface $form, ?array $options = null, $data = null)
    {
        $options = $options ?? $form->getConfig()->getOptions();

        if ($options["choice_filter"] === false) return [];
        if ($options["choice_filter"] === null) {

            $options["choice_filter"] = [];
            if($data) {

                if($data instanceof Collection || is_array($data)) {

                    foreach($data as $entry)
                        if(is_object($entry)) $options["choice_filter"][] = get_class($entry);

                } else if(is_object($data)) {
                    
                    $options["choice_filter"][] = get_class($data);
                }
            }

            if(!$options["choice_filter"]  && $options["class"]) {
                $options["choice_filter"][] = $options["class"];
            }
        }

        return $options["choice_filter"] ?? [];
    }
}
