<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Constraint for the StringCase Entity validator.
 *
 * @Annotation
 * @Target({"CLASS"})
 *
 */

#[\Attribute(\Attribute::TARGET_CLASS)]
class StringCaseEntity extends ConstraintEntity
{
    public ?string $message = 'string_case';
}
