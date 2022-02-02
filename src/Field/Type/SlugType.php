<?php

namespace Base\Field\Type;

use Base\Model\AutovalidateInterface;
use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
final class SlugType extends AbstractType implements AutovalidateInterface
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
            ->setAllowedTypes('target', ['string', 'null'])
            ->setDefaults([
                "separator" => "-"
            ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $target = $form->getParent();
        $targetPath = $options["target"] ? explode(".", $options["target"]) : [];
        $view->vars['target'] = $targetPath;
        
        // Check if child exists.. this just trigger an exception..
        foreach($targetPath as $path) {
            
            if(!$target->has($path))
            throw new \Exception("Child form \"$path\" related to view data \"".get_class($target->getViewData())."\" not found in ".get_class($form->getConfig()->getType()->getInnerType())." (complete path: \"".$options["target"]."\")");
            
            $target = $target->get($path);
            $targetType = $target->getConfig()->getType()->getInnerType();
            
            if($targetType instanceof TranslationType) {
                $availableLocales = array_keys($target->all());
                $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0]);
                $target = $target->get($locale);
            }
        }
        
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-slug.js");
    }

    public function getParent() : ?string
    {
        return TextType::class;
    }
}
