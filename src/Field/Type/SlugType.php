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

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $targetPath = explode(".", $options["target"]);
        $view->vars['target'] = $targetPath;

        // Check if child exists
        $target = $form->getParent();
        foreach($targetPath as $path) {

            if(!$target->has($path))
                throw new \Exception("Child \"$path\" doesn't exists in \"".$options["target"]."\".");

            $target = $target->get($path);
            $targetType = $target->getConfig()->getType()->getInnerType();

            if($targetType instanceof TranslatableType) {
                $availableLocales = array_keys($target->all());
                $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0]);
                $target = $target->get($locale);
            }
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-slug.js");
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
