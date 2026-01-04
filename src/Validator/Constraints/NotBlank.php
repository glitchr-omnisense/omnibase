<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Constraint for the NotBlank validator.
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD"})
 * @NamedArgumentConstructor
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NotBlank extends Constraint
{
    public string $message = '@validators.not_blank';
    public bool $allowNull = false;
    public $normalizer = null;

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    /**
     * Symfony 7.4 compliant constructor
     */
    public function __construct(
        ?string $message = null,
        ?bool $allowNull = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // Initialize properties EXPLICITLY (Symfony 7 rule)
        if ($message !== null) {
            $this->message = $message;
        }

        if ($allowNull !== null) {
            $this->allowNull = $allowNull;
        }

        if ($normalizer !== null) {
            if (!is_callable($normalizer)) {
                throw new InvalidArgumentException(sprintf(
                    'The "normalizer" option must be a valid callable ("%s" given).',
                    get_debug_type($normalizer)
                ));
            }
            $this->normalizer = $normalizer;
        }

        // Only AFTER setting properties call parent constructor
        parent::__construct(groups: $groups, payload: $payload);
    }
}