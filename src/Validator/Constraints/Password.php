<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

/**
 * @Annotation
 * @NamedArgumentConstructor
 */
#[\Attribute(\Attribute::TARGET_PROPERTY |\Attribute::TARGET_METHOD |\Attribute::TARGET_CLASS)]
class Password extends Constraint
{
    public const MIN_LENGTH_FALLBACK   = 8;
    public const MIN_STRENGTH_FALLBACK = 0;
    public const MAX_STRENGTH_FALLBACK = 5;

    public string $messageMinStrength = 'password.min_strength';
    public string $messageMinLength   = 'password.min_length';

    public string $messageUpperCase   = 'password.requirements.uppercase';
    public string $messageLowerCase   = 'password.requirements.lowercase';
    public string $messageLength      = 'password.requirements.length';
    public string $messageNumbers     = 'password.requirements.numbers';
    public string $messageSpecials    = 'password.requirements.specials';

    protected int  $minLength;
    protected int  $minStrength;
    protected int  $maxStrength;

    protected bool $uppercase;
    protected bool $lowercase;
    protected bool $numbers;
    protected bool $specials;
    protected bool $length;

    public function getMinLength(): int       { return $this->minLength; }
    public function getMinStrength(): int     { return $this->minStrength; }
    public function getMaxStrength(): int     { return $this->maxStrength; }
    public function requiresUppercase(): bool { return $this->uppercase; }
    public function requiresLowercase(): bool { return $this->lowercase; }
    public function requiresNumbers(): bool   { return $this->numbers; }
    public function requiresSpecials(): bool  { return $this->specials; }
    public function requiresLength(): bool    { return $this->length; }

    /**
     * Symfony 7.4+ compliant constructor
     */
    public function __construct(
        bool $uppercase = true,
        bool $lowercase = true,
        bool $numbers   = true,
        bool $specials  = true,
        bool $length    = true,
        int $min_strength = self::MIN_STRENGTH_FALLBACK,
        int $min_length   = self::MIN_LENGTH_FALLBACK,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // Initialize requirements BEFORE parent constructor
        $this->uppercase = $uppercase;
        $this->lowercase = $lowercase;
        $this->numbers   = $numbers;
        $this->specials  = $specials;
        $this->length    = $length;

        // Strength computation
        $this->maxStrength = (int)$lowercase + (int)$uppercase + (int)$numbers + (int)$specials;
        $this->minStrength = min($this->maxStrength, $min_strength);

        // Length requirement
        $this->minLength = $min_length;

        // Symfony 7.4 requirement: call parent last
        parent::__construct(groups: $groups, payload: $payload);
    }
}