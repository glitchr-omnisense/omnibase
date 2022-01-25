<?php

namespace Base\Model;

use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class Autocomplete
{
    public function __construct()
    {
        
    }

    public static function getFormattedValues($entry, $class = null, TranslatorInterface $translator = null, $format = FORMAT_SENTENCECASE) 
    {
        if($entry == null) return null;
        if(is_object($entry) && $class !== null) {

            $accessor = PropertyAccess::createPropertyAccessor();
            $id = $accessor->isReadable($entry, "id") ? strval($accessor->getValue($entry, "id")) : null;

            $autocomplete     = null;
            $autocompleteData = [];
            if(class_implements_interface($entry, AutocompleteInterface::class)) {
                $autocomplete = $entry->__autocomplete() ?? null;
                $autocompleteData = $entry->__autocompleteData() ?? []; 
            }

            $className = get_class($entry);
            if($translator) $className = $translator->entity($className, Translator::TRANSLATION_SINGULAR);

            $html = is_html($autocomplete) ? $autocomplete : null;
            $text = is_html($autocomplete) ? null          : $autocomplete;
            $data = $autocompleteData;

            if(!$text)
                $text = is_stringeable($entry) ? strval($entry) : $className . " #".$entry->getId();

            $icons = $entry->__iconize() ?? [];
            if(empty($icons) && class_implements_interface($entry, IconizeInterface::class)) 
                $icons = $entry::__iconizeStatic();

            $icon = begin($icons);

        } else if(is_a($class, IconType::class)) {

            $id   = $entry;
            $icon = $class::getIcon($entry, 0);
            $text = $class::getText($entry, $translator);
            $html = $class::getHtml($entry);
            $data = $class::getData($entry);
            
        }else if(class_implements_interface($class, SelectInterface::class)) {

            $id   = $entry;
            $icon = $class::getIcon($entry, 0);
            $text = $class::getText($entry, $translator);
            $html = $class::getHtml($entry);
            $data = $class::getData($entry);
            
        } else {

            $id    = is_array($entry) ? $entry[0] : $entry;
            $text  = is_array($entry) ? $entry[1] : null;
            $icon  = is_array($entry) ? $entry[2] : null;
            $html  = null;
            $data  = [];
        }

        return
        [
            "id"   => $id ?? null,
            "icon" => $icon,
            "text" => is_string($text) ? castcase($text, $format) : $text,
            "html" => $html,
            "data" => $data
        ];
    }
}