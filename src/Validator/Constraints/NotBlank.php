<?php


namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Constraint for the Unique Entity validator.
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 *
 */
class NotBlank extends Constraint
{
    public $message = '@validators.not_blank';
    public $allowNull = false;
    public $normalizer;

    public function getTargets() : string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    public function __construct(array $options = null, string $message = null, bool $allowNull = null, callable $normalizer = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);
        $this->message = $message ?? $this->message;
        $this->allowNull = $allowNull ?? $this->allowNull;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
