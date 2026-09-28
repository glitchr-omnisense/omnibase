<?php

namespace Base\Twig\Extension;

use Base\Service\Model\Wysiwyg\SemanticEnhancer;
use Base\Service\Model\Wysiwyg\SemanticEnhancerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SemanticTwigExtension extends AbstractExtension
{
    /**
     * @var SemanticEnhancerInterface
     */
    protected SemanticEnhancerInterface $semanticEnhancer;

    public function __construct(SemanticEnhancerInterface $semanticEnhancer)
    {
        $this->semanticEnhancer = $semanticEnhancer;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'semantic_extension';
    }

    public function getFilters(): array
    {
        return [
            // SemanticEnhancer has no static highlight()/highlightOne() any more:
            // its enhance() does both (every semantic word, or only $words).
            new TwigFilter('semantify', fn ($text, array $attributes = []) => $this->semanticEnhancer->enhance($text, null, $attributes), ['is_safe' => ['all']]),
            new TwigFilter('semantify_only', fn ($text, $words, array $attributes = []) => $this->semanticEnhancer->enhance($text, $words, $attributes), ['is_safe' => ['all']]),
        ];
    }
}
