<?php

namespace Base\Field\Behaviour;

use Base\Field\Editor\EditorJs;
use Omnibridge\Form\Endpoint;
use Omnibridge\Runtime;

/**
 * Kind::EDITOR: an Editor.js document (forms/form-type-editor.js) kept as JSON in a hidden input - what
 * omnibridge/symfony's EditorType decided, which it now asks.
 *
 * - options(): the placeholder, the script's entry, collab_autosave and collab_live (a debounced save
 *   through ux_editorjs_autosave; a relay's live editing, its ticket through ux_editorjs_collabTicket).
 * - vars(): the widget's id (dashes made underscores), the value with each picture's address made from
 *   its stored origin in the editor's storage (base.twig.editor.storage, omnibase's MediaEnhancer), the
 *   five addresses of the editor's routes with their token (Base\Field\Editor\EditorJs), and `editor`
 *   (JSON): the placeholder and, for a record the persistence maps when a collaboration is asked, the
 *   room of that field (its token, its version hash, the account signed in). The record, the field's
 *   name and its language are given by the host: editor_record, editor_field, editor_locale.
 * - reverse(): a document without a block is no text (null).
 */
final class Editor extends AbstractBehaviour
{
    protected function defaults(): array
    {
        return [
            'empty_data' => null,
            'placeholder' => self::trans('@fields.editor.placeholder'),
            'webpack_entry' => 'form.editor',
            // Both optional, each on its own: collab_autosave saves the field as it is written (EditorJs::autosave(),
            // its version hash refusing a stale save), collab_live joins a relay's room (EditorJs::ticket()).
            'collab_autosave' => false,
            'collab_live' => false,
        ];
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        $editor = ['placeholder' => $options['placeholder']];
        // The raw value's hash, before the pictures' addresses are rewritten below: the autosave compares it with the stored one.
        $raw = $shown;

        $value = $shown;
        $editorJs = Runtime::find(EditorJs::class);
        if (is_json($value)) {
            $value = json_decode($value ?? '{}');
            if (!$value || !property_exists($value, 'blocks')) {
                $value = (object) ['time' => time(), 'blocks' => []];
            }
            foreach ($value->blocks as $k => $block) {
                if ('image' === $block->type && property_exists($block->data, 'file') && property_exists($block->data->file, 'url')) {
                    $block->data->file->origin ??= $block->data->file->url; // @deprecated - to be removed in favor of "storageId" property later on
                    if ($editorJs instanceof EditorJs) {
                        $block->data->file->url = $editorJs->picture($block->data->file->origin);
                    }
                    $value->blocks[$k] = $block;
                }
            }
        }

        $addresses = $editorJs instanceof EditorJs ? $editorJs->addresses() : array_fill_keys(array_keys(EditorJs::ADDRESSES), null);
        if ($editorJs instanceof EditorJs && null !== $collab = self::collab($editorJs, $options, $raw)) {
            $editor['collab'] = $collab;
        }

        return [
            'id' => str_replace('-', '_', $id),
            'value' => (\is_object($value) || \is_array($value)) ? json_encode($value) : $shown,
            'editor' => json_encode($editor),
        ] + $addresses;
    }

    /** An empty document (no block) is no text. */
    public function reverse(mixed $submitted, array $options): mixed
    {
        $json = \is_string($submitted) ? json_decode($submitted) : null;
        if (\is_object($json) && \is_array($json->blocks ?? null) && \count($json->blocks) < 1) {
            return null;
        }

        return $submitted;
    }

    public function endpoints(array $options): array
    {
        $endpoints = [];
        foreach (EditorJs::ADDRESSES as $var => $route) {
            $endpoints[$var] = new Endpoint($route, [], 'POST');
        }
        if ($options['collab_autosave'] ?? false) {
            $endpoints['autosave'] = new Endpoint('ux_editorjs_autosave', [], 'POST');
        }
        if ($options['collab_live'] ?? false) {
            $endpoints['collabTicket'] = new Endpoint('ux_editorjs_collabTicket', [], 'POST');
        }

        return $endpoints;
    }

    protected function controller(): ?string
    {
        return 'editor';
    }

    /**
     * The collaboration's room of this field (EditorType's `collab`), only when one is asked and the form's
     * data is a record the persistence maps, already stored; collab_live only where a relay is configured
     * (otherwise its ticket would be refused on every edit screen).
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    private static function collab(EditorJs $editorJs, array $options, mixed $raw): ?array
    {
        $autosave = (bool) ($options['collab_autosave'] ?? false);
        $live = (bool) ($options['collab_live'] ?? false);
        if ($live && !$editorJs->tickets()?->isConfigured()) {
            $live = false;
        }
        if (!$autosave && !$live) {
            return null;
        }
        $rooms = $editorJs->rooms();
        $entity = $rooms->resolveEntity($options['editor_record'] ?? null);
        if (!$entity || !method_exists($entity, 'getId') || !$entity->getId()) {
            return null;
        }
        // One room per field, its language apart (a multi-language form does not ask for a collaboration yet).
        $locale = $options['editor_locale'] ?? null;
        $field = (string) ($options['editor_field'] ?? '');
        $room = $rooms->buildRoom(\get_class($entity), $entity->getId(), $field, $locale);

        return [
            'autosave' => $autosave,
            'live' => $live,
            'fqcn' => \get_class($entity),
            'id' => $entity->getId(),
            'field' => $field,
            'locale' => $locale,
            'room' => $room,
            'autosaveUrl' => $editorJs->address('ux_editorjs_autosave'),
            'ticketUrl' => $live ? $editorJs->address('ux_editorjs_collabTicket') : null,
            // This room's token, not the session-wide "editorjs": it writes this field only.
            'token' => $editorJs->roomToken($room),
            'version' => $rooms->hash($raw),
            'user' => $editorJs->author(),
        ];
    }
}
