<?php

namespace Base\Model\Icon;

use Base\Model\IconProviderInterface;
use Symfony\Component\Yaml\Yaml;

class FontAwesome implements IconProviderInterface
{
    public const STYLE_SOLID   = "fas";
    public const STYLE_REGULAR = "far";
    public const STYLE_LIGHT   = "fal";
    public const STYLE_DUOTONE = "fad";
    public const STYLE_BRANDS  = "fab";

    public function __construct(string $metadata)
    {
        $this->metadata = $metadata;
        $this->getVersion();
    }

    public function load(): array
    {
        return self::parse($this->metadata);
    }

    public function supports(string $icon): bool
    {
        $styles = [self::STYLE_SOLID, self::STYLE_REGULAR, self::STYLE_LIGHT, self::STYLE_DUOTONE, self::STYLE_BRANDS];
        $isAwesome = count(array_filter(explode(" ", $icon), fn($id) => in_array($id, $styles)));

        return $isAwesome;
    }

    public function iconify(string $icon, array $attributes = []): string
    {
        $class = $attributes["class"] ?? "";
        $class = trim($class." ".$icon);
        if($attributes["class"] ?? false) unset($attributes["class"]);

        return "<i ".html_attributes($attributes)." class='".$class."'></i>";
    }

    public function getChoices(string $term = ""): array
    {
        $choices = [];
        foreach($this->getIcons() as $key => $icon)
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
                $choices[mb_ucfirst($style)." Style"][$label] = "fa".$style[0]." fa-".$key;

        }

        return $choices;
    }

    protected static $contents = [];
    public function getContents() { return $this->contents; }
    public static function parse(string $metadata): array
    {
        if (empty(self::$contents[$metadata])) {

            self::$contents[$metadata] =
                (str_ends_with($metadata, "yml") ?
                    Yaml::parse(file_get_contents($metadata)) :
                (str_ends_with($metadata, "yaml") ?
                    Yaml::parse(file_get_contents($metadata)) :
                (str_ends_with($metadata, "json") ?
                    json_decode(file_get_contents($metadata), true) : [])));
        }

        return self::$contents[$metadata];
    }

    /*
     * Available icons
     */
    protected $icons = [];
    public function getIcons() 
    { 
        if(empty(self::$contents[$this->metadata])) self::parse($this->metadata); 
        return self::$contents[$this->metadata] ?? []; 
    }

    public function getIcon(string $value = null): string 
    {
        if(empty(self::$contents[$this->metadata])) self::parse($this->metadata); 
        return self::$contents[$this->metadata][$value] ?? "";
    }

    protected $version;
    public function getVersion()
    {
        if( !empty($this->version) )
            return $this->version;

        if ( !preg_match('/.*\/([0-9.]*)\/metadata/', $this->metadata ?? "", $matches) )
            return "unk.";

        $this->version = $matches[1];
        return $this->version;
    }

    public function getStyle(string $name)
    {
        $styles = [self::STYLE_BRANDS, self::STYLE_DUOTONE, self::STYLE_LIGHT, self::STYLE_REGULAR, self::STYLE_SOLID];
        return array_filter(explode(" ", $name), fn($n) => in_array($n, $styles))[0] ?? null;
    }

    public function getIdentifier(string $name)
    {
        return array_transforms(
            fn($k, $v, $i):?array => preg_match("/fa-(.*)/", $v, $matches) ? [$i, $matches[1]] : null, 
            explode(" ", $name)
        )[0];
    }

    public function getStyles(?string $name = null)
    {
        if ($name === null)
            return array_map(function($icon) { return $icon["styles"]; }, self::$contents[$this->metadata]);

        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents[$this->metadata])) return [];
        return self::$contents[$this->metadata][$identifier]["styles"] . " " ;
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
