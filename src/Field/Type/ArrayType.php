<?php

namespace Base\Field\Type;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Entity\Layout\Attribute\Common\AbstractAttribute;
use Base\Form\Common\AbstractType;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class ArrayType extends CollectionType implements DataMapperInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(Environment $twig, TranslatorInterface $translator, AuthorizationChecker $authorizationChecker, AdminUrlGenerator $adminUrlGenerator, ClassMetadataManipulator $classMetadataManipulator)
    {
        parent::__construct($twig, $translator, $authorizationChecker, $adminUrlGenerator);
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getBlockPrefix(): string
    {
        return 'array';
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            "target" => null,
            "associative" => null,
            "prototype_key" => null,
            "prototype_id" => null,
            "allow_add" => true,
            "allow_delete" => true,
            'entry_type' => TextType::class,
            "pattern" => null,
            "length" => 0,
            "placeholder" => []
        ]);

        $resolver->setNormalizer('target', function (Options $options, $value) {
            if ($options["pattern"] !== null && $value !== null) {
                throw new \Exception("Option \"target\" cannot be set at the same time as \"pattern\"");
            }
        });

        $resolver->setNormalizer('length', fn (Options $options, $value) => $options["pattern"] ? $this->getNumberOfArguments($options["pattern"]) : $value);
        $resolver->setNormalizer('allow_add', fn (Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete', fn (Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('prototype_key', fn (Options $options, $value) => $value ?? ($options["associative"] ? "__prototype_key__" : null));
        $resolver->setNormalizer('prototype_id', fn (Options $options, $value) => $value ?? "__prototype_id__");
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        if ($options['prototype_key'] && $options["prototype"]) {

            $prototypeOptions = $options['entry_options'];
            if (null !== $options['prototype_data']) {
                $prototypeOptions['data'] = $options['prototype_data'];
            }

            if (null !== $options['entry_required']) {
                $prototypeOptions['required'] = $options['entry_required'];
            }

            $prototypeOptions["attr"]['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->translator->trans("@fields.array.value");
            $prototype = $builder->create($options['prototype_name'], FormType::class, ["label" => false])
                ->add($options["prototype_key"], TextType::class, ["label" => false, "attr" => ["placeholder" => $this->translator->trans("@fields.array.key")]])
                ->add($options['prototype_id'], $options['entry_type'], $prototypeOptions);

            $builder->setAttribute('prototype', $prototype->getForm());
            $builder->add($prototype);
            $builder->setDataMapper($this);
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

                $data = $event->getData();
                $form = $event->getForm();

                dump($data, $form);

                if($data) {

                    dump($data);

                    $array = [];
                    foreach($data as $id => $element) {

                        $childForm = $form->add($prototype);
                        $childForm->setData($element["__prototype_id__"]);

                        $array[$element["__prototype_key__"]] = $element["__prototype_id__"];
                    }

                    $form->setData($array);
                }
            });
        }
    }

    public function getNumberOfArguments($pattern): int
    {
        return preg_match_all('/\{[0-9]*\}/i', $pattern);
    }
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);

        $view->vars['length'] = is_string($options["length"]) ? explode(".", $options["length"]) : $options["length"];
        $view->vars["pattern"] = $options["pattern"];
        $view->vars["placeholder"] = $options["placeholder"];
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
//        dump(iterator_to_array($forms));
        dump($viewData);
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
//        dump(iterator_to_array($forms));
//        dump($viewData);
        foreach(iterator_to_array($forms) as $form)
        {
            dump($form);
        }
        dump($viewData);
    }
}
