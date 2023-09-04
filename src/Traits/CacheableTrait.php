<?php

namespace Base\Traits;

/**
 *
 */
trait CacheableTrait
{
    public function __toKey(mixed ...$variadic): string
    {
        $variadic = array_flatten("-", $variadic);
        $variadic = array_filter($variadic);
        $variadic = array_map(function($v) {

            if(is_stringeable($v)) {
                return $v;
            }

            if($v instanceof \DateTime) {
                return $v->getTimestamp();
            }

            return null;

        }, $variadic);

        if (empty($variadic)) {
            $variadic[] = spl_object_id($this);
        }

        $variadic = array_flatten(".", $variadic);
        return implode(";", array_filter([
            "class-".snake2camel(str_replace("\\", "_", static::class)),
            "webp-".browser_supports_webp(),
            ...$variadic
        ]));
    }

    public function __toKeyTTL(): ?int
    {
        return 3600 * 24 * 7;
    }

    public function __toKeyTags(): array
    {
        return [];
    }
}
