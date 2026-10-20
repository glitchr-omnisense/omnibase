<?php

namespace Base\Screen;

/**
 * A stored file answered as it is (step R10 of the audit "lesringostars face
 * au master plan"): its path, its type (null: the host guesses it from the
 * file), how it may be kept.
 */
final readonly class File
{
    public function __construct(
        public string $path,
        public ?string $type = null,
        public Cache $cache = new Cache(),
    ) {
    }
}
