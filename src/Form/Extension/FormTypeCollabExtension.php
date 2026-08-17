<?php

namespace Base\Form\Extension;

use Base\Entity\User;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\Collab\CollabRoomResolver;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Opts any regular field (input/select/select2 — anything, not just
 * EditorType) into the same real-time presence/autosave machinery
 * EditorType uses, via a new `collab` form option: false (default) |
 * "autosave" | "live". Renders plain data-collab-* attributes rather than
 * touching form_div_layout.html.twig — Twig's own widget_attributes
 * rendering already outputs whatever finishView() puts in $view->vars
 * ["attr"], so every field type (including select2_widget's own attr
 * merge) picks these up automatically.
 *
 * form-type-collab-presence.js (assets/styles/js/forms/) is the client
 * side, reusing ux_editorjs_collabTicket/ux_editorjs_autosave — the same
 * generic endpoints EditorType uses, per the "one controller" decision in
 * the collaboration plan.
 */
class FormTypeCollabExtension extends AbstractTypeExtension
{
    protected CollabRoomResolver $roomResolver;
    protected TokenStorageInterface $tokenStorage;
    protected CsrfTokenManagerInterface $csrfTokenManager;
    protected AdvancedRouterInterface $router;

    public function __construct(CollabRoomResolver $roomResolver, TokenStorageInterface $tokenStorage, CsrfTokenManagerInterface $csrfTokenManager, AdvancedRouterInterface $router)
    {
        $this->roomResolver = $roomResolver;
        $this->tokenStorage = $tokenStorage;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->router = $router;
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['collab' => false]);
        $resolver->setAllowedValues('collab', [false, "autosave", "live"]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $collab = $options["collab"] ?? false;
        if (!$collab) {
            return;
        }

        $entity = $this->roomResolver->resolveEntity($form->getRoot()->getData());
        if (!$entity || !method_exists($entity, "getId") || !$entity->getId()) {
            return;
        }

        $locale = $view->vars["locale"] ?? null;
        $room = $this->roomResolver->buildRoom(get_class($entity), $entity->getId(), $form->getName(), $locale);

        $token = $this->csrfTokenManager->getToken("editorjs")->getValue();
        $currentUser = $this->tokenStorage->getToken()?->getUser();

        $collabAttr = [
            "data-collab-field" => $view->vars["id"],
            "data-collab-room"  => $room,
            "data-collab-mode"  => $collab,
            "data-collab-token" => $token,
            "data-collab-fqcn"  => get_class($entity),
            "data-collab-id"    => $entity->getId(),
            "data-collab-property" => $form->getName(),
            "data-collab-locale"   => $locale,
        ];

        if ($collab === "live") {
            $collabAttr["data-collab-ticket-url"] = $this->router->generate("ux_editorjs_collabTicket");
            $collabAttr["data-collab-user"] = $currentUser instanceof User ? json_encode([
                "name"  => (string) $currentUser,
                "color" => $currentUser->getColor(),
            ]) : "";
        }

        if ($collab === "autosave") {
            $collabAttr["data-collab-autosave-url"] = $this->router->generate("ux_editorjs_autosave");
        }

        $view->vars["attr"] = ($view->vars["attr"] ?? []) + $collabAttr;
    }
}
