<?php

namespace Base\Twig\Extension;

use Base\Twig\Renderer\Adapter\EncoreTagRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 *
 */
final class EncoreTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('encore_entry_link_tags', [EncoreTagRenderer::class, 'renderLinkTags'], ['is_safe' => ['all']]),
            new TwigFunction('encore_entry_script_tags', [EncoreTagRenderer::class, 'renderScriptTags'], ['is_safe' => ['all']]),
            new TwigFunction('encore_entry_css_source', [EncoreTagRenderer::class, 'renderCssSource'], ['is_safe' => ['all']]),
        ];
    }
}
