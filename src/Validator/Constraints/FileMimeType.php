<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

/**
 * @Annotation
 */
class FileMimeType extends Constraint
{
    public $message = 'file.mimeType';

    protected array $type;
    public function getAllowedMimeTypes()
    {
        return $this->type;
    }

    public function __construct(array $options = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->type = $options["type"] ?? [];
    }
}
