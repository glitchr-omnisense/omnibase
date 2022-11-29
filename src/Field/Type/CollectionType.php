<?php

namespace Base\Field\Type;

use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Doctrine\Common\Collections\Collection;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class CollectionType extends AbstractType
{
    public function __construct(Environment $twig, TranslatorInterface $translator, AuthorizationChecker $authorizationChecker, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->twig = $twig;
        $this->translator = $translator;

        $this->authorizationChecker = $authorizationChecker;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function getBlockPrefix(): string { return 'collection2'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'form2' => false,
            'length' => 0,
            'allow_add' => true,
            'allow_delete' => true,
            'html'      => false,
            'href' => null,
            'prototype' => true,
            'prototype_data' => null,
            'prototype_name' => '__prototype__',
            'group' => true,
            'row_group' => true,
            'entry_collapsed' => true,
            'entry_type' => TextType::class,
            'entry_label' => function($i, $label)
            {
                if($i === "__prototype__") return false;

                if(!is_object($label)) return $this->translator->trans("@fields.collection.entry"). " #".(((int)$i)+1);

                $_label = $this->translator->transEntity($label). " #".(((int)$label->getId())+1);
                if(is_stringeable($label)) $_label .= " : ". ((string) $label);
                return $_label;
            },
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
            // NB: Child fields "required" options are deduced from parents..
            //     .. but collection is not supposed to knows about child requirements, IMO
            return true;
        });

        $resolver->setNormalizer('allow_add',     fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete',  fn(Options $options, $value) => $options["length"] == 0 && $value);

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

            $prototypeOptions["label"] = "__prototype__";
            $prototypeOptions['attr']['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->translator->trans("@fields.array.value");
            $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
            $builder->setAttribute('prototype', $prototype->getForm());
        }

        // Resize collection according to length option
        if(is_int($options["length"]) && $options["length"] > 0) {

            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

                $data = $event->getData() ?? [];
                if($data instanceof Collection)
                    while(count($data) < $options["length"]) $data->add(null);
                else if(is_array($data))
                    $data = array_pad($data, $options["length"], null);

                $event->setData($data);
            });
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
        //
        // Set controller url
        $view->vars['href']            = $options["href"];
        $view->vars['html']            = $options["html"];
        $view->vars['entry_collapsed'] = $options['entry_collapsed'];
        $view->vars['entry_label']     = $options['entry_label'];
        $view->vars['entry_options']   = $options['entry_options'];
        $view->vars['data_class']      = $options['data_class'];
        $view->vars['group']           = $options['group'];
        $view->vars['row_group']       = $options['row_group'];
        $view->vars['allow_add']       = $options['allow_add'];
        $view->vars['allow_delete']    = $options['allow_delete'];

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
        $view->vars['length'] = $options["length"];

        $prefixOffset = -1;
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
    }
}
