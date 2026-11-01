<?php

/*
 * The finder language read by bare --strict: Composer's autoloader alone, nothing of Doctrine, Twig
 * nor the framework (tests/BareTest.php::testTheFinderLanguageIsReadWithoutDoctrine).
 */

use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Finder\Subject;

$subject = new class implements Subject {
    public function getClassName(): string
    {
        return \stdClass::class;
    }

    public function getFieldNames(): array
    {
        return ['id', 'email', 'username', 'activeAt'];
    }

    public function hasField(string $name): bool
    {
        return \in_array($name, $this->getFieldNames(), true);
    }

    public function criteriaFromSelect(object $select): array
    {
        return get_object_vars($select);
    }
};

$query = (new Parser($subject))->parse('findByIdNotEqualToAndActiveAtYoungerThan', [5, 300]);

return [
    'method' => $query->method,
    'criterions' => array_map(static fn (Criterion $criterion) => [$criterion->key(), $criterion->value, $criterion->options], $query->criterions()),
    'doctrine loaded' => array_values(array_filter(get_declared_classes(), static fn (string $class) => str_starts_with($class, 'Doctrine\\ORM\\') || str_starts_with($class, 'Doctrine\\DBAL\\'))),
];
