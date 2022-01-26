<?php

namespace Base\Model;

use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class Autocomplete
{
    public function __construct(TranslatorInterface $translator = null)
    {   
        $this->translator = $translator;
    }

    public function resolve($data, $class = null, $format = FORMAT_SENTENCECASE) 
    {
        if($data == null) return null;
        if(is_object($data) && $class !== null) {

            $accessor = PropertyAccess::createPropertyAccessor();
            $id = $accessor->isReadable($data, "id") ? strval($accessor->getValue($data, "id")) : null;

            $autocomplete     = null;
            $autocompleteData = [];
            if(class_implements_interface($data, AutocompleteInterface::class)) {
                $autocomplete = $data->__autocomplete() ?? null;
                $autocompleteData = $data->__autocompleteData() ?? []; 
            }

            $className = get_class($data);
            $className = $this->translator ? $this->translator->entity($className, Translator::TRANSLATION_SINGULAR) : $className;

            $html = is_html($autocomplete) ? $autocomplete : null;
            $text = is_html($autocomplete) ? null          : $autocomplete;
            $data = $autocompleteData;

            if(!$text)
                $text = is_stringeable($data) ? strval($data) : $className . " #".$data->getId();

            $icons = $data->__iconize() ?? [];
            if(empty($icons) && class_implements_interface($data, IconizeInterface::class)) 
                $icons = $data::__iconizeStatic();

            $icon = begin($icons);

        } else if(class_implements_interface($class, SelectInterface::class)) {

            $id   = $data;
            $icon = $class::getIcon($data, 0);
            $text = $class::getText($data, $this->translator);
            $html = $class::getHtml($data);
            $data = $class::getData($data);

        } else {

            $id    = is_array($data) ? $data[0] : $data;
            $text  = is_array($data) ? $data[1] : null;
            $icon  = is_array($data) ? $data[2] : null;
            $html  = null;
            $data  = [];
        }

        return [

            "id"   => $id ?? null,
            "icon" => $icon,
            "text" => is_string($text) ? castcase($text, $format) : $text,
            "html" => $html,
            "data" => $data
        ];
    }
}