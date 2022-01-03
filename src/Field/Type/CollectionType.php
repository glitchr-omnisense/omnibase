<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionType extends AbstractType
{
    public function __construct(BaseService $baseService) { $this->baseService = $baseService; }
    public function getBlockPrefix(): string { return 'collection2'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'form2' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'collection_required' => true,
            'prototype' => true,
            'prototype_data' => null,
            'prototype_name' => '__prototype__',
            'entry_type' => TextType::class,
            'entry_inline' => false,
            'entry_row_inline' => false,
            'entry_label' => null,
            'entry_options' => [],
            'entry_required' => null,
            'delete_empty' => false,
            'invalid_message' => function (Options $options, $previousValue) {
               return 'The collection is invalid.';
            },
        ]);

        $resolver->setNormalizer('entry_options', function (Options $options, $value) {
            $value['block_name'] = 'entry';
            $value["label"] = false;
            return $value;
        });

        $resolver->setNormalizer('required', function (Options $options, $value) {
            // Collection is always submitted regardless of its options..
            // It returns at least an empty array..
            // NB: Child fields "required" options are deduced from parents.
            //     Collection is not supposed to knows about child requirements
            return true;
        });

        $resolver->setAllowedTypes('delete_empty', ['bool', 'callable']);
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['allow_add'] && $options['prototype']) {

            $prototypeOptions = $options['entry_options'];
            if (null !== $options['prototype_data'])
                $prototypeOptions['data'] = $options['prototype_data'];

            if (null !== $options['entry_required'])
                $prototypeOptions['required'] = $options['entry_required'];

            $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
            $builder->setAttribute('prototype', $prototype->getForm());
        }
        
        $builder->addEventSubscriber(new ResizeFormListener(
            $options['entry_type'],
            $options['entry_options'],
            $options['allow_add'],
            $options['allow_delete'],
            $options['delete_empty']
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['entry_label'] = $options['entry_label'] ?? null;
        $view->vars['entry_inline'] = $options['entry_inline'] ?? false;
        $view->vars['entry_row_inline'] = $options['entry_row_inline'] ?? false;
        $view->vars['allow_add'] = $options['allow_add'] ?? false;
        $view->vars['allow_delete'] = $options['allow_delete'] ?? false;

        if ($form->getConfig()->hasAttribute('prototype')) {
            $prototype = $form->getConfig()->getAttribute('prototype');
            $view->vars['prototype'] = $prototype->setParent($form)->createView($view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $prefixOffset = -2;
        // check if the entry type also defines a block prefix
        /** @var FormInterface $entry */
        foreach ($form as $entry) {
            if ($entry->getConfig()->getOption('block_prefix')) {
                --$prefixOffset;
            }

            break;
        }

        foreach ($view as $entryView) {
            array_splice($entryView->vars['block_prefixes'], $prefixOffset, 0, $this->getBlockPrefix().'_entry');
        }

        /** @var FormInterface $prototype */
        if ($prototype = $form->getConfig()->getAttribute('prototype')) {

            $prototypeView = $view->vars['prototype'];
            if ($prototypeView->vars['multipart']) {
                $view->vars['multipart'] = true;
            }

            if ($prefixOffset > -3 && $prototype->getConfig()->getOption('block_prefix')) {
                --$prefixOffset;
            }

            array_splice($prototypeView->vars['block_prefixes'], $prefixOffset, 0, $this->getBlockPrefix().'_entry');
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-collection.js");
    }
}
