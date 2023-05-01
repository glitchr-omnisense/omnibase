<?php

namespace Base\Form\Extension;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Form\FormFactory;
use Base\Service\BaseService;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Symfony\Component\Form\AbstractTypeExtension;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FormTypeCollectionExtension extends AbstractTypeExtension
{
    /**
     * @var BaseService
     */
    protected BaseService $baseService;

    /**
     * @var FormFactory
     */
    protected FormFactory $formFactory;

    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    public function __construct(BaseService $baseService, FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->baseService = $baseService;
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->browseView($view, $form, $options);
    }

    public function browseView(FormView $view, FormInterface $form, array $options)
    {
        if ($form->getData() instanceof PersistentCollection &&
            $this->classMetadataManipulator->isCollectionOwner($form, $form->getData()) === false) {
            $view->vars["required"] = false;
            $view->vars["disabled"] = true;
        }

        foreach ($view->children as $field => $childView) {
            if (!$form->has($field)) {
                continue;
            }

            $childForm = $form->get($field);
            $childOptions = $childForm->getConfig()->getOptions();

            $this->browseView($childView, $childForm, $childOptions);
        }
    }
}
