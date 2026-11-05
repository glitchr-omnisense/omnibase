<?php

namespace Base\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * No other record of the class has the same values for these fields (plan "omnibase agnostic",
 * step E1): Symfony's UniqueEntity (symfony/doctrine-bridge) without Doctrine, the lookup asked of
 * the persistence's Repositories (Base\Database\Repository\Repositories, whichever backend answers
 * the unit of work). The same arguments, message, code and violation as UniqueEntity, so an entity
 * that named it says the same; the neutral form layer reads it as omnisense/omnibridge's Unique.
 *
 * Kept from UniqueEntity: fields, message, entityClass, repositoryMethod (an Entities method taking
 * the criteria, findBy by default, asked for two records at most), errorPath, ignoreNull (a bool, or
 * the fields whose null is ignored), groups, payload. Not kept: `em` and `service` (Doctrine's
 * manager and validator service: the unit of work is the host's), identifierFieldNames (a DTO
 * validated as an entity; none of the core's).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Unique extends Constraint
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f';

    protected const ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    public string $message = 'This value is already used.';
    public ?string $entityClass = null;
    public string $repositoryMethod = 'findBy';
    /** @var array<int|string, string>|string */
    public array|string $fields = [];
    public ?string $errorPath = null;
    /** @var bool|string|list<string> */
    public bool|string|array $ignoreNull = true;

    /**
     * @param array<int|string, string>|string $fields
     * @param bool|string|list<string>|null    $ignoreNull
     * @param list<string>|null                $groups
     */
    public function __construct(
        array|string $fields,
        ?string $message = null,
        ?string $entityClass = null,
        ?string $repositoryMethod = null,
        ?string $errorPath = null,
        bool|string|array|null $ignoreNull = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->fields = $fields;
        $this->message = $message ?? $this->message;
        $this->entityClass = $entityClass ?? $this->entityClass;
        $this->repositoryMethod = $repositoryMethod ?? $this->repositoryMethod;
        $this->errorPath = $errorPath ?? $this->errorPath;
        $this->ignoreNull = $ignoreNull ?? $this->ignoreNull;
    }

    public function getRequiredOptions(): array
    {
        return ['fields'];
    }

    public function getDefaultOption(): ?string
    {
        return 'fields';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
