<?php

namespace Base\Model\SocialSharer;

use Symfony\Component\Yaml\Yaml;

abstract class AbstractSharerAdapter implements SharerAdapterInterface
{   
    // protected string $metadata;

    // public function getMetadata() { return $this->metadata; }
    // public function load(): array { return self::parse($this->metadata); }

    // protected static $contents = [];
    // public function getContents() { return $this->contents; }
    // public static function parse(string $metadata): array
    // {
    //     if (empty(self::$contents[$metadata])) {

    //         self::$contents[$metadata] =
    //             (str_ends_with($metadata, "yml") ?
    //                 Yaml::parse(file_get_contents($metadata)) :
    //             (str_ends_with($metadata, "yaml") ?
    //                 Yaml::parse(file_get_contents($metadata)) :
    //             (str_ends_with($metadata, "json") ?
    //                 json_decode(file_get_contents($metadata), true) : [])));
    //     }

    //     return self::$contents[$metadata];
    // }

    // public function getEntries() 
    // { 
    //     if(empty(self::$contents[$this->metadata])) self::parse($this->metadata); 
    //     return self::$contents[$this->metadata] ?? []; 
    // }

    // public function getEntry(string $value = null): string 
    // {
    //     if(empty(self::$contents[$this->metadata])) self::parse($this->metadata); 
    //     return self::$contents[$this->metadata][$value] ?? "";
    // }

    // protected $version;
    // public function getVersion(): string
    // {
    //     if( !empty($this->version) )
    //         return $this->version;

    //     if ( !preg_match('/.*\/([0-9.]*(?:[-_]{1}[a-zA-Z0-9]*)?)\//', $this->metadata ?? "", $matches) )
    //         return "unk.";

    //     $this->version = $matches[1];
    //     return $this->version;
    // }

    // public function iconify(string $icon, array $attributes = []): string
    // {
    //     $options = $this->getOptions();

    //     $class = trim(implode(" ", [$attributes["class"] ?? null, $options["class"] ?? null, $icon]));
    //     $attributes = array_key_removes($attributes, "class");
        
    //     return "<i ".html_attributes($attributes)." class='".$class."'></i>";
    // }
}