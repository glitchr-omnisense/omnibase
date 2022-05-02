<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @Annotation
 */
class File extends Constraint
{
    public $message         = "";
    public $messageMaxSize  = 'file.maxSize';
    public $messageMimeType = 'file.mimeType';

    protected array $mimeTypes;
    public function getAllowedMimeTypes() { return $this->mimeTypes; }

    protected $maxSize;
    public function getMaxSize(): int { return $this->maxSize; }

    public function __construct(array $options = [], array $groups = null, mixed $payload = null)
    {
        $this->mimeTypes = $maxSize ?? $options["mime_types"] ?? [];

        $this->maxSize = $mimeTypes ?? $options["max_size"] ?? (string) UploadedFile::getMaxFilesize();
        $this->maxSize = min(str2dec($this->maxSize), UploadedFile::getMaxFilesize());
 
        unset($options["mime_types"]);
        unset($options["max_size"]);
        parent::__construct($options ?? [], $groups, $payload);
   }
}
