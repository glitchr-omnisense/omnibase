<?php

namespace Base\Service\Model;

interface WorkflowInterface extends \Symfony\Component\Workflow\WorkflowInterface
{
    public static function getWorkflowName(): string;
    public static function getWorkflowType(): string;

    public static function supports(): array;
    public static function supportStrategy(): ?string;
}