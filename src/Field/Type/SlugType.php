<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
final class SlugType extends AbstractType
{
    public function __construct(BaseService $baseService) {

        $this->baseService = $baseService;
    }

    public function getBlockPrefix(): string
    {
        return 'slug';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['target'])
            ->setAllowedTypes('target', ['string'])
        ;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $targetPath = explode(".", $options["target"]);
        $view->vars['target'] = $targetPath;

        $this->baseService->addJavascriptFile("/bundles/base/form-type-slug.js");
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
