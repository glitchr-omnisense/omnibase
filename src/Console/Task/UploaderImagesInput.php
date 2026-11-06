<?php

namespace Base\Console\Task;

/** uploader:images' options (plan "omnibase agnostic", step F5e, slice 3). */
readonly class UploaderImagesInput extends UploaderEntitiesInput
{
    /**
     * @param bool    $warmup the images and their thumbnails made in the cache
     * @param int     $batch  at most this many made per run (0: no limit)
     * @param ?string $format only this format ("<width>x<height>")
     */
    public function __construct(
        string $source = 'src',
        ?string $entity = null,
        ?string $property = null,
        ?string $uuid = null,
        bool $orphans = false,
        bool $deleteOrphans = false,
        public bool $warmup = false,
        public int $batch = 0,
        public ?string $format = null,
    ) {
        parent::__construct($source, $entity, $property, $uuid, $orphans, $deleteOrphans);
    }
}
