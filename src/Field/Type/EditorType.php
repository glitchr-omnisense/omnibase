<?php

namespace Base\Field\Type;

use Base\Service\ParameterBagInterface;
use Base\Twig\Environment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class EditorType extends AbstractType
{
    /** @var Environment */
    protected $twig;

    /** @var ParameterBagInterface */
    protected $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag, Environment $twig)
    {
        $this->parameterBag = $parameterBag;
        $this->twig = $twig;
    }

    public function getBlockPrefix(): string
    {
        return 'editor';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'empty_data', null,

            'placeholder' => "Compose an epic..",
            'height' => "250px",

            "tools" => [
                
                "list" => [
                    "class" => "List",
                    "inlineToolbar" => "true",
                    "config" => [
                        "defaultStyle" => 'unordered'
                    ]
                ],
            ]
        ]);
    }

    public function getFormID($view): string
    {
        $parent = $view->parent;
        while ($parent->parent) {
            $parent = $parent->parent;
        }

        return $parent->vars["attr"]["id"] ?? null;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Editor options
        $editorOpts = [];
        $editorOpts["placeholder"] = $options["placeholder"];
        $editorOpts["height"] = $options["height"];
        
        $view->vars["editor"] = json_encode($editorOpts);
    }
}
