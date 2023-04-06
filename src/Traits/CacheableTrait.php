<?php

namespace Base\Traits;

use Base\Database\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

trait CacheableTrait
{
    public function __toKey(?string ...$variadic):string {

        if(empty($variadic)) $variadic[] = spl_object_id($this);

        return implode(";", array_filter([
            snake2camel(str_replace("\\", "_", static::class)),
            ...$variadic
        ]));
    }

    public function __toKeyTTL() : ?int { return 3600*24*7; }
    public function __toKeyTags() : array { return []; }
}