<?php

namespace Base\Model\IconProvider\Adapter;

use Base\Model\IconizeInterface;
use Base\Model\IconProvider\AbstractIconAdapter;

class IconifyAdapter extends AbstractIconAdapter
{
    public function __construct(string $metadata, string $javascript, string $stylesheet)
    {
        $this->metadata   = $metadata;
        $this->javascript = $javascript;
        $this->stylesheet = $stylesheet;
        $this->getVersion();
    }

    public static function getName(): string { return "iconify"; }
    public static function getOptions(): array { return ["class" => "iconify"]; }

    public function getAssets(): array
    {
        return [];
    }

    public function supports(IconizeInterface|string|null $icon): bool
    {
        if($icon === null) return false;

        if ($icon instanceof IconizeInterface) {
            $icon = $icon->__iconize() ?? $icon->__iconizeStatic();
            $icon = first($icon);
        }

        return preg_match('s/\w+:\w+/g', $icon);
    }

    public function getClass(string $style)
    {
        return "iconify";
    }

    public function getChoices(string $term = ""): array
    {
        $choices = [];
        foreach($this->getEntries() as $key => $icon)
        {
            $label  = $icon["label"];
            $styles = $icon["styles"];
            $terms  = $icon["search"]["terms"] ?? null;

            $termFound = empty($term);
            $term = mb_strtolower($term);
            if(!empty($term)) {

                $termFound |= str_contains(mb_strtolower($label), $term);
                if(!$termFound) $termFound |= $terms !== null && !empty(array_filter($terms, fn($t) => str_contains($t, $term)));
            }

            if(!$termFound) continue;
            foreach ($styles as $style)
                $choices[mb_ucfirst($style)." Style"][$label] = $this->getClass($style)." ".$this->getName()."-".$key;

        }

        return $choices;
    }

    public function getStyle(string $name)
    {
        return first(explode(":", $name));
    }

    public function getIdentifier(string $name)
    {
        return second(explode(":", $name));
    }

    public function getStyles(?string $name = null)
    {
        return array_filter([$this->getStyle($name)]);
    }

    public function getValues() { return array_keys(self::$contents[$this->metadata]); }
    public function getValue(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents[$this->metadata])) return "";
        return $identifier;
    }

    public function getLabels() { return array_map(function($icon) { return $icon["label"]; }, self::$contents[$this->metadata]); }
    public function getLabel(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents[$this->metadata])) return "";
        return self::$contents[$this->metadata][$identifier]["label"];
    }

    public function getUnicodes() { return array_map(function($icon) { return $icon["unicode"]; }, self::$contents[$this->metadata]); }
    public function getUnicode(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents[$this->metadata])) return [];
        return self::$contents[$this->metadata][$identifier]["unicode"];
    }
}
