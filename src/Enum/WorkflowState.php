<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

class WorkflowState extends EnumType implements IconizeInterface
{
    public const SUBMITTED = "WORKFLOW_SUBMITTED";
    public const SUSPENDED = "WORKFLOW_SUSPENDED";
    public const PENDING   = "WORKFLOW_PENDING";
    public const REVIEWING = "WORKFLOW_REVIEWING";
    public const REJECTED  = "WORKFLOW_REJECTED";
    public const APPROVED  = "WORKFLOW_APPROVED";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::SUBMITTED => ["fa-solid fa-paper-plane"],
            self::SUSPENDED => ["fa-solid fa-exclamation-circle"],
            self::APPROVED  => ["fa-solid fa-check-circle"],
            self::PENDING   => ["fa-solid fa-pause-circle"],
            self::REJECTED  => ["fa-solid fa-times-circle"],
            self::REVIEWING => ["fa-solid fa-search"],
        ];
    }
}
