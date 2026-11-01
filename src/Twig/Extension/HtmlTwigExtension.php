<?php

namespace Base\Twig\Extension;

use Base\Service\EditorEnhancerInterface;
use Base\Service\WysiwygEnhancerInterface;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Base\View\Wysiwyg;

final class HtmlTwigExtension extends AbstractExtension
{
    protected HtmlTagRenderer $htmlTagRenderer;

    protected WysiwygEnhancerInterface $wysiwygEnhancer;
    protected EditorEnhancerInterface $editorEnhancer;

    public function __construct(HtmlTagRenderer $htmlTagRenderer, WysiwygEnhancerInterface $wysiwygEnhancer, EditorEnhancerInterface $editorEnhancer)
    {
        $this->htmlTagRenderer = $htmlTagRenderer;
        $this->wysiwygEnhancer = $wysiwygEnhancer;
        $this->editorEnhancer = $editorEnhancer;
        $this->wysiwyg = new Wysiwyg($wysiwygEnhancer, $editorEnhancer);
    }

    private readonly Wysiwyg $wysiwyg;

    /** The neutral pipeline behind |wysiwyg (the screens' Texts contract: Base\Bridge\Symfony\WysiwygTexts). */
    public function wysiwyg(): Wysiwyg
    {
        return $this->wysiwyg;
    }

    /**
     * The pipeline of `|wysiwyg` (Base\View\Wysiwyg; TextsTwigExtension, through the Texts
     * contract: Base\Bridge\Symfony\WysiwygTexts under Symfony).
     *
     * @param string|null $htmlOrJson
     * @param array $options
     * @return string
     */
    public function renderWysiwyg(?string $htmlOrJson, array $options = [])
    {
        return $this->wysiwyg->render($htmlOrJson, $options);
    }

    public function getTableOfContents(?string $htmlOrJson, ?int $maxLevel = null): array
    {
        return $this->wysiwyg->toc($htmlOrJson, $maxLevel);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('html_entry_link_tags', [$this, 'renderLinkTags'], ['is_safe' => ['all'], 'needs_environment' => true, 'raw' => true]),
            new TwigFunction('html_entry_script_tags', [$this, 'renderScriptTags'], ['is_safe' => ['all'], 'needs_environment' => true, 'raw' => true]),

            new TwigFunction('html_entry_head_tags', [$this, 'renderHeadTags'], ['is_safe' => ['all'], 'needs_environment' => true, 'raw' => true]),
            new TwigFunction('html_entry_noscript_tags', [$this, 'renderNoscriptTags'], ['is_safe' => ['all'], 'needs_environment' => true, 'raw' => true]),
            new TwigFunction('html_entry_body_tags', [$this, 'renderBodyTags'], ['is_safe' => ['all'], 'needs_environment' => true, 'raw' => true]),
        ];
    }

    public function renderLinkTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent('stylesheet:before') .
            $this->htmlTagRenderer->renderHtmlContent('stylesheet') .
            $this->htmlTagRenderer->renderHtmlContent('stylesheet:after');
    }

    public function renderScriptTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent('javascript:head') .
            $this->htmlTagRenderer->renderHtmlContent('javascript') .
            $this->htmlTagRenderer->renderHtmlContent('javascript:body');
    }

    public function renderHeadTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent('stylesheet:before') .
            $this->htmlTagRenderer->renderHtmlContent('stylesheet') .
            $this->htmlTagRenderer->renderHtmlContent('stylesheet:after') .

            $this->htmlTagRenderer->renderHtmlContent('javascript:head') .
            $this->htmlTagRenderer->renderHtmlContent('javascript');
    }

    public function renderNoscriptTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent('noscripts');
    }

    public function renderBodyTags(): string
    {
        return $this->htmlTagRenderer->renderHtmlContent('javascript:body');
    }
}
