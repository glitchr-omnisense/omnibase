<?php

namespace Base\Service\Model;

use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 *
 */
class Autocomplete
{
    /** @var TranslatorInterface */
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param $entry
     * @param $class
     * @param array $entryOptions
     * @return array|null
     */
    public function resolve($entry, $class = null, array $entryOptions = [])
    {
        $entryOptions["format"] ??= FORMAT_IDENTITY;
        $entryOptions["html"] ??= true;

        if ($entry == null) {
            return null;
        }

        if (is_object($entry) && $class !== null) {
            $accessor = PropertyAccess::createPropertyAccessor();
            $id = $accessor->isReadable($entry, "id") ? strval($accessor->getValue($entry, "id")) : null;

            $autocomplete = null;
            $autocompleteData = [];

            $entityStr = is_stringeable($entry) ? $entry->__toString() : null;
            if (class_implements_interface($entry, AutocompleteInterface::class)) {
                $autocomplete = $entry->__autocomplete() ?? null;
                $autocompleteData = $entry->__autocompleteData() ?? [];
            }

            $className = get_class($entry);
            $className = $this->translator->transEntity($className, null, Translator::NOUN_SINGULAR);

            $html = $entryOptions["html"] && is_html($autocomplete) ? $autocomplete : null;
            $text = $entryOptions["html"] && is_html($autocomplete) ? null : $entityStr;
            $data = $autocompleteData;

            if (!$text) {
                $text = is_stringeable($entry) ? strip_tags(strval($entry)) : $className . " #" . $entry->getId();
            }

            $icons = [];
            if (class_implements_interface($entry, IconizeInterface::class)) {
                $icons = $entry->__iconize();
            }
            if (empty($icons) && class_implements_interface($entry, IconizeInterface::class)) {
                $icons = $entry::__iconizeStatic();
            }

            $icon = begin($icons);


            $color = [];
            if (class_implements_interface($entry, ColorizeInterface::class)) {
                $color = $entry->__colorize();
            }
            if (empty($color) && class_implements_interface($entry, ColorizeInterface::class)) {
                $color = $entry::__colorizeStatic();
            }
            $color = null;

        } elseif (class_implements_interface($class, SelectInterface::class)) {

            $id = $entry;
            $icon = $class::getIcon($entry, 0);
            $text = $class::getText($entry, $this->translator);
            $html = $class::getHtml($entry);
            $data = $class::getData($entry);

            $color = null;
            if (class_implements_interface($entry, ColorizeInterface::class)) {
                $color = $class::getColor($entry);
            }

        } else {
            
            $icon = is_array($entry) ? ($entry[2] ?? $entry[1] ?? $entry[0]) : null;
            $text = is_array($entry) ? ($entry[1] ?? $entry[0]) : $entry;
            $id = is_array($entry) ? ($entry[0]) : $entry;
            $html = null;
            $data = [];

            $color = null;
        }
        
        return
            [
                "id" => $id ?? null,
                "icon" => $icon,
                "color" => $color,
                "search" => null,
                "text" => is_string($text) ? castcase($text, $entryOptions["format"]) : $text,
                "html" => $entryOptions["html"] ? $html : null,
                "data" => $data,
            ];
    }

    /**
     * @param $entry
     * @param array $entryOptions
     * @return array
     * @throws \Exception
     */
    public function resolveArray($entry, array $entryOptions = [])
    {
        $entryOptions["format"] ??= FORMAT_IDENTITY;

        return array_transforms(function ($k, $v, $callback, $i, $d) use ($entryOptions): ?array {
            if (is_array($v)) {
                $children = array_transforms($callback, $v, ++$d);

                $group = array_pop_key("_self", $children);
                $group["text"] = $k;
                $group["children"] = $children;
                return [null, $group];
            }

            return [null, ["id" => $v, "icon" => $v, "text" => castcase($k, $entryOptions["format"])]];
        }, !empty($entry) ? $entry : []);
    }
}
