<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoneyType extends \Symfony\Component\Form\Extension\Core\Type\MoneyType
{
    public const CODE_ONLY = 0;
    public const LABEL_ONLY = 1;
    public const LABELCODE_ONLY = 2;

    public function __construct(BaseService $baseService) { $this->baseService = $baseService; }
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $targetPath = explode(".", $options["currency_target"]);
        $view->vars['currency_target'] = $targetPath;

        // Check if child exists.. this just trigger an exception..
        $target = $form->getParent();
        foreach($targetPath as $path) {

            if(!$target->has($path))
                throw new \Exception("Child path \"$path\" doesn't exists in \"".get_class($target->getViewData())."\".");

            $target = $target->get($path);
            $targetType = $target->getConfig()->getType()->getInnerType();

            if($targetType instanceof TranslationType) {
                $availableLocales = array_keys($target->all());
                $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0]);
                $target = $target->get($locale);
            }
        }

        // Build view and other options
        parent::buildView($view, $form, $options);
        $view->vars["scale"] = $options["scale"];
        $view->vars["currency"] = $options["currency"];
        $view->vars["currency_list"] = array_unique(array_merge([$options["currency"]], $options["currency_list"]));
        $view->vars["currency_exchange"] = ["USD" => 1, "EUR" => 0.90];

        switch($options["currency_label"]) {
            case self::CODE_ONLY:
                $view->vars["currency_label"] = [
                    "USD" => "USD",
                    "EUR" => "EUR"
                ];
            break;

            default:
            case self::LABEL_ONLY:
                $view->vars["currency_label"] = [
                    "USD" => "\$US",
                    "EUR" => "€"
                ];
            break;

            case self::LABELCODE_ONLY:
                $view->vars["currency_label"] = [
                    "USD" => "\$US (USD)",
                    "EUR" => "€ (EUR)"
                ];
            break;
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-money.js");
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            "currency_target"   => false,
            "currency_label"    => self::LABEL_ONLY,
            "currency_exchange" => ["USD" => 1, "EUR" => 0.90],
            'currency_list'     => ["USD", "EUR"],
        ]);
    }
}
