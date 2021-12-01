<?php

namespace Base\Field\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Base\Service\BaseService;

/**
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
final class ColorType extends AbstractType
{
    /** @var BaseService */
    protected $baseService;
    public function __construct(BaseService $baseService) { $this->baseService = $baseService; }

    public function getBlockPrefix(): string
    {
        return 'jscolor';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'jscolor-js'    => $this->baseService->getParameterBag("base.vendor.jscolor.js"),
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // JSColor requirement
        $view->vars['attr']['data-jscolor'] = "{}";

        // JSColor class for stylsheet
        $view->vars['attr']["class"] = "jscolor";

        // Add alpha channel by default
        switch( strlen($view->vars['value']) ) {
            case 4:
                $view->vars['value'] .= "F";
                break;
            case 7:
                $view->vars['value'] .= "FF";
                break;
            case 9:
                break;
            default:
                $view->vars['value'] = "#AAAAAAFF";
        }
        $options["value"] = $view->vars['value'];

        // Import JSColor
        $this->baseService->addHtmlContent("javascripts", $options["jscolor-js"]);

    }

    public function getParent() : ?string
    {
        return TextType::class;
    }
}
