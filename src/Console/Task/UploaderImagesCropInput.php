<?php

namespace Base\Console\Task;

/** uploader:images:crop's options (plan "omnibase agnostic", step F5e, slice 3). */
readonly class UploaderImagesCropInput extends UploaderImagesInput
{
    /**
     * @param bool    $normalize     the crops' coordinates renormalised (x0, y0, width0, height0 from x, y, width, height)
     * @param ?object $maxDefinition the resolution (a Base\Imagine\Filter\Basic\Definition\*) they are renormalised against
     */
    public function __construct(
        string $source = 'src',
        ?string $entity = null,
        ?string $property = null,
        ?string $uuid = null,
        bool $orphans = false,
        bool $deleteOrphans = false,
        bool $warmup = false,
        int $batch = 0,
        ?string $format = null,
        public bool $normalize = false,
        public ?object $maxDefinition = null,
    ) {
        parent::__construct($source, $entity, $property, $uuid, $orphans, $deleteOrphans, $warmup, $batch, $format);
    }
}
