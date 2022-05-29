<?php

namespace Base\Model\IconProvider\Adapter;

use Base\Model\IconProvider\AbstractIconAdapter;

class BootstrapTwitterAdapter extends AbstractIconAdapter
{
    public const STYLE_REGULAR = "regular";
    public const STYLE_FILL    = "filled";

    public static function getName(): string { return "bi"; }
    public static function getOptions(): array { return []; }

    public function getAssets(): array
    {
        return [$this->stylesheet];
    }

    public function __construct(string $metadata, string $stylesheet)
    {
        $this->metadata = $metadata;
        $this->stylesheet = $stylesheet;
        $this->getVersion();
    }

    public function supports(string $icon): bool
    {
        return count(array_filter(explode(" ", $icon), fn($id) => $id == $this->getName()));
    }

    public function getChoices(string $term = ""): array
    {
        $choices = [];
        foreach($this->getEntries() as $key => $unicode)
        {
            $label = str_replace("-", " ", $key);
            $style = str_ends_with($key, "-fill") ? self::STYLE_FILL : self::STYLE_REGULAR;
            if($style == self::STYLE_FILL)
                $label  = mb_substr($label, 0, strlen($label) - 5);

            $label = ucwords($label);

            $term = mb_strtolower($term);
            $termFound = str_contains(mb_strtolower($label), $term);

            if(!$termFound) continue;
            $choices[mb_ucfirst($style)." Style"][$label] = $this->getName()." ".$this->getName()."-".$key;
        }

        return $choices;
    }

    public function getStyle(string $name)
    {
        return array_filter(explode(" ", $name), fn($n) => in_array($n, [self::STYLE_FILL]))[0] ?? null;
    }

    public function getIdentifier(string $name)
    {
        return array_transforms(
            fn($k, $v, $callback, $i):?array => preg_match("/".$this->getName()."-(.*)/", $v, $matches) ? [$i, $matches[1]] : null,
            explode(" ", $name)
        )[0];
    }

    public function getValues() { return array_keys(self::$contents); }
    public function getValue(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents)) return "";
        return $identifier;
    }

    public function getLabels() { return array_map(function($icon) { return $icon["label"]; }, self::$contents); }
    public function getLabel(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents)) return "";
        return self::$contents[$identifier]["label"];
    }
}
