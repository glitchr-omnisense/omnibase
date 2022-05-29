<?php

namespace Base\Field\Type;

use Base\Service\BaseService;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Traversable;

class QuillType extends AbstractType implements DataMapperInterface
{
    /** @var BaseService */
    protected $baseService;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public function getParent(): ?string { return HiddenType::class; }
    public function getBlockPrefix(): string { return 'quill'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'highlight-js'  => $this->baseService->getParameterBag("base.vendor.highlight.javascript"),
            'highlight-css' => $this->baseService->getParameterBag("base.vendor.highlight.stylesheet"),
            'quill-js'      => $this->baseService->getParameterBag("base.vendor.quill.javascript"),
            'quill-css'     => $this->baseService->getParameterBag("base.vendor.quill.stylesheet"),
            'empty_data', null,

            'theme' => $this->baseService->getParameterBag("base.vendor.quill.theme"),
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
        $this->baseService->addHtmlContent("javascripts:head", $options["highlight-js"]);
        $this->baseService->addHtmlContent("stylesheets:head", $options["highlight-css"]);

        // Import quill
        $this->baseService->addHtmlContent("javascripts:head", $options["quill-js"]);

        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Quill options
        $theme = $options["theme"];
        $themeCssFile = dirname($options["quill-css"]) . "/quill." . $theme . ".css";
        if (preg_match("/.*\/quill.(.*).css/", $theme, $themeArray)) {

            $theme = $themeArray[1];
            $themeCssFile = $themeArray[0];
        }

        $this->baseService->addHtmlContent("stylesheets:head", $themeCssFile);
        $modules = $options["modules"] ?? [];

        $quillOpts = [];
        $quillOpts["theme"] = $theme;
        $quillOpts["modules"] = $modules;
        $quillOpts["placeholder"] = $options["placeholder"];
        $quillOpts["height"] = $options["height"];

        $view->vars["quill"] = json_encode($quillOpts);

        //
        // Default quill initialializer
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-quill.js");
    }

    public function mapDataToForms($viewData, Traversable $forms) { }
    public function mapFormsToData(Traversable $forms, &$viewData) { }
}
