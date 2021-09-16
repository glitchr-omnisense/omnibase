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
        $resolver->setDefaults(['bootstrap' => $this->defaultEnabled]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if(!$options["bootstrap"]) return;

        $formChildren = $form->all();
        foreach($view->children as $key => $viewChild) {

            if(!array_key_exists($key, $formChildren))
                continue; // (e.g. happends for CSRF _token)

            $this->finishView($viewChild, $formChildren[$key], $options);
        }

        if(count($view->children) == 0) {

            $attr = $view->vars["attr"];

            // Transfer label to placeholder attribute
            $label = (array_key_exists("label", $view->vars) && $view->vars["label"] != null)
                    ? $view->vars["label"]
                    : ucfirst($view->vars["name"]);

            // Add the most relevant class attribute to the <label> and <input> tags
            $type = explode("\\", get_class($form->getConfig()->getType()->getInnerType()));
            switch(end($type)) {

                case "CheckboxType":
                    self::addAttribute("class", "form-check-input", $view);
                    self::addLabelAttribute("class", "form-check-label", $view);
                    break;

                case "SubmitType":
                    self::addAttribute("class", "btn btn-primary", $view);
                    self::addLabelAttribute("class", "btn btn-primary", $view);
                    break;
    
                case "HiddenType":
                    break;

                default:
                    self::addAttribute("class", "form-control", $view);
            }

            if(!array_key_exists("placeholder", $attr) || $attr["placeholder"] == null)
                $view->vars["attr"]["placeholder"] = $label;

        }
    }

    public static function addAttribute($name, $value, FormView $view)
    {
        if (array_key_exists($name, $view->vars["attr"])) $view->vars["attr"][$name] .= " " . $value;
        else $view->vars["attr"][$name] = $value;
    }

    public static function addLabelAttribute($name, $value, FormView $view)
    {
        $view->vars["label_attr"] = $view->vars["label_attr"] ?? [];
        if (array_key_exists($name, $view->vars["label_attr"])) $view->vars["label_attr"][$name] .= " " . $value;
        else $view->vars["label_attr"][$name] = $value;
    }
}
