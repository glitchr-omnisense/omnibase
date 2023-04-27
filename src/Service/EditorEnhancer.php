<?php

namespace Base\Service;

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

    public function highlightHeadings(mixed $json, ?int $maxLevel = null, array $attrs = []): mixed
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        $attrs = [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-anchor");

        $maxLevel ??= 6;
        foreach ($json->blocks ?? [] as $block) {
            if ($block->type == "header" && $block->data->level < $maxLevel) {
                $text = $block->data->text;
                $id = $this->slugger->slug(str_strip_nonprintable(strip_tags($text)));

                $block->data->text = "<a id='".$id."' ".html_attributes($attrs). " href='#".$id."'>".strip_tags($text)."</a>";
            }
        }

        return $json;
    }

    public function highlightSemantics(mixed $json, null|array|string $words = null, array $attrs = []): mixed
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        $attrs = [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-semantic");

        foreach ($json->blocks ?? [] as $block) {
            if ($block->type == "paragraph") {
                $block->data->text = $this->semanticEnhancer->highlight($block->data->text, $words, $attrs);
            }
        }

        return $json;
    }

    public function getTableOfContents(mixed $json, ?int $maxLevel = null): array
    {
        if (is_string($json)) {
            $json = json_decode($json);
        }

        $headlines = [];

        $maxLevel ??= 6;
        foreach ($json->blocks ?? [] as $block) {
            if ($block->type == "header" && $block->data->level < $maxLevel) {
                $text = $block->data->text;
                $id = $this->slugger->slug(str_strip_nonprintable(strip_tags($text)));

                $headlines[] = [
                        "tag" => "h".$block->data->level,
                        "slug"  => $id,
                        "title" => str_strip_nonprintable(strip_tags($text))
                ];
            }
        }

        return $headlines;
    }
}
