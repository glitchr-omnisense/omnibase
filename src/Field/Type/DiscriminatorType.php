<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Enum\UserRole;
use Base\Form\FormFactory;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;


class DiscriminatorType extends AbstractType
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    
    /**
     * @var FormFactory
     */
    protected $formFactory = null;
    
    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();

            $class = $options["data_class"] ?? $this->formFactory->guessType($form->getParent());
            if(!$class) throw new \Exception("Entity cannot be determined for " . $form->getName());

            $discriminatorMap   = $this->classMetadataManipulator->getDiscriminatorMap($class);
            $rootEntityName   = $this->classMetadataManipulator->getRootEntityName($class);

            $choices = [];
            foreach($discriminatorMap as $key => $entity) {

                $icon = null;
                if(class_implements_interface($entity, IconizeInterface::class))
                    $icon = $entity::__staticIconize()[0];

                if($options["exclude_root"] && $entity == $rootEntityName)
                    continue;

                $choices[mb_ucwords($key)] = [$icon, $key];

                // if(!class_implements_interface($entity, AttributeInterface::class))
                //     continue;
                
                // $fieldType    = $entity::getType();
                // $fieldOptions = $entity::getOptions();
                // $form->add($fieldName, $fieldType, $fieldOptions);
                // dump($fieldType);
            }

            $form->add("choice", SelectType::class, ["choices" => $choices]);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        
    }

    public function getBlockPrefix(): string { return 'discriminator'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "exclude_root" => true
        ]);
    }
}
