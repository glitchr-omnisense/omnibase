<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;

/**
 *
 */
class EditorEnhancer extends WysiwygEnhancer implements EditorEnhancerInterface
{
    public function supports(mixed $json): bool
    {
        return is_json($json);
    }

    public function render(mixed $json, array $options = []): string
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        return $this->twig->render("@Base/form/wysiwyg/editor_js.html.twig", ["json" => $json, "options" => $options]);
    }

    public function getTableOfContents(mixed $json, ?int $maxLevel = null): array
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        $headlines = [];

        foreach ($json->blocks ?? [] as $block) {

            if ($block->type != "header") continue;
            
            $block->data->text = "<h".$block->data->level.">".strip_tags($block->data->text)."</h".$block->data->level.">";
            $headlines = array_merge($headlines, $this->headingEnhancer->toc($block->data->text, $maxLevel));
        }

        return $headlines;
    }

    public function enhanceHeadings(mixed $json, ?int $maxLevel = null, array $attrs = []): mixed
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        foreach ($json->blocks ?? [] as $block) {

            if ($block->type != "header") continue;
    
            $block->data->text = "<h".$block->data->level.">".strip_tags($block->data->text)."</h".$block->data->level.">";
            $block->data->text = $this->headingEnhancer->enhance($block->data->text, $maxLevel, $attrs);
            $block->data->text = str_strip($block->data->text, "<h".$block->data->level.">", "</h".$block->data->level.">");
        }
        
        return $json;
    }

    public function enhanceSemantics(mixed $json, null|array|string $words = null, array $attrs = []): mixed
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        $attrs ??= [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-semantic");

        foreach (json_leaves($json) as &$block) {
            $block = $this->semanticEnhancer->enhance($block, $words, $attrs);
        }

        return json_encode($json);
    }

    public function enhanceMentions(mixed $json, array $attrs = []): mixed
    {
        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        $attrs ??= [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-mention");

        foreach (json_leaves($json) as &$block) {
            $block = $this->mentionEnhancer->enhance($block, $attrs);
        }
        
        return json_encode($json);
    }

    public function enhanceMedia(mixed $json, array $config = [], FilterInterface|array $filters = [], array $attrs = []): mixed
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        $attrs ??= [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-media");

        foreach ($json->blocks ?? [] as $block) {
       
            if ($block->type != "image") continue;

            $url = $block?->data?->file?->url;
            if($url) $block->data->file->url = $this->mediaEnhancer->enhance($url, $config, $filters, $attrs);
        }
        
        return $json;
    }
}
