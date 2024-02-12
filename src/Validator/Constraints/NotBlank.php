<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use function is_callable;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Constraint for the Unique Entity validator.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "PROPERTY", "METHOD"})
 *
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NotBlank extends Constraint
{
    public $message = '@validators.not_blank';
    public bool $allowNull = false;
    public $normalizer;

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    /**
     * @param array|null $options
     * @param string|null $message
     * @param bool|null $allowNull
     * @param callable|null $normalizer
     * @param array|null $groups
     * @param $payload
     */
    public function __construct(array $options = null, string $message = null, bool $allowNull = null, callable $normalizer = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);
        $this->message = $message ?? $this->message;
        $this->allowNull = $allowNull ?? $this->allowNull;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
