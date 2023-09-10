<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;
use Base\Service\Model\Wysiwyg\HeadingEnhancerInterface;
use Base\Service\Model\Wysiwyg\MentionEnhancerInterface;
use Base\Service\Model\Wysiwyg\SemanticEnhancerInterface;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
use Base\Twig\Environment;

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

    /**
     * @var MediaEnhancerInterface
     */
    protected $mediaEnhancer;

    public function __construct(
        Environment $twig, 
        HeadingEnhancerInterface $headingEnhancer, 
        SemanticEnhancerInterface $semanticEnhancer, 
        MentionEnhancerInterface $mentionEnhancer, 
        MediaEnhancerInterface $mediaEnhancer)
    {
        $this->twig = $twig;

        $this->headingEnhancer = $headingEnhancer;
        $this->semanticEnhancer = $semanticEnhancer;
        $this->mentionEnhancer = $mentionEnhancer;
        $this->mediaEnhancer = $mediaEnhancer;
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

    public function enhanceHeadings(mixed $html, ?int $maxLevel = null, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {
            $toc = [];
            foreach ($html as $htmlEntry) {
                $toc[] = $this->enhanceHeadings($htmlEntry, $maxLevel, $attrs);
            }

            return $toc;
        }

        return $this->headingEnhancer->enhance($html, $maxLevel, $attrs);
    }

    public function enhanceSemantics(mixed $html, null|array|string $words = null, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {
            $htmlRet = [];
            foreach ($html as $htmlEntry) {
                $htmlRet[] = $this->enhanceSemantics($htmlEntry, $words, $attrs);
            }

            return $htmlRet;
        }

        return $this->semanticEnhancer->enhance($html, $words, $attrs);
    }

    public function enhanceMentions(mixed $html, array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {

            $htmlRet = [];
            foreach ($html as $htmlEntry) {
                $htmlRet[] = $this->enhanceMentions($htmlEntry, $attrs);
            }

            return $htmlRet;
        }

        return $this->mentionEnhancer->enhance($html, $attrs);
    }

    public function enhanceMedia(mixed $html, array $config = [], FilterInterface|array $filters = [], array $attrs = []): mixed
    {
        if ($html === null) {
            return null;
        }

        if (is_array($html)) {

            $htmlRet = [];
            foreach ($html as $htmlEntry) {
                $htmlRet[] = $this->enhanceMedia($htmlEntry, $config, $filters, $attrs);
            }

            return $htmlRet;
        }

        return $this->mentionEnhancer->enhance($html, $config, $filters, $attrs);
    }
}
