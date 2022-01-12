<?php

namespace Base\Model\Icon;

use Base\Model\IconProviderInterface;
use Symfony\Component\Yaml\Yaml;

class BootstrapTwitter implements IconProviderInterface
{
    public const STYLE_REGULAR = "";
    public const STYLE_FILL    = "fill";

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
        return count(array_filter(explode(" ", $icon), fn($id) => in_array($id, ["bi"])));
    }

    public function iconify(string $icon, array $attributes = []): string
    {
        $class = $attributes["class"] ?? "";
        $class = trim($class." ".$icon);
        if($attributes["class"] ?? false) unset($attributes["class"]);

        dump($attributes);
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
                $choices[mb_ucfirst($style)." Style"][$label] = "bi bi-".$key;
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

        if ( !preg_match('/.*\/([0-9.]*)\//', $this->metadata ?? "", $matches) )
            return "unk.";

        $this->version = $matches[1];
        return $this->version;
    }

    public function getStyle(string $name)
    {
        return array_filter(explode(" ", $name), fn($n) => in_array($n, [self::STYLE_FILL]))[0] ?? null;
    }

    public function getIdentifier(string $name)
    {
        return array_transforms(
            fn($k, $v, $i):?array => preg_match("/bi-(.*)/", $v, $matches) ? [$i, $matches[1]] : null, 
            explode(" ", $name)
        )[0];
    }

    public function getStyles(?string $name = null)
    {
        if ($name === null)
            return array_map(function($icon) { return $icon["styles"]; }, self::$contents);

        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents)) return [];
        return self::$contents[$identifier]["styles"] . " " ;
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

    public function getUnicodes() { return array_map(function($icon) { return $icon["unicode"]; }, self::$contents); }
    public function getUnicode(string $name)
    {
        $identifier = $this->getIdentifier($name);
        if (!array_key_exists($identifier, self::$contents)) return [];
        return self::$contents[$identifier]["unicode"];
    }
}
