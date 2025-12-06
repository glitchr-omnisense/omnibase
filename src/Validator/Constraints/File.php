<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[\Attribute]
class File extends Constraint
{
    public string $messageMaxSize = 'file.max_size';
    public string $messageMimeType = 'file.mime_type';

    public array $mimeTypes = [];
    public int $maxSize;

    /**
     * @param string|null $max_size    Human readable string ("2M", "500K", etc.) or null
     * @param array        $mime_types Array of allowed MIME types
     * @param array|null   $groups     Validation groups
     * @param mixed        $payload    Metadata
     */
    public function __construct(
        ?string $max_size = null,
        array $mime_types = [],
        ?array $groups = null,
        mixed $payload = null
    ) {
        $this->mimeTypes = $mime_types;

        // Convert "2M", "1G", "500K" → bytes → *bits* → final size
        $converted = str2dec($max_size ?? (8 * UploadedFile::getMaxFilesize()));
        $this->maxSize = (int) ($converted / 8);

        // Must only pass groups/payload (Symfony 7.4+)
        parent::__construct(groups: $groups, payload: $payload);
    }

    public function getAllowedMimeTypes(): array
    {
        return $this->mimeTypes;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}