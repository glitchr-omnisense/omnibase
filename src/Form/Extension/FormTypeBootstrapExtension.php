<?php

namespace Base\Form\Extension;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Form\AbstractTypeExtension;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;


class FormTypeBootstrapExtension extends AbstractTypeExtension
{
    protected $defaultEnabled;
    public function __construct(bool $defaultEnabled = true)
    {
        $this->defaultEnabled = $defaultEnabled;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'bootstrap' => $this->defaultEnabled,
            'bootstrap_label' => true
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if(!$options["bootstrap"]) return;

        $this->browseView( $view, $form, $options);
    }

    public function browseView(FormView $view, FormInterface $form, array $options)
    {
        foreach($view->children as $field => $childView) {

            if (!$form->has($field))
                continue;
                
            $childForm = $form->get($field);
            $childOptions = $childForm->getConfig()->getOptions();

            $type = explode("\\", get_class($childForm->getConfig()->getType()->getInnerType()));
            $attr = $childView->vars["attr"];
            $label = (array_key_exists("label", $childView->vars) && $childView->vars["label"] != null)
                    ? $childView->vars["label"]
                    : ucfirst($childView->vars["name"]);

            switch(end($type)) {

                case "CheckboxType":
                    $this->addAttribute($childView, "class", "form-switch form-switch-lg form-check-input");
                    $this->addRowAttribute($childView, "class", "form-switch form-switch-lg form-check");
                    $this->addLabelAttribute($childView, "class", "form-check-label");
                    break;

                case "SubmitType":
                    $this->addAttribute($childView, "class", "btn btn-primary");
                    $this->addLabelAttribute($childView, "class", "btn btn-primary");
                    break;
    
                case "HiddenType":
                    break;

                default:
                    $this->addAttribute($childView, "class", "form-control");
                    $this->addRowAttribute($childView, "class", "form-group");

                    if(!$options["bootstrap_label"]) $childView->vars["label"] = false;
            }

            if(!array_key_exists("placeholder", $attr) || $attr["placeholder"] == null) {
                if(!$options["bootstrap_label"]) $childView->vars["attr"]["placeholder"] = $label;
            }

            $this->browseView($childView, $childForm, $childOptions);
        }
    }
    
    public function addAttribute(FormView $view, $name, $value)
    {
        if (!array_key_exists($name, $view->vars["attr"]))
            return $view->vars["attr"][$name] = $value;

        $classList  = explode(" ", trim($view->vars["attr"][$name], " "));
        foreach(explode(" ", $value) as $class)
            $classList[] = $class;

        $view->vars["attr"][$name] = implode(" ", array_unique($classList));
        return $view->vars["attr"][$name];
    }

    public function addRowAttribute(FormView $view, $name, $value)
    {
        if (!array_key_exists($name, $view->vars["row_attr"]))
            return $view->vars["row_attr"][$name] = $value;

        $classList  = explode(" ", trim($view->vars["row_attr"][$name], " "));
        foreach(explode(" ", $value) as $class)
            $classList[] = $class;

        $view->vars["row_attr"][$name] = implode(" ", array_unique($classList));
        return $view->vars["row_attr"][$name];
    }

    public function addLabelAttribute(FormView $view, $name, $value)
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
