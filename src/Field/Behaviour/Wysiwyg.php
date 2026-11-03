<?php

namespace Base\Field\Behaviour;

/**
 * Kind::WYSIWYG: an HTML text written in Quill (forms/form-type-wysiwyg.js), kept in a hidden input -
 * what omnibridge/symfony's WysiwygType decided, which it now asks. Its settings are Quill's: the theme,
 * the toolbar's modules, the placeholder, the height; no route. The widget's id has its dashes made
 * underscores (a translation's fr-FR part: Quill's script reads the id as a name).
 */
final class Wysiwyg extends AbstractBehaviour
{
    /** Quill's toolbar, as WysiwygType gives it. */
    public const MODULES = [
        'syntax' => true,
        'toolbar' => [
            [['header' => [1, 2, false]]],
            ['bold', 'italic', 'underline', 'strike'],
            [['color' => []], ['background' => []], ['script' => 'sub'], ['script' => 'super']],
            ['image', 'link', 'blockquote', 'code-block'],
            [['list' => 'ordered'], ['list' => 'bullet'], ['align' => []]],
            ['clean'],
        ],
    ];

    protected function defaults(): array
    {
        return [
            'empty_data' => null,
            'webpack_entry' => 'form.wysiwyg',
            'theme' => 'snow',
            'placeholder' => self::trans('@fields.wysiwyg.placeholder'),
            'height' => '250px',
            'modules' => self::MODULES,
        ];
    }

    /** The widget's id (dashes made underscores) and Quill's settings as the script reads them (`wysiwyg`, JSON). */
    public function vars(array $options, mixed $shown, string $id): array
    {
        return [
            'id' => str_replace('-', '_', $id),
            'wysiwyg' => json_encode(['theme' => $options['theme'], 'modules' => $options['modules'] ?? [], 'placeholder' => $options['placeholder'], 'height' => $options['height']]),
        ];
    }

    protected function controller(): ?string
    {
        return 'wysiwyg';
    }
}
