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
     * @param int|string|null $max_size   A size in bytes (an integer: FileType's ceiling), a human readable
     *                                    string ("2MB", "500KB", etc.), or null (PHP's upload limit)
     * @param array           $mime_types Array of allowed MIME types
     * @param array|null      $groups     Validation groups
     * @param mixed           $payload    Metadata
     */
    public function __construct(
        int|string|null $max_size = null,
        array $mime_types = [],
        ?array $groups = null,
        mixed $payload = null
    ) {
        $this->mimeTypes = $mime_types;

        if (\is_int($max_size)) {
            // Bytes, as FileType gives them (Uploader::getMaxFilesize()). They were read as a string of bits
            // until omnibase's plan "omnibase agnostic", step F5d: a ceiling of 32 MB refused a file over 4 MB.
            $this->maxSize = $max_size;
        } else {
            // Convert "2M", "1G", "500K" → bytes → *bits* → final size
            $converted = str2dec($max_size ?? (string) (8 * UploadedFile::getMaxFilesize()));
            $this->maxSize = (int) ($converted / 8);
        }

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