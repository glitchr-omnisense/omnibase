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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
        $resolver->setDefaults([
            'class'     => null,
            'form_type' => null,
            'autoload'  => true,
            'href'      => null,

            'entry_collapsed' => true,
            'entry_label' => function($i, $e) { return $i === "__prototype__" ? false : $this->getTranslator()->entity($e). " #".(((int)$i)+1); },
            'entry_required' => true,

            'fields' => [],
            'keep_indexes' => true,
            'length' => 0,
            'excluded_fields' => [],

            'recursive' => null,
            "multiple" => false,
            'group'     => true,
            'row_group' => true,

            'allow_add' => true,
            'allow_delete' => true,
            'allow_entity' => true,
        ]);

        $resolver->setNormalizer('required', function (Options $options, $value) {
            // Association type must depends on child requirement here.. it marked as false for some reasons..
            return $options["entry_required"];
        });

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if($options["multiple"]) return null;
            return $value ?? null;
        });

        $resolver->setNormalizer('allow_add', function (Options $options, $value) {
            if($options["group"]) return $value ?? null;
            return false;
        });
        $resolver->setNormalizer('allow_delete', function (Options $options, $value) {
            if($options["group"]) return $value ?? null;
            return false;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['href']         = $options["href"];
        $view->vars["multiple"]     = $options["multiple"];
        $view->vars["group"]        = $options["group"];
        $view->vars["row_group"]    = $options["row_group"];
        $view->vars["allow_add"]    = $options["allow_add"];
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["keep_indexes"] = $options["keep_indexes"];
        $view->vars['length']       = $options["length"];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $data = $event->getData();

            if($options["multiple"]) {

                $dataClass = $options["class"];
                unset($options["class"]);

                if(!is_array($data) && !$data instanceof Collection)
                    $data = [$data];

                $collectionOptions = [
                    "data_class"       => null,
                    "label"            => $options["label"],
                    'by_reference'     => false,
                    'length'           => $options["group"] ? $options["length"] : max(1, $options["length"]),
                    "group"            => $options["group"],
                    "row_group"        => $options["row_group"],
                    'entry_collapsed'  => $options["entry_collapsed"],
                    'entry_type'       => AssociationType::class,
                    'entry_label'      => $options["entry_label"],
                    'entry_options'    => array_merge($options, [
                        'href'         => $options["href"] ?? null,
                        'allow_entity' => $options["allow_entity"],
                        'data_class'   => $dataClass,
                        'multiple'     => false,
                        'keep_indexes' => $options["keep_indexes"],
                    ]),
                ];

                if ($options["allow_add"] !== null)
                    $collectionOptions['allow_add'] = $options["group"] ? $options["allow_add"] : false;
                if ($options["allow_delete"] !== null)
                    $collectionOptions['allow_delete'] = $options["group"] ? $options["allow_delete"] : false;

                $form->add("_collection", CollectionType::class, $collectionOptions);

            } else {

                $dataClass = $options["class"] ?? $this->formFactory->guessClass($event, $options);
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

                    if ($fieldType !== null && ($fieldEntity || $fieldType != AssociationType::class))
                        $form->add($fieldName, $fieldType, $field);
                }

                if($options["keep_indexes"]) $form->add("_index", HiddenType::class, ["mapped" => false, "required" => false]);
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

                if(!$childForm->getConfig()->getOption("mapped")) continue;

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
        $form = current(iterator_to_array($forms));
        $formParent  = $form->getParent();

        $options     = $formParent->getConfig()->getOptions();
        $options["class"]    = $options["class"] ?? $this->formFactory->guessClass($formParent, $options);
        $options["multiple"] = $options["multiple"]   ?? $this->formFactory->guessMultiple($formParent, $options);

        $data = new ArrayCollection();
        foreach(iterator_to_array($forms) as $fieldName => $childForm)
            $data[$fieldName] = $childForm->getData();

        if(!$options["multiple"] && $this->classMetadataManipulator->isEntity($options["class"])) {

            $classMetadata = $this->classMetadataManipulator->getClassMetadata($options["class"]);
            if(!$classMetadata)
                throw new \Exception("Entity \"".$options["class"]."\" not found.");


            $viewData = $this->entityHydrator->hydrate(is_object($viewData) ? $viewData : $options["class"], $data);

        } else if($viewData instanceof PersistentCollection) {

            $mappedBy =  $viewData->getMapping()["mappedBy"];
            $fieldName = $viewData->getMapping()["fieldName"];
            $isOwningSide = $viewData->getMapping()["isOwningSide"];

            if ($data->containsKey("_collection"))
                $data = $data->get("_collection");

            if(!$isOwningSide) {

                foreach($viewData as $entry)
                    $this->propertyAccessor->setValue($entry, $mappedBy, null);
            }

            $viewData->clear();
            foreach($data as $n => $entry) {

                $viewData->add($entry);
                if(!$isOwningSide)
                    $this->propertyAccessor->setValue($entry, $mappedBy, $viewData->getOwner());
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
