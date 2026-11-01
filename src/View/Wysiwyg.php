<?php

namespace Base\View;

use Base\Service\EditorEnhancerInterface;
use Base\Service\Locator;
use Base\Service\WysiwygEnhancerInterface;

/**
 * A stored text rendered (plan "omnibase agnostic", step F4): an Editor.js document or the HTML of
 * the older editor, its mentions and links enhanced, its semantics if asked, its media, its
 * headings anchored - the pipeline of |wysiwyg and |wysiwyg_toc (HtmlTwigExtension::renderWysiwyg()
 * under Twig, the Texts contract of the screens: Base\Bridge\Symfony\WysiwygTexts), the same code
 * for Blade and a React page's props.
 */
final class Wysiwyg
{
    /**
     * @param string|null $storage the storage of the editor's media (base.twig.editor.storage); read from the parameters when none is given
     */
    public function __construct(
        private readonly WysiwygEnhancerInterface $wysiwygEnhancer,
        private readonly EditorEnhancerInterface $editorEnhancer,
        private readonly ?string $storage = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options headings (a level, or true), semantics, media (its config, or false), row_attr, hydrate
     *
     * @throws \RuntimeException for a text neither enhancer supports
     */
    public function render(?string $htmlOrJson, array $options = []): mixed
    {
        $enhancer = $this->enhancer($htmlOrJson);

        $htmlOrJson = $enhancer->enhanceMentions($htmlOrJson);
        $htmlOrJson = $enhancer->enhanceLinks($htmlOrJson);

        $applySemantics = array_pop_key('semantics', $options) ?? false;
        if ($applySemantics) {
            $htmlOrJson = $enhancer->enhanceSemantics($htmlOrJson);
        }

        $mediaConfig = array_pop_key('media', $options) ?? true;
        if ($mediaConfig) {
            $mediaConfig = \is_array($mediaConfig) ? $mediaConfig : [];
            $mediaConfig['storage'] ??= $this->storage ?? Locator::findParameterBag()?->get('base.twig.editor.storage');
            $htmlOrJson = $enhancer->enhanceMedia($htmlOrJson, $mediaConfig);
        }

        $maxHeadings = array_pop_key('headings', $options) ?? 0;
        if ($maxHeadings) {
            $htmlOrJson = $enhancer->enhanceHeadings($htmlOrJson, true === $maxHeadings ? null : $maxHeadings);
        }

        return $enhancer->render($htmlOrJson, ['attr' => $options['row_attr'] ?? [], 'hydrate' => $options['hydrate'] ?? true]);
    }

    /**
     * The text's headings.
     *
     * @return array<array-key, mixed>
     */
    public function toc(?string $htmlOrJson, ?int $maxLevel = null): array
    {
        return $this->enhancer($htmlOrJson)->getTableOfContents($htmlOrJson, $maxLevel);
    }

    private function enhancer(?string $htmlOrJson): WysiwygEnhancerInterface|EditorEnhancerInterface
    {
        if ($this->editorEnhancer->supports($htmlOrJson)) {
            return $this->editorEnhancer;
        }
        if ($this->wysiwygEnhancer->supports($htmlOrJson)) {
            return $this->wysiwygEnhancer;
        }

        throw new \RuntimeException('Unsupported wysiwyg input');
    }
}
