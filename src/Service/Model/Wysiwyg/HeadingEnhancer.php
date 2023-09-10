<?php

namespace Base\Service\Model\Wysiwyg;

use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 *
 */
class HeadingEnhancer implements HeadingEnhancerInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var SluggerInterface
     */
    protected SluggerInterface $slugger;

    public function __construct(EntityManagerInterface $entityManager, SluggerInterface $slugger)
    {
        $this->entityManager = $entityManager; 
        $this->slugger = $slugger;
    }

    public function toc(mixed $html, ?int $maxLevel = null): array
    {
        if (!$html) {
            return $html;
        }

        if (is_array($html)) {
            $toc = [];
            foreach ($html as $htmlEntry) {
                $toc[] = $this->toc($htmlEntry, $maxLevel);
            }

            return $toc;
        }

        $maxLevel ??= 6;
        $maxLevel = max(1, $maxLevel);

        $encoding = mb_detect_encoding($html);
        $dom = new DOMDocument('1.0', $encoding);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', $encoding), LIBXML_NOERROR);

        $headlines = [];
        for ($i = 1; $i <= $maxLevel; $i++) {

            $tagName = "h" . $i;
            $tags = $dom->getElementsByTagName($tagName);
            foreach ($tags as $tag) {
    
                $content = trim(str_strip_nonprintable(strip_tags($tag->nodeValue)));
                $content = str_replace("&nbsp;", " ", $content);
                $id = strtolower($this->slugger->slug($content));
                $headlines[] = [
                    "tag" => $tagName,
                    "slug" => $id,
                    "title" => $content
                ];
            }
        }

        return $headlines;
    }

    public function enhance(mixed $html, ?int $maxLevel = null, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {
            $toc = [];
            foreach ($html as $htmlEntry) {
                $toc[] = $this->enhance($htmlEntry, $maxLevel, $attrs);
            }

            return $toc;
        }

        $maxLevel ??= 6;
        $maxLevel = max(1, $maxLevel);

        $encoding = mb_detect_encoding($html);
        $dom = new DOMDocument('1.0', $encoding);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', $encoding), LIBXML_NOERROR);

        $attrs ??= [];
        $attrs["class"] = $attrs["class"] ?? "";
        $attrs["class"] = trim($attrs["class"] . " markdown-anchor");

        for ($i = 1; $i <= $maxLevel; $i++) {

            $tagName = "h" . $i;
            $tags = $dom->getElementsByTagName($tagName);
            foreach ($tags as $tag) {
    
                $content = trim(str_strip_nonprintable(strip_tags($tag->nodeValue)));
                $content = str_replace("&nbsp;", " ", $content);
                $tag->nodeValue = null;

                $id = strtolower($this->slugger->slug($content));
                $template = $dom->createDocumentFragment();
                $template->appendXML("<a " . html_attributes($attrs) . " id='".$id."' href='#" . $id . "'>" .trim($content) . "</a>");

                $tag->appendChild($template);
            }
        }

        $node = $dom->getElementsByTagName('body')->item(0);
        return trim(implode(array_map([$node->ownerDocument, "saveHTML"], iterator_to_array($node->childNodes))));
    }
}
