<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Model\AutovalidateInterface;
use Base\Twig\Environment;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SlugType extends AbstractType implements AutovalidateInterface
{

    public function __construct(Environment $twig, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->twig = $twig;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getParent() : ?string { return TextType::class; }
    public function getBlockPrefix(): string { return 'slug'; }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                "separator" => "-",
                "keep"   => null,
                "lower"  => true,
                "lock"   => null,
                "strict" => null,
                "target" => null,
                "required" => false
            ]);

            $resolver->setNormalizer('strict', function (Options $options, $value) {
                if($value === null) return empty($options["keep"] ?? "");
                return $value;
            });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $this->twig->addHtmlContent("javascripts:body", "bundles/base/form-type-slug.js");
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["keep"]      = $options["keep"]      ? preg_quote($options["keep"])      : null;
        $view->vars["separator"] = $options["separator"] ?$options["separator"] : null;
        $view->vars["lower"]     = json_encode($options["lower"]);
        $view->vars["strict"]    = json_encode($options["strict"]);
        $view->vars["lock"]      = json_encode($options["lock"]);

        // Make sure field is not rquired when slugis nullable
        $dataClass = $form->getParent()->getConfig()->getDataClass();
        if($dataClass && $this->classMetadataManipulator->hasField($dataClass, $form->getName())) {

            $fieldMapping = $this->classMetadataManipulator->getFieldMapping($dataClass, $form->getName());
            $isNullable   = $fieldMapping["nullable"] ?? false;
            $view->vars["required"] = $options["required"] || !$isNullable;
        }

        // Check if path is reacheable..
        if($options["target"] !== null && str_starts_with($options["target"], ".")) {

            $view->ancestor = $view->parent;

            $target = $form->getParent();
            $targetPath = substr($options["target"], 1);

        } else {

            // Get oldest parent form available..
            $ancestor = $view;
            while($ancestor->parent !== null)
                $ancestor = $ancestor->parent;

            $view->ancestor = $ancestor;

            $target = $form->getParent();
            while($target && ($target->getViewData() instanceof Collection || $target->getViewData() === null))
                $target = $target->getParent();

            $targetPath = $options["target"];
        }

        $targetPath = $targetPath ? explode(".", $targetPath) : null;
        foreach($targetPath ?? [] as $path) {

            if(!$target->has($path))
                throw new \Exception("Child form \"$path\" related to view data \"".get_class($target->getViewData())."\" not found in ".get_class($form->getConfig()->getType()->getInnerType())." (complete path: \"".$options["target"]."\")");

            $target = $target->get($path);
            $targetType = $target->getConfig()->getType()->getInnerType();

            if($targetType instanceof TranslationType) {

                $availableLocales = array_keys($target->all());
                $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0] ?? null);
                if($locale) $target = $target->get($locale);
            }
        }

        $view->vars['target'] = $targetPath;
    }
}
