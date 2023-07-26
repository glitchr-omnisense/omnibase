<?php

namespace Base\Twig\Extension;

use Base\Twig\Renderer\Adapter\WebpackTagRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 *
 */
final class WebpackTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('encore_entry_link_tags', [WebpackTagRenderer::class, 'renderLinkTags'], ['is_safe' => ['all']]),
            new TwigFunction('encore_entry_script_tags', [WebpackTagRenderer::class, 'renderScriptTags'], ['is_safe' => ['all']]),
            new TwigFunction('encore_entry_css_source', [WebpackTagRenderer::class, 'renderCssSource'], ['is_safe' => ['all']]),
        ];
    }
}
