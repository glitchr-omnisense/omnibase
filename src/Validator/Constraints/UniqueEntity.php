<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;

/**
 * Constraint for the StringCase Entity validator.
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 *
 */
class UniqueEntity extends ConstraintEntity
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f';

    public $service = 'doctrine.orm.validator.unique';
    public $em = null;
    public $entityClass = null;
    public $repositoryMethod = 'findBy';
    public $fields = [];
    public $errorPath = null;
    public $ignoreNull = true;

    protected static $errorNames = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    /**
     * {@inheritdoc}
     *
     * @param array|string $fields the combination of fields that must contain unique values or a set of options
     */
    public function __construct(
        $fields,
        string $message = null,
        string $service = null,
        string $em = null,
        string $entityClass = null,
        string $repositoryMethod = null,
        string $errorPath = null,
        bool $ignoreNull = null,
        array $groups = null,
        $payload = null,
        array $options = []
    ) {
        parent::__construct($fields, $options, $groups, $payload);

        dump($fields, $options);
        $this->message = $message ?? $this->message;
        $this->service = $service ?? $this->service;
        $this->em = $em ?? $this->em;
        $this->entityClass = $entityClass ?? $this->entityClass;
        $this->repositoryMethod = $repositoryMethod ?? $this->repositoryMethod;
        $this->errorPath = $errorPath ?? $this->errorPath;
        $this->ignoreNull = $ignoreNull ?? $this->ignoreNull;
    }
}
