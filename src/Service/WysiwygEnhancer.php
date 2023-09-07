<?php

namespace Base\Service;

use Base\Twig\Environment;
use Base\Twig\Renderer\Adapter\WebpackTagRenderer;
use DOMDocument;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 *
 */
class WysiwygEnhancer implements WysiwygEnhancerInterface
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var HeadingEnhancerInterface
     */
    protected $headingEnhancer;

    /**
     * @var SemanticEnhancerInterface
     */
    protected $semanticEnhancer;

    /**
     * @var MentionEnhancerInterface
     */
    protected $mentionEnhancer;

    public function __construct(Environment $twig, HeadingEnhancerInterface $headingEnhancer, SemanticEnhancerInterface $semanticEnhancer, MentionEnhancerInterface $mentionEnhancer)
    {
        $this->twig = $twig;

        $this->headingEnhancer = $headingEnhancer;
        $this->semanticEnhancer = $semanticEnhancer;
        $this->mentionEnhancer = $mentionEnhancer;
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

        return $this->headingEnhancer->toc($html, $maxLevel);
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

        return $this->headingEnhancer->highlight($html, $maxLevel, $attrs);
    }

    public function highlightSemantics(mixed $html, null|array|string $words = null, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {
            $htmlRet = [];
            foreach ($html as $htmlEntry) {
                $htmlRet[] = $this->highlightSemantics($htmlEntry, $words, $attrs);
            }

            return $htmlRet;
        }

        return $this->semanticEnhancer->highlight($html, $words, $attrs);
    }

    public function highlightMentions(mixed $html, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {

            $htmlRet = [];
            foreach ($html as $htmlEntry) {
                $htmlRet[] = $this->highlightMentions($htmlEntry, $attrs);
            }

            return $htmlRet;
        }

        return $this->mentionEnhancer->highlight($html, $attrs);
    }
}
