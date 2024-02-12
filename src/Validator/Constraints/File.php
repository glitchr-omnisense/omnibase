<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @NamedArgumentConstructor
 */

#[\Attribute]
class File extends Constraint
{
    public string $messageMaxSize = 'file.max_size';
    public string $messageMimeType = 'file.mime_type';

    protected array $mimeTypes;

    /**
     * @return array|mixed
     */
    public function getAllowedMimeTypes()
    {
        return $this->mimeTypes;
    }

    protected int $maxSize;

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    public function __construct(?string $max_size = null, array $mime_types = [], array $options = [], array $groups = null, mixed $payload = null)
    {
        $this->mimeTypes = $mime_types;

        $this->maxSize = str2dec($max_size ?? 8 * UploadedFile::getMaxFilesize()) / 8;

        parent::__construct($options, $groups, $payload);
    }
}
