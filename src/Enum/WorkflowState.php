<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class WorkflowState extends EnumType implements IconizeInterface
{
    const SUBMITTED = "WORKFLOW_SUBMITTED";
    const SUSPENDED = "WORKFLOW_SUSPENDED";
    const PENDING   = "WORKFLOW_PENDING";
    const REVIEWING = "WORKFLOW_REVIEWING";
    const REJECTED  = "WORKFLOW_REJECTED";
    const APPROVED  = "WORKFLOW_APPROVED";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::SUBMITTED => ["fas fa-paper-plane"],
            self::SUSPENDED => ["fas fa-exclamation-circle"],
            self::APPROVED  => ["fas fa-check-circle"],
            self::PENDING   => ["fas fa-pause-circle"],
            self::REJECTED  => ["fas fa-times-circle"],
            self::REVIEWING => ["fas fa-times-circle"],
        ];
    }
}