<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @Annotation
 */
class File extends Constraint
{
    public $messageMaxSize  = 'file.max_size';
    public $messageMimeType = 'file.mime_type';

    protected array $mimeTypes;
    public function getAllowedMimeTypes() { return $this->mimeTypes; }

    protected $maxSize;
    public function getMaxSize(): int { return $this->maxSize; }

    public function __construct(array $options = [], array $groups = null, mixed $payload = null)
    {
        $this->mimeTypes = $options["mime_types"] ?? [];
        unset($options["mime_types"]);

        $this->maxSize   = str2dec($options["max_size"] ?? 8*UploadedFile::getMaxFilesize())/8;
        unset($options["max_size"]);

        parent::__construct($options ?? [], $groups, $payload);
   }
}
