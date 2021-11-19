<?php

namespace Base\Form\Extension;

use Base\Service\BaseService;
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


class FormTypeBaseExtension extends AbstractTypeExtension
{
    protected $defaultEnabled;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
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
            'form2' => $this->baseService->getParameterBag("base.twig.use_form2"),
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

            if($options["form2"]) $this->applyForm2($childView, $childForm, $options);
            if($options["bootstrap"]) $this->applyBootstrap($childView, $childForm, $options);

            $this->browseView($childView, $childForm, $childOptions);
        }
    }

    public function applyForm2(FormView $view, FormInterface $form, array $options)
    {
        // Add to all form custom base style.. 
        // It is named form2 and blocks are available in ./templates/form/form_div_layout.html.twig
        
        if (array_search("form" , $view->vars['block_prefixes']) !== false && 
            array_search("form2", $view->vars['block_prefixes']) === false)
            array_splice($view->vars['block_prefixes'], 1, 0, ["form2"]);
    }

    public function applyBootstrap(FormView $view, FormInterface $form, array $options)
    {
        $type = explode("\\", get_class($form->getConfig()->getType()->getInnerType()));
            
            $label = $view->vars["label"] ?? null;
            if($label === null) $label = ucfirst((string) $view->vars["name"]);

            $attr = $view->vars["attr"];
           
            switch(end($type)) {

                case "CheckboxType":
                    self::addAttribute($view, "class", "form-switch form-switch-lg form-check-input");
                    self::addRowAttribute($view, "class", "form-switch form-switch-lg form-check");
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
