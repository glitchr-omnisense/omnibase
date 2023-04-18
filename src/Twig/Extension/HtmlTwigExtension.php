<?php

namespace Base\Twig\Extension;

use Base\Service\EditorEnhancerInterface;
use Base\Service\WysiwygEnhancerInterface;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use RuntimeException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class HtmlTwigExtension extends AbstractExtension
{
    /**
     * @var HtmlTagRenderer
     */
    protected $htmlTagRenderer;

    protected $wysiwygEnhancer;
    protected $editorEnhancer;

    public function __construct(HtmlTagRenderer $htmlTagRenderer, WysiwygEnhancerInterface $wysiwygEnhancer, EditorEnhancerInterface $editorEnhancer)
    {
        $this->htmlTagRenderer = $htmlTagRenderer;
        $this->wysiwygEnhancer  = $wysiwygEnhancer; 
        $this->editorEnhancer  = $editorEnhancer; 
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('wysiwyg', [$this, 'renderWysiwyg']),
            new TwigFilter('wysiwyg_toc', [$this, 'getTableOfContents']),
        ];
    }
    
    public function renderWysiwyg(?string $htmlOrJson, array $options = [], ?int $maxLevel = null) 
    {
        if($this->editorEnhancer->supports($htmlOrJson))
            $enhancer = $this->editorEnhancer;
        else if($this->wysiwygEnhancer->supports($htmlOrJson))
            $enhancer = $this->wysiwygEnhancer;
        else throw new RuntimeException("Unsupported wysiwyg input");
        
        $applySemantics = array_pop_key("semantics", $options);
        if($applySemantics)
            $htmlOrJson = $enhancer->highlightSemantics($htmlOrJson);
        
        $applyHeadings = array_pop_key("headings", $options);
        if($applyHeadings)
            $htmlOrJson = $enhancer->highlightHeadings($htmlOrJson, $maxLevel);

        return $enhancer->render($htmlOrJson, ["attr" => $options["row_attr"] ?? []]);
    }

    public function getTableOfContents(?string $htmlOrJson, ?int $maxLevel = null): array
    {
        if($this->editorEnhancer->supports($htmlOrJson))
            $enhancer = $this->editorEnhancer;
        else if($this->wysiwygEnhancer->supports($htmlOrJson))
            $enhancer = $this->wysiwygEnhancer;
        else throw new RuntimeException("Unsupported wysiwyg input");

        return $enhancer->getTableOfContents($htmlOrJson, $maxLevel);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('html_entry_link_tags', [$this, 'renderLinkTags'  ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
            new TwigFunction('html_entry_script_tags', [$this, 'renderScriptTags'  ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),

            new TwigFunction('html_entry_head_tags', [$this, 'renderHeadTags'  ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
            new TwigFunction('html_entry_noscript_tags', [$this, 'renderNoscriptTags' ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
            new TwigFunction('html_entry_body_tags', [$this, 'renderBodyTags' ], ["is_safe" => ['all'], 'needs_environment' => true, "raw" => true]),
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
