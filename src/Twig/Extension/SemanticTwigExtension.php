<?php

namespace Base\Twig\Extension;

use Base\Service\SemanticEnhancer;
use Base\Service\SemanticEnhancerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SemanticTwigExtension extends AbstractExtension
{
    public function __construct(SemanticEnhancerInterface $semanticEnhancer)
    {
        $this->semanticEnhancer = $semanticEnhancer;
    }

    public function getName()
    {
        return 'semantic_extension';
    }
    public function getFilters(): array
    {
        return [
            new TwigFilter('highlight_semantic', [SemanticEnhancer::class, 'highlight' ], ['is_safe' => ['all']]),
            new TwigFilter('highlight_words', [SemanticEnhancer::class, 'highlightWords'], ['is_safe' => ['all']]),
        ];
    }
}
