<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

/**
 * @Annotation
 */
class Password extends Constraint
{
    public const MIN_LENGTH_FALLBACK   = 8;
    public const MIN_STRENGTH_FALLBACK = 0;
    public const MAX_STRENGTH_FALLBACK = 5;

    public $messageMinStrength = 'password.min_strength';
    public $messageMinLength   = 'password.min_length';
    
    public $messageUpperCase = 'password.requirements.uppercase';
    public $messageLowerCase = 'password.requirements.lowercase';
    public $messageLength12  = 'password.requirements.length12';
    public $messageNumbers   = 'password.requirements.numbers'  ;
    public $messageSpecials  = 'password.requirements.specials' ;

    protected int $minLength;
    public function getMinLength(): int { return $this->minLength; }
    protected int $minStrength;
    public function getMinStrength(): int { return $this->minStrength; }
    protected int $maxStrength;
    public function getMaxStrength(): int { return $this->maxStrength; }

    protected bool $uppercase;
    public function requiresUppercase(): bool { return $this->uppercase; }
    protected bool $lowercase;
    public function requiresLowercase(): bool { return $this->lowercase; }
    protected bool $numbers;
    public function requiresNumbers(): bool { return $this->numbers; }
    protected bool $specials;
    public function requiresSpecials(): bool { return $this->specials; }
    protected bool $length12;
    public function requiresLength12(): bool { return $this->length12; }

    public function __construct(array $options = [], array $groups = null, mixed $payload = null)
    {
        $this->uppercase = $options["uppercase"] ?? true;
        unset($options["uppercase"]);
        $this->lowercase = $options["lowercase"] ?? true;
        unset($options["lowercase"]);
        $this->numbers   = $options["numbers"]   ?? true;
        unset($options["numbers"]);
        $this->specials  = $options["specials"]  ?? true;
        unset($options["specials"]);
        $this->specials  = $options["length12"]  ?? true;
        unset($options["length12"]);

        $this->maxStrength = (int) $this->lowercase + (int) $this->uppercase + (int) $this->numbers + (int) $this->specials;
        $this->minStrength = min($this->maxStrength, $options["min_strength"] ?? self::MIN_STRENGTH_FALLBACK);
        unset($options["min_strength"]);
        
        $this->minLength = $options["min_length"] ?? self::MIN_LENGTH_FALLBACK;
        unset($options["min_length"]);
        
        parent::__construct($options ?? [], $groups, $payload);
   }
}
