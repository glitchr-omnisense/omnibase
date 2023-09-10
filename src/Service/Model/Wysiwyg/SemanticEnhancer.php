<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Repository\Layout\SemanticRepository;

/**
 *
 */
class SemanticEnhancer implements SemanticEnhancerInterface
{
    protected SemanticRepository $semanticRepository;

    public function __construct(SemanticRepository $semanticRepository)
    {
        $this->semanticRepository = $semanticRepository;
    }

    public function enhance(string|array|null $strOrArray, null|array|string $words = null, array $attributes = []): string|array|null
    {
        if (!$strOrArray) {
            return $strOrArray;
        }

        $words ??= [];
        $semantics = $this->semanticRepository->cacheAll()->getResult();

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($semantics as $semantic) {

            foreach ($array as &$entry) {

                if(!$entry) continue;
            
                if ($words) {
                    $entry = $semantic->enhanceBy($words, $entry, $attributes);
                } else {
                    $entry = $semantic->enhance($entry, $attributes);
                }
            }
        }

        return is_array($strOrArray) ? $array : first($array);
    }
}
