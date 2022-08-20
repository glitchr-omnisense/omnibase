<?php

namespace Base\Service;

use Base\Repository\Layout\SemanticRepository;

class SemanticEnhancer implements SemanticEnhancerInterface
{
    public function __construct(SemanticRepository $semanticRepository)
    {
        $this->semanticRepository = $semanticRepository;
    }

    public function highlight(string|array|null $strOrArray, array $attributes = []): string|array|null
    {
        if($strOrArray === null) return null;

        $semantics = $this->semanticRepository->cacheAll();;

        $array = $strOrArray;
        if(!is_array($array)) $array = [$array];

        foreach($array as &$entry) {

            foreach($semantics as $semantic)
                $entry = $semantic->highlight($entry);
        }

        return is_array($strOrArray) ? $array : is_array($strOrArray);
    }

    public function highlightByWord(string $word, array $attributes = [])
    {
        $semantic = $this->semanticRepository->cacheOneByInsensitiveKeywords([$word]);
        if($semantic === null) return $word;

        return "<a href='".$semantic->generate()."' ".html_attributes($attributes).">".$word."</a>";
    }
}