<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Repository\Layout\Semantics;

class SemanticEnhancer implements SemanticEnhancerInterface
{
    protected Semantics $semanticRepository;

    public function __construct(Semantics $semanticRepository)
    {
        $this->semanticRepository = $semanticRepository;
    }

    public function enhance(string|array|null $strOrArray, null|array|string $words = null, array $attributes = []): string|array|null
    {
        if (!$strOrArray) {
            return $strOrArray;
        }

        $words ??= [];
        $semantics = $this->semanticRepository->every();

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
