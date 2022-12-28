<?php

namespace Base\Twig\Extension;

use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HtmlTwigExtension extends AbstractExtension
{
    /**
     * @var HtmlTagRenderer
     */
    protected $htmlTagRenderer;

    public function __construct(HtmlTagRenderer $htmlTagRenderer)
    {
        $this->htmlTagRenderer = $htmlTagRenderer;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('html_entry_link_tags',   [$this, 'renderLinkTags'  ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
            new TwigFunction('html_entry_script_tags',   [$this, 'renderScriptTags'  ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),

            new TwigFunction('html_entry_head_tags',   [$this, 'renderHeadTags'  ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
            new TwigFunction('html_entry_noscript_tags',  [$this, 'renderNoscriptTags' ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
            new TwigFunction('html_entry_body_tags',  [$this, 'renderBodyTags' ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
        ];
    }

    public function renderLinkTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent("stylesheet:before").
               $this->htmlTagRenderer->renderHtmlContent("stylesheet").
               $this->htmlTagRenderer->renderHtmlContent("stylesheet:after");
    }

    public function renderScriptTags(): string
    {
        return  $this->htmlTagRenderer->renderHtmlContent("javascript:head").
                $this->htmlTagRenderer->renderHtmlContent("javascript").
                $this->htmlTagRenderer->renderHtmlContent("javascript:body");
    }

    public function renderHeadTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent("stylesheet:before").
               $this->htmlTagRenderer->renderHtmlContent("stylesheet").
               $this->htmlTagRenderer->renderHtmlContent("stylesheet:after").
               
               $this->htmlTagRenderer->renderHtmlContent("javascript:head").
               $this->htmlTagRenderer->renderHtmlContent("javascript");
    }

    public function renderNoscriptTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent("noscripts");
    }

    public function renderBodyTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent("javascript:body");
    }
}
