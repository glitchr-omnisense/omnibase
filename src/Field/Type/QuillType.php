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
            'empty_data', null,

            'theme' => "snow",
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
        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Quill options
        $theme = $options["theme"];
        $modules = $options["modules"] ?? [];

        $quillOpts = [];
        $quillOpts["theme"] = $theme;
        $quillOpts["modules"] = $modules;
        $quillOpts["placeholder"] = $options["placeholder"];
        $quillOpts["height"] = $options["height"];

        $view->vars["quill"] = json_encode($quillOpts);
    }
}
