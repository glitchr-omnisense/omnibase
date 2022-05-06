<?php

namespace Base\Form\Extension;

use Base\Service\BaseService;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FormTypeBootstrapExtension extends AbstractTypeExtension
{
    public function __construct(BaseService $baseService) { $this->baseService = $baseService; }

    public static function getExtendedTypes(): iterable { return [FormType::class]; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'bootstrap' => $this->baseService->getParameterBag("base.twig.use_bootstrap"),
            'bootstrap_label' => true
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->browseView( $view, $form, $options);
    }

    public function browseView(FormView $view, FormInterface $form, array $options)
    {
        foreach($view->children as $field => $childView) {

            if (!$form->has($field))
                continue;
                
            $childForm = $form->get($field);
            $childOptions = $childForm->getConfig()->getOptions();

            if($options["bootstrap"]) $this->applyBootstrap($childView, $childForm, $options);

            $this->browseView($childView, $childForm, $childOptions);
        }
    }

    public function applyBootstrap(FormView $view, FormInterface $form, array $options)
    {
        $type = explode("\\", get_class($form->getConfig()->getType()->getInnerType()));
            
            $label = $view->vars["label"] ?? null;
            if($label === null) $label = mb_ucfirst((string) $view->vars["name"]);

            $attr = $view->vars["attr"];
           
            switch(end($type)) {

                case "CheckboxType":
                    self::addAttribute($view, "class", "form-switch form-switch-lg form-check-input");
                    self::addRowAttribute($view, "class", "form-switch form-switch-lg form-group form-check");
                    self::addLabelAttribute($view, "class", "form-check-label");
                    break;

                case "SubmitType":
                    self::addAttribute($view, "class", "btn btn-primary");
                    self::addLabelAttribute($view, "class", "btn btn-primary");
                    break;
    
                case "HiddenType":
                    break;

                default:
                    self::addAttribute($view, "class", "form-control");
                    self::addRowAttribute($view, "class", "form-group");

                    if(!$options["bootstrap_label"]) $view->vars["label"] = false;
            }

            if(!array_key_exists("placeholder", $attr) || $attr["placeholder"] == null) {
                if(!$options["bootstrap_label"]) $view->vars["attr"]["placeholder"] = $label;
            }
    }

    public static function addAttribute(FormView $view, $name, $value)
    {
        if (!array_key_exists($name, $view->vars["attr"]))
            return $view->vars["attr"][$name] = $value;

        $classList  = explode(" ", trim($view->vars["attr"][$name], " "));
        foreach(explode(" ", $value) as $class)
            $classList[] = $class;

        $view->vars["attr"][$name] = implode(" ", array_unique($classList));
        return $view->vars["attr"][$name];
    }

    public static function addRowAttribute(FormView $view, $name, $value)
    {
        if (!array_key_exists($name, $view->vars["row_attr"]))
            return $view->vars["row_attr"][$name] = $value;

        $classList  = explode(" ", trim($view->vars["row_attr"][$name], " "));
        foreach(explode(" ", $value) as $class)
            $classList[] = $class;

        $view->vars["row_attr"][$name] = implode(" ", array_unique($classList));
        return $view->vars["row_attr"][$name];
    }

    public static function addLabelAttribute(FormView $view, $name, $value)
    {
        if (!array_key_exists("label_attr", $view->vars))
            $view->vars["label_attr"] = [];

        if (!array_key_exists($name, $view->vars["label_attr"]))
            return $view->vars["label_attr"][$name] = $value;

        $classList  = explode(" ", trim($view->vars["label_attr"][$name], " "));
        foreach(explode(" ", $value) as $class)
            $classList[] = $class;

    
        $view->vars["label_attr"][$name] = implode(" ", array_unique($classList));
        return $view->vars["label_attr"][$name];
    }
}
