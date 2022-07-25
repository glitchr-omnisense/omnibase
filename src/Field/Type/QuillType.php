<?php

namespace Base\Field\Type;

use Base\Service\ParameterBagInterface;
use Base\Twig\Environment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Traversable;

class QuillType extends AbstractType
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

    public function getParent(): ?string { return HiddenType::class; }
    public function getBlockPrefix(): string { return 'quill'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'highlight-js'  => $this->parameterBag->get("base.vendor.highlight.javascript"),
            'highlight-css' => $this->parameterBag->get("base.vendor.highlight.stylesheet"),
            'quill-js'      => $this->parameterBag->get("base.vendor.quill.javascript"),
            'quill-css'     => $this->parameterBag->get("base.vendor.quill.stylesheet"),
            'empty_data', null,

            'theme' => $this->parameterBag->get("base.vendor.quill.theme"),
            'placeholder' => "Compose an epic..",
            'height' => "250px",
            'modules' => [
                "syntax" => true,
                "toolbar" => [
                    [
                        ["header" => [1, 2, false]]
                    ],
                    ['bold', 'italic', 'underline', 'strike'],
                    [
                        ['color' => []],
                        ['background' => []],
                        ['script' => 'sub'],
                        ['script' => 'super']
                    ],

                    ['image', 'link', 'blockquote', 'code-block'],

                    [
                        [ 'list' => 'ordered'],
                        [ 'list' => 'bullet' ],
                        ['align' => []],
                    ],

                    ['clean']
                ]
            ]
        ]);
    }

    public function getFormID($view): string
    {
        $parent = $view->parent;
        while($parent->parent)
            $parent = $parent->parent;

        return $parent->vars["attr"]["id"] ?? null;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // Import highlight
        $this->twig->addHtmlContent("javascripts:head", $options["highlight-js"]);
        $this->twig->addHtmlContent("stylesheets:before", $options["highlight-css"]);

        // Import quill
        $this->twig->addHtmlContent("javascripts:head", $options["quill-js"]);

        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Quill options
        $theme = $options["theme"];
        $themeCssFile = dirname($options["quill-css"]) . "/quill." . $theme . ".css";
        if (preg_match("/.*\/quill.(.*).css/", $theme, $themeArray)) {

            $theme = $themeArray[1];
            $themeCssFile = $themeArray[0];
        }

        $this->twig->addHtmlContent("stylesheets:before", $themeCssFile);
        $modules = $options["modules"] ?? [];

        $quillOpts = [];
        $quillOpts["theme"] = $theme;
        $quillOpts["modules"] = $modules;
        $quillOpts["placeholder"] = $options["placeholder"];
        $quillOpts["height"] = $options["height"];

        $view->vars["quill"] = json_encode($quillOpts);

        //
        // Default quill initialializer
        $this->twig->addHtmlContent("javascripts:body", "bundles/base/form-type-quill.js");
    }
}
