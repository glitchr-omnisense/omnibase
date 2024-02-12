<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 */

#[\Attribute]
class Password extends Constraint
{
    public const MIN_LENGTH_FALLBACK = 8;
    public const MIN_STRENGTH_FALLBACK = 0;
    public const MAX_STRENGTH_FALLBACK = 5;

    public string $messageMinStrength = 'password.min_strength';
    public string $messageMinLength = 'password.min_length';

    public string $messageUpperCase = 'password.requirements.uppercase';
    public string $messageLowerCase = 'password.requirements.lowercase';
    public string $messageLength = 'password.requirements.length';
    public string $messageNumbers = 'password.requirements.numbers';
    public string $messageSpecials = 'password.requirements.specials';

    protected int $minLength;

    public function getMinLength(): int
    {
        return $this->minLength;
    }

    protected int $minStrength;

    public function getMinStrength(): int
    {
        return $this->minStrength;
    }

    protected int $maxStrength;

    public function getMaxStrength(): int
    {
        return $this->maxStrength;
    }

    protected bool $uppercase;

    public function requiresUppercase(): bool
    {
        return $this->uppercase;
    }

    protected bool $lowercase;

    public function requiresLowercase(): bool
    {
        return $this->lowercase;
    }

    protected bool $numbers;

    public function requiresNumbers(): bool
    {
        return $this->numbers;
    }

    protected bool $specials;

    public function requiresSpecials(): bool
    {
        return $this->specials;
    }

    protected bool $length;

    public function requiresLength(): bool
    {
        return $this->length;
    }

    public function __construct(
        bool $uppercase = true, 
        bool $lowercase = true, 
        bool $numbers = true,
        bool $specials = true,
        bool $length = true,
        int $min_strength = self::MIN_STRENGTH_FALLBACK,
        int $min_length = self::MIN_LENGTH_FALLBACK,

        array $options = [], array $groups = null, mixed $payload = null)
    {
        $this->uppercase = $uppercase;
        $this->lowercase = $lowercase;
        $this->numbers = $numbers;
        $this->specials = $specials;
        $this->length = $length;

        $this->maxStrength = (int)$this->lowercase + (int)$this->uppercase + (int)$this->numbers + (int)$this->specials;
        $this->minStrength = min($this->maxStrength, $min_strength);
        $this->minLength = $min_length;

        parent::__construct($options, $groups, $payload);
    }
}
