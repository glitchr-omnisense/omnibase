<?php

namespace Base\Service;

use Base\Twig\Environment;
use Symfony\Component\String\Slugger\SluggerInterface;

class WysiwygEnhancer implements WysiwygEnhancerInterface
{
    protected $twig;
    protected $slugger;
    protected $semanticEnhancer;

    public function __construct(Environment $twig, SluggerInterface $slugger, SemanticEnhancerInterface $semanticEnhancer)
    {
        $this->twig = $twig;
        $this->slugger = $slugger;
        $this->semanticEnhancer = $semanticEnhancer;
    }

    public function supports(mixed $html): bool
    {
        return is_string($html) || is_array($html) || $html === null;
    }

    public function render(mixed $html, array $options = []): string
    {
        if ($html === null) {
            return "";
        }

        return $this->twig->render("@Base/form/wysiwyg/quill.html.twig", ["html" => $html, "options" => $options]);
    }

    public function highlightHeadings(mixed $html, ?int $maxLevel = null, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }
        if (is_array($html)) {
            $toc = [];
            foreach ($html as $htmlEntry) {
                $toc[] = $this->highlightHeadings($htmlEntry, $maxLevel, $attrs);
            }

            return $toc;
        }

        $maxLevel ??= 6;
        $encoding = mb_detect_encoding($html);

        $dom = new \DOMDocument('1.0', $encoding);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', $encoding));

        $attrs ??= [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-anchor");

        for ($i = 1; $i <= $maxLevel; $i++) {
            $hX = "h" . $i;
            $tags = $dom->getElementsByTagName($hX);
            foreach ($tags as $tag) {
                $content = $tag->nodeValue;
                $tag->nodeValue = null;

                $id = strtolower($this->slugger->slug($content));
                $tag->setAttribute("id", $id);

                $template = $dom->createDocumentFragment();
                $template->appendXML("<a " . html_attributes($attrs) . " href='#" . $id . "'>" . $content . "</a>");

                $tag->appendChild($template);
            }
        }

        $node = $dom->getElementsByTagName('body')->item(0);
        return trim(implode(array_map([$node->ownerDocument, "saveHTML"], iterator_to_array($node->childNodes))));
    }

    public function highlightSemantics(mixed $html, null|array|string $words = null, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {
            $htmlRet = [];
            foreach ($html as $htmlEntry) {
                $htmlRet[] = $this->semanticEnhancer->highlight($htmlEntry, $words, $attrs);
            }

            return $htmlRet;
        }

        return $this->semanticEnhancer->highlight($html, $words, $attrs);
    }

    public function getTableOfContents(mixed $html, ?int $maxLevel = null): array
    {
        if ($html === null) {
            return [];
        }

        if (is_array($html)) {
            $toc = [];
            foreach ($html as $htmlEntry) {
                $toc[] = $this->getTableOfContents($htmlEntry, $maxLevel);
            }

            return $toc;
        }

        $maxLevel ??= 6;

        $headlines = [];
        preg_replace_callback("/\<[ ]*(h[1-" . $maxLevel . "])(?:[^\<\>]*)\>([^\<\>]*)\<\/[ ]*h[1-" . $maxLevel . "][ ]*\>/", function ($match) use (&$headlines) {
            $headlines[] = [
                "tag" => $match[1],
                "slug" => strtolower($this->slugger->slug($match[2])),
                "title" => $match[2]
            ];
        }, $html);

        return $headlines;
    }
}
