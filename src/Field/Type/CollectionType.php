<?php

namespace Base\Field\Type;

use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Base\Admin\Router\AdminUrlGenerator;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
    /** @var array<int,int> total entry count per form instance, pre-windowing */
    private array $entryTotals = [];

    /**
     * @var Environment
     */
    protected Environment $twig;

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var AuthorizationChecker
     */
    protected AuthorizationChecker $authorizationChecker;

    /**
     * @var AdminUrlGenerator
     */
    protected AdminUrlGenerator $adminUrlGenerator;

    public function __construct(Environment $twig, TranslatorInterface $translator, AuthorizationChecker $authorizationChecker, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->twig = $twig;
        $this->translator = $translator;

        $this->authorizationChecker = $authorizationChecker;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function getBlockPrefix(): string
    {
        return 'collection2';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'form2' => false,
            'length' => 0,
            // Cap how many entries are BUILT into the form. Each entry is a full
            // sub-form whose own relations get hydrated, so an unbounded
            // collection is an unbounded query count - a thread with 146
            // comments builds 146 sub-forms on every edit page load.
            // Deliberately null (off) by default: see the allow_delete guard
            // below for why this cannot be applied blindly.
            'max_entries' => null,
            // Window offset, used by the lazy-load endpoint to render the NEXT
            // slice of an already-capped collection.
            'entry_offset' => 0,
            'allow_object' => false, // This is introduced because object should be stringeable, otherwise there will be some error turning it into string
            'allow_add' => false,
            'allow_delete' => true,
            'html' => false,
            'href' => null,
            'prototype' => true,
            'prototype_data' => null,
            'prototype_name' => '__prototype__',
            'group' => true,
            'checkbox' => false,
            'row_group' => true,
            'entry_collapsed' => true,
            'entry_type' => HiddenType::class,
            'entry_label' => function ($i, $label) {
                if ($i === "__prototype__") {
                    return false;
                }

                if (!is_object($label)) {
                    return $this->translator->trans("@fields.collection.entry") . " #" . (((int)$i) + 1);
                }

                $_label = $this->translator->transEntity($label) . " #" . (((int)$label->getId()) + 1);
                if (is_stringeable($label)) {
                    $_label .= " : " . ((string)$label);
                }
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

        $resolver->setNormalizer('allow_add', fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete', fn(Options $options, $value) => $options["length"] == 0 && $value);

        $resolver->setAllowedTypes('delete_empty', ['bool', 'callable']);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['allow_add'] && $options['prototype']) {
            $prototypeOptions = $options['entry_options'];
            if (null !== $options['prototype_data']) {
                $prototypeOptions['data'] = $options['prototype_data'];
            }

            if (null !== $options['entry_required']) {
                $prototypeOptions['required'] = $options['entry_required'];
            }

            $prototypeOptions["label"] = "__prototype__";
            $prototypeOptions['attr']['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->translator->trans("@fields.array.value");
            $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
            $builder->setAttribute('prototype', $prototype->getForm());
        }

        // Prevent stringeable issue for entities...
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            $form = $event->getForm();
            $data = $event->getData();
            if ($form->getName() == "_collection") { // Special case from association type
                $data ??= $form->getParent()->getData();
            }

            $data ??= [];

            foreach ($data as $id => $entry) {
                if (is_object($entry) && !$options["allow_object"]) {
                    throw new Exception("Object data are not allowed in collection unless you mark it as so.. (use `allow_object` option)");
                }
            }

            $event->setData($data);
        });

        // Cap the number of entries built, when asked and when SAFE to do so.
        //
        // The guard matters: Symfony removes entries that are absent from the
        // submitted data only when allow_delete is true. With allow_delete
        // false, entries we never rendered are left alone on save - so capping
        // is purely a rendering economy. With allow_delete true the same cap
        // would silently delete every entry past the limit the moment the form
        // is submitted, so it is refused outright rather than made optional.
        $maxEntries = $options["max_entries"];
        $entryOffset = max(0, (int) $options["entry_offset"]);

        // Remember how many entries there really were, before any windowing, so
        // the view can offer "load the rest". Keyed per form instance and
        // request-scoped - the type itself is a shared service.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $this->entryTotals[spl_object_id($event->getForm())] = is_countable($data) ? \count($data) : 0;
        }, 10);
        if (\is_int($maxEntries) && $maxEntries > 0 && false === $options["allow_delete"]) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($maxEntries, $entryOffset) {
                $data = $event->getData();
                if (null === $data) {
                    return;
                }

                $count = is_countable($data) ? \count($data) : 0;
                if (0 === $entryOffset && $count <= $maxEntries) {
                    return;
                }

                // preserve_keys = true is load-bearing, not tidiness: the key IS
                // the entry's index in the collection, and therefore the index in
                // the submitted field name. A lazily fetched entry #12 must come
                // back as [_collection][12][...] or it would bind to the wrong
                // slot (or create a new one) on save.
                if ($data instanceof Collection) {
                    $event->setData(new ArrayCollection(\array_slice($data->toArray(), $entryOffset, $maxEntries, true)));
                } elseif (\is_array($data)) {
                    $event->setData(\array_slice($data, $entryOffset, $maxEntries, true));
                }
            });
        }

        // Resize collection according to length option
        if (is_int($options["length"]) && $options["length"] > 0) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
                $data = $event->getData() ?? [];
                if ($data instanceof Collection) {
                    while (count($data) < $options["length"]) {
                        $data->add(null);
                    }
                } elseif (is_array($data)) {
                    $data = array_pad($data, $options["length"], null);
                }

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
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        //
        // Set controller url
        $view->vars['href'] = $options["href"];
        $view->vars['html'] = $options["html"];
        $view->vars['entry_collapsed'] = $options['entry_collapsed'];
        $view->vars['entry_label'] = $options['entry_label'];
        $view->vars['entry_options'] = $options['entry_options'];
        $view->vars['data_class'] = $options['data_class'];
        $view->vars['group'] = $options['group'];
        $view->vars['row_group'] = $options['row_group'];
        $view->vars['allow_add'] = $options['allow_add'];
        $view->vars['allow_delete'] = $options['allow_delete'];

        if ($form->getConfig()->hasAttribute('prototype')) {
            $prototype = $form->getConfig()->getAttribute('prototype');
            $view->vars['prototype'] = $prototype->setParent($form)->createView($view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['length'] = $options["length"];
        // The window, for the lazy-load control (see collection2_widget).
        $view->vars['entry_offset'] = max(0, (int) $options["entry_offset"]);
        $view->vars['max_entries'] = $options["max_entries"];
        $view->vars['entry_total'] = $this->entryTotals[spl_object_id($form)] ?? null;

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
            array_splice($entryView->vars['block_prefixes'], $prefixOffset, 0, $this->getBlockPrefix() . '_entry');
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

            array_splice($prototypeView->vars['block_prefixes'], $prefixOffset, 0, $this->getBlockPrefix() . '_entry');
        }
    }
}
