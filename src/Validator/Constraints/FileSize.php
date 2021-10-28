<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @Annotation
 */
class FileSize extends Constraint
{
    public $message = 'file.maxSize';

    protected $max;

    public function getMaxSize(): int { return $this->max; }

    public function __construct(array $options = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->max = $this->size2int($options["max"]) ?? UploadedFile::getMaxFilesize();
        $this->max = min($this->max,  UploadedFile::getMaxFilesize());
    }
}
