<?php

namespace Base\Field\Type;

use Base\Entity\User;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\Collab\CollabRoomResolver;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class EditorType extends AbstractType
{
    /** @var Environment */
    protected Environment $twig;

    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;

    protected AdvancedRouterInterface $router;
    protected CsrfTokenManagerInterface $csrfTokenManager;
    protected ObfuscatorInterface $obfuscator;

    protected TranslatorInterface $translator;
    protected MediaEnhancerInterface $mediaEnhancer;

    protected CollabRoomResolver $roomResolver;
    protected TokenStorageInterface $tokenStorage;

    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator, Environment $twig, AdvancedRouterInterface $router, CsrfTokenManagerInterface $csrfTokenManager, ObfuscatorInterface $obfuscator, MediaEnhancerInterface $mediaEnhancer, CollabRoomResolver $roomResolver, TokenStorageInterface $tokenStorage)
    {
        $this->parameterBag = $parameterBag;
        $this->twig = $twig;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->obfuscator = $obfuscator;
        $this->translator = $translator;
        $this->mediaEnhancer = $mediaEnhancer;
        $this->roomResolver = $roomResolver;
        $this->tokenStorage = $tokenStorage;
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
            "webpack_entry" => "form.editor",

            // Optional, off by default, independently toggleable:
            //  - collab_autosave: debounced periodic save + optimistic-
            //    concurrency conflict guard via ux_editorjs_autosave. No
            //    WebSocket relay involved on its own.
            //  - collab_live: real-time presence/collaboration via the
            //    collab relay (collab-relay/ at the bundle root) — mints a
            //    join ticket via ux_editorjs_collabTicket. Setting this does
            //    not also require collab_autosave (the relay's own
            //    persistence bridge covers durable saving once that lands).
            // See Base\Controller\UX\EditorController::Autosave()/CollabTicket().
            "collab_autosave" => false,
            "collab_live"     => false,
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
        $data = $this->obfuscator->encode(["token" => $token], ObfuscatorInterface::NO_SHORT);

        // Captured before mediaEnhancer rewrites image block URLs below: the
        // autosave conflict guard's version hash must match what's actually
        // stored in the DB (raw origin URLs), not the display-enhanced JSON
        // sent to the browser, or every save touching an image block would
        // spuriously look like a conflict.
        $rawValue = $view->vars["value"];

        $value = $view->vars["value"];
        if(is_json($value)) {
            $value = \json_decode($value ?? "{}");
            if(!$value || !property_exists($value, "blocks")) {
                $value = (object) ["time" => time(), "blocks" => []];
            }
            
            foreach($value->blocks as $k => $block) {
                if ($block->type === "image" && property_exists($block->data, "file") && property_exists($block->data->file, "url")) {
                    $block->data->file->origin ??= $block->data->file->url; // @deprecated - to be removed in favor of "storageId" property later on
                    $block->data->file->url = $this->mediaEnhancer->enhance($block->data->file->origin, ["storage" => $this->parameterBag->get("base.twig.editor.storage")], [], []);
                    $value->blocks[$k] = $block;
                }
            }
        }

        $view->vars["value"] = (is_object($value) || is_array($value)) ? json_encode($value) : $view->vars["value"];
        $view->vars["uploadByFile"] = $this->router->generate("ux_editorjs_uploadByFile", ["data" => $data]);
        $view->vars["uploadByUrl"]  = $this->router->generate("ux_editorjs_uploadByUrl", ["data" => $data]);

        $view->vars["endpointByUser"]    = $this->router->generate("ux_editorjs_endpointByUser", ["data" => $data]);
        $view->vars["endpointByThread"]  = $this->router->generate("ux_editorjs_endpointByThread", ["data" => $data]);
        $view->vars["endpointByKeyword"] = $this->router->generate("ux_editorjs_endpointByKeyword", ["data" => $data]);

        // Optional collaboration block — only populated when at least one of
        // collab_autosave/collab_live is enabled AND the form's root data
        // resolves to a real mapped entity (embedded/collection forms
        // binding a DTO instead just get no "collab" key, i.e. plain
        // non-collaborative behavior).
        $collabAutosave = $options["collab_autosave"] ?? false;
        $collabLive     = $options["collab_live"] ?? false;
        if ($collabAutosave || $collabLive) {
            $entity = $this->roomResolver->resolveEntity($form->getRoot()->getData());
            if ($entity && method_exists($entity, "getId") && $entity->getId()) {
                // Locale-tab resolution for translatable content is not yet
                // wired here — defaults to a single, locale-agnostic room
                // until the active-locale-tab plumbing is traced (see plan's
                // open risk #2), so multi-locale forms should not enable
                // collab_autosave/collab_live until that lands.
                $locale = $view->vars["locale"] ?? null;

                $token = $this->csrfTokenManager->getToken("editorjs")->getValue();
                $currentUser = $this->tokenStorage->getToken()?->getUser();

                $editorOpts["collab"] = [
                    "autosave"    => $collabAutosave,
                    "live"        => $collabLive,
                    "fqcn"        => get_class($entity),
                    "id"          => $entity->getId(),
                    "field"       => $form->getName(),
                    "locale"      => $locale,
                    "room"        => $this->roomResolver->buildRoom(get_class($entity), $entity->getId(), $form->getName(), $locale),
                    "autosaveUrl" => $this->router->generate("ux_editorjs_autosave"),
                    "ticketUrl"   => $collabLive ? $this->router->generate("ux_editorjs_collabTicket") : null,
                    "token"       => $token,
                    "version"     => $this->roomResolver->hash($rawValue),
                    "user"        => $currentUser instanceof User ? [
                        "id"     => $currentUser->getId(),
                        "name"   => (string) $currentUser,
                        "avatar" => $currentUser->getAvatar(),
                        "color"  => $currentUser->getColor(),
                    ] : null,
                ];
            }
        }

        $view->vars["editor"] = json_encode($editorOpts);
    }
}
