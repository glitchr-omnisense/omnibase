<?php

namespace Base\Service;

use Base\Repository\Layout\SemanticRepository;

class SemanticEnhancer implements SemanticEnhancerInterface
{
    protected $semanticRepository;
    public function __construct(SemanticRepository $semanticRepository)
    {
        $this->semanticRepository = $semanticRepository;
    }

    public function highlight(string|array|null $strOrArray, null|array|string $words = null, array $attributes = []): string|array|null
    {
        if ($strOrArray === null) {
            return null;
        }

        $words ??= [];
        $semantics = $this->semanticRepository->cacheAll()->getResult();

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($semantics as $semantic) {
            foreach ($array as &$entry) {
                if ($words) {
                    $entry = $semantic->highlightBy($words, $entry, $attributes);
                } else {
                    $entry = $semantic->highlight($entry, $attributes);
                }
            }
        }

        return is_array($strOrArray) ? $array : first($array);
    }
}
