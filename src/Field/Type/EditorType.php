<?php

namespace Base\Field\Type;

use Base\Routing\RouterInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 *
 */
class EditorType extends AbstractType
{
    /** @var Environment */
    protected Environment $twig;

    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;

    protected RouterInterface $router;
    protected CsrfTokenManagerInterface $csrfTokenManager;
    protected ObfuscatorInterface $obfuscator;

    protected TranslatorInterface $translator;

    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator, Environment $twig, RouterInterface $router, CsrfTokenManagerInterface $csrfTokenManager, ObfuscatorInterface $obfuscator)
    {
        $this->parameterBag = $parameterBag;
        $this->twig = $twig;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->obfuscator = $obfuscator;
        $this->translator = $translator;
    }

    public function getParent(): ?string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'editor';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'empty_data', null,
            'placeholder' => $this->translator->trans("@fields.editor.placeholder"),
            "webpack_entry" => "form.editor"
        ]);
    }

    /**
     * @param $view
     * @return string|null
     */
    public function getFormID($view): ?string
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

        $token = $this->csrfTokenManager->getToken("editorjs")->getValue();
        $data = $this->obfuscator->encode(["token" => $token]);
        $view->vars["uploadByFile"] = $this->router->generate("ux_editorjs_endpointByFile", ["data" => $data]);
        $view->vars["uploadByUrl"] = $this->router->generate("ux_editorjs_endpointByUrl", ["data" => $data]);
        $view->vars["uploadByUser"] = $this->router->generate("ux_editorjs_endpointByUser", ["data" => $data]);
        $view->vars["uploadByThread"] = $this->router->generate("ux_editorjs_endpointByThread", ["data" => $data]);

        $view->vars["editor"] = json_encode($editorOpts);
    }
}
