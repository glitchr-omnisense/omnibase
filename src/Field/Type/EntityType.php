<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntityType extends AbstractType
{
    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            
            $form = $event->getForm();

            $dataClass = $this->classMetadataManipulator->getDataClass($form);
            
            $fields = $this->classMetadataManipulator->getFields($dataClass, $options["fields"], $options["excluded_fields"]);
            foreach ($fields as $fieldName => $field) {

                $fieldType = $field['field_type'] ?? null;
                unset($field['field_type']);
    
                $form->add($fieldName, $fieldType, $field);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'fields' => [],
            'excluded_fields' => []
        ]);

        $resolver->setNormalizer('data_class', function (Options $options, $value): string {

            if (empty($value))
                throw new \RuntimeException(sprintf('Missing "data_class" option in "'.get_called_class().'".'));

            return $value;
        });
    }
}