<?php

namespace Base\Model\SocialSharer\Adapter;

use Base\Model\SocialSharer\AbstractSharerAdapter;

class FacebookAdapter extends AbstractSharerAdapter
{
    // public static function getName(): string { return "fa"; }
    // public static function getOptions(): array { return ["class" => "fa-fw"]; }

    // public function getAssets(): array
    // {
    //     return [
    //         "<script type='text/javascript'>window.FacebookConfig = { autoReplaceSvg: false }</script>",
    //         $this->javascript,
    //         $this->stylesheet
    //     ];
    // }

    // public function supports(string $icon): bool
    // {
    //     $knownPrefix = array_merge([$this->getName()], array_map(
    //         fn($s) => $this->getClass($s),
    //         [self::STYLE_SOLID, self::STYLE_REGULAR, self::STYLE_LIGHT, self::STYLE_THIN, self::STYLE_DUOTONE, self::STYLE_BRANDS, self::STYLE_KIT]
    //     ));

    //     $isAwesome = count(array_filter(explode(" ", $icon), fn($id) => in_array($id, $knownPrefix)));
    //     return $isAwesome;
    // }

    // public function getClass(string $style)
    // {
    //     if(version_compare($this->getVersion(), 6, ">="))
    //         return $this->getName()."-".$style;
    //     if(version_compare($this->getVersion(), 5, ">="))
    //         return $this->getName().$style[0];

    //     throw new \Exception("Version ". $this->getVersion()." is not supported.");
    // }

    // public function getChoices(string $term = ""): array
    // {
    //     $choices = [];
    //     foreach($this->getEntries() as $key => $icon)
    //     {
    //         $label  = $icon["label"];
    //         $styles = $icon["styles"];
    //         $terms  = $icon["search"]["terms"] ?? null;

    //         $termFound = empty($term);
    //         $term = mb_strtolower($term);
    //         if(!empty($term)) {

    //             $termFound |= str_contains(mb_strtolower($label), $term);
    //             if(!$termFound) $termFound |= $terms !== null && !empty(array_filter($terms, fn($t) => str_contains($t, $term)));
    //         }

    //         if(!$termFound) continue;
    //         foreach ($styles as $style)
    //             $choices[mb_ucfirst($style)." Style"][$label] = $this->getClass($style)." ".$this->getName()."-".$key;

    //     }

    //     return $choices;
    // }

    // public function getStyle(string $name)
    // {
    //     $styles = [self::STYLE_BRANDS, self::STYLE_DUOTONE, self::STYLE_LIGHT, self::STYLE_REGULAR, self::STYLE_SOLID, self::STYLE_THIN, self::STYLE_KIT];
    //     return array_filter(explode(" ", $name), fn($n) => in_array($n, $styles))[0] ?? null;
    // }

    // public function getIdentifier(string $name)
    // {
    //     return array_transforms(
    //         fn($k, $v, $callback, $i):?array => preg_match("/".$this->getName()."-(.*)/", $v, $matches) ? [$i, $matches[1]] : null,
    //         explode(" ", $name)
    //     )[0];
    // }

    // public function getStyles(?string $name = null)
    // {
    //     if ($name === null)
    //         return array_map(function($icon) { return $icon["styles"]; }, self::$contents[$this->metadata]);

    //     $identifier = $this->getIdentifier($name);
    //     if (!array_key_exists($identifier, self::$contents[$this->metadata])) return [];
    //     return self::$contents[$this->metadata][$identifier]["styles"] . " " ;
    // }

    // public function getValues() { return array_keys(self::$contents[$this->metadata]); }
    // public function getValue(string $name)
    // {
    //     $identifier = $this->getIdentifier($name);
    //     if (!array_key_exists($identifier, self::$contents[$this->metadata])) return "";
    //     return $identifier;
    // }

    // public function getLabels() { return array_map(function($icon) { return $icon["label"]; }, self::$contents[$this->metadata]); }
    // public function getLabel(string $name)
    // {
    //     $identifier = $this->getIdentifier($name);
    //     if (!array_key_exists($identifier, self::$contents[$this->metadata])) return "";
    //     return self::$contents[$this->metadata][$identifier]["label"];
    // }

    // public function getUnicodes() { return array_map(function($icon) { return $icon["unicode"]; }, self::$contents[$this->metadata]); }
    // public function getUnicode(string $name)
    // {
    //     $identifier = $this->getIdentifier($name);
    //     if (!array_key_exists($identifier, self::$contents[$this->metadata])) return [];
    //     return self::$contents[$this->metadata][$identifier]["unicode"];
    // }
}
