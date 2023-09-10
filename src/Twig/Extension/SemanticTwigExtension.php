<?php

namespace Base\Twig\Extension;

use Base\Service\Model\Wysiwyg\SemanticEnhancer;
use Base\Service\Model\Wysiwyg\SemanticEnhancerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 *
 */
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
            new TwigFilter('semantify', [SemanticEnhancer::class, 'highlight'], ['is_safe' => ['all']]),
            new TwigFilter('semantify_only', [SemanticEnhancer::class, 'highlightOne'], ['is_safe' => ['all']]),
        ];
    }
}
