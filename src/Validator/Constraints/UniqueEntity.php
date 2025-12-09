<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;

/**
 * Constraint for UniqueEntity validation.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS"})
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueEntity extends ConstraintEntity
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f';

    public  string $service = 'doctrine.orm.validator.unique';
    public ?string $em = null;
    public ?string $entityClass = null;
    public  string $repositoryMethod = 'findBy';
    public  string|array $fields = [];
    public ?string $errorPath = null;
    public  bool $ignoreNull = true;

    protected const ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    /**
     * Symfony 7.4+ compliant constructor.
     *
     * @param array|string $fields
     * @param string|null  $message
     * @param string|null  $service
     * @param string|null  $em
     * @param string|null  $entityClass
     * @param string|null  $repositoryMethod
     * @param string|null  $errorPath
     * @param bool|null    $ignoreNull
     * @param array|null   $groups
     * @param mixed|null   $payload
     */
    public function __construct(
        array|string $fields,
        ?string $message = null,
        ?string $service = null,
        ?string $em = null,
        ?string $entityClass = null,
        ?string $repositoryMethod = null,
        ?string $errorPath = null,
        ?bool $ignoreNull = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // REQUIRED: Set base fields BEFORE calling parent
        $this->fields = $fields;

        // Override defaults only when provided
        if ($service !== null)         $this->service = $service;
        if ($em !== null)              $this->em = $em;
        if ($entityClass !== null)     $this->entityClass = $entityClass;
        if ($repositoryMethod !== null)$this->repositoryMethod = $repositoryMethod;
        if ($errorPath !== null)       $this->errorPath = $errorPath;
        if ($ignoreNull !== null)      $this->ignoreNull = $ignoreNull;

        // Message fallback is handled by ConstraintEntity
        parent::__construct(
            fields: $fields,
            entity: null,
            message: $message,
            groups: $groups,
            payload: $payload
        );
    }
}