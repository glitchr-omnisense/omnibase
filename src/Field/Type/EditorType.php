<?php

namespace Base\Field\Type;

use Base\Routing\RouterInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Twig\Environment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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

    public function configureOptions(OptionsResolver $resolver): void
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

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {
            
            $form = $event->getForm();
            $data = $event->getData();

            $json = json_decode($data);
            if($json && count($json->blocks) < 1) {
                $event->setData(null);
            }

        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Editor options
        $editorOpts = [];
        $editorOpts["placeholder"] = $options["placeholder"];

        $token = $this->csrfTokenManager->getToken("editorjs")->getValue();
        $data = $this->obfuscator->encode(["token" => $token]);

        $view->vars["uploadByFile"] = $this->router->generate("ux_editorjs_uploadByFile", ["data" => $data]);
        $view->vars["uploadByUrl"]  = $this->router->generate("ux_editorjs_uploadByUrl", ["data" => $data]);

        $view->vars["endpointByUser"]    = $this->router->generate("ux_editorjs_endpointByUser", ["data" => $data]);
        $view->vars["endpointByThread"]  = $this->router->generate("ux_editorjs_endpointByThread", ["data" => $data]);
        $view->vars["endpointByKeyword"] = $this->router->generate("ux_editorjs_endpointByKeyword", ["data" => $data]);

        $view->vars["editor"] = json_encode($editorOpts);
    }
}
