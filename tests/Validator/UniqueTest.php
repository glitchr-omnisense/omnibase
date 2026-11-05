<?php

namespace Tests\Base\Validator;

use Base\Database\Repository\Entities;
use Base\Database\Repository\Repositories;
use Base\Entity\Layout\Redirection;
use Base\Entity\Layout\TextOverride;
use Base\Validator\Constraints\Unique;
use Base\Validator\Constraints\UniqueValidator;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;

/**
 * Unique, symfony/doctrine-bridge's UniqueEntity without Doctrine (plan "omnibase agnostic", step E1):
 * the two entities that named UniqueEntity name it, and it answers as UniqueEntity did - the records
 * looked up by findBy(criteria, null, 2) through the persistence's Repositories, the violation at the
 * first field (or errorPath) with UniqueEntity's code and its value formatted as there; nothing when
 * the one record found is the one validated, or when a field is null (ignoreNull).
 */
final class UniqueTest extends TestCase
{
    public function testTheTwoEntitiesDeclareUniqueAsTheyDeclaredUniqueEntity(): void
    {
        $redirection = (new \ReflectionClass(Redirection::class))->getAttributes(Unique::class)[0]->newInstance();
        self::assertSame(['source'], $redirection->fields);
        self::assertSame('This address is already redirected: edit that one.', $redirection->message);

        $override = (new \ReflectionClass(TextOverride::class))->getAttributes(Unique::class)[0]->newInstance();
        self::assertSame(['domain', 'key', 'locale'], $override->fields);
        self::assertSame('This text is already rewritten in this language: edit that one.', $override->message);
        self::assertSame(Constraint::CLASS_CONSTRAINT, $override->getTargets());
        self::assertSame('23bd9dbf-6b9b-41cd-a99e-4844bcf3077f', Unique::NOT_UNIQUE_ERROR);
    }

    public function testATakenValueIsRefusedAtTheFirstField(): void
    {
        $other = new UniqueRecord('/a', 'x');
        [$violations, $asked] = $this->validate(new UniqueRecord('/a', 'y'), new Unique(['source', 'target'], message: 'Taken.'), [$other]);

        self::assertSame([['source' => '/a', 'target' => 'y'], null, 2], \array_slice($asked, 0, 3));
        self::assertCount(1, $violations);
        self::assertSame('source', $violations[0]->getPropertyPath());
        self::assertSame('Taken.', $violations[0]->getMessage());
        self::assertSame(Unique::NOT_UNIQUE_ERROR, $violations[0]->getCode());
        self::assertSame(['{{ value }}' => '"/a"'], $violations[0]->getParameters());
        self::assertSame('/a', $violations[0]->getInvalidValue());
        self::assertSame([$other], $violations[0]->getCause());
    }

    public function testTheErrorPathAndTheDefaultMessage(): void
    {
        [$violations] = $this->validate(new UniqueRecord('/a', 'y'), new Unique(['source', 'target'], errorPath: 'target'), [new UniqueRecord('/a', 'y')]);

        self::assertSame('target', $violations[0]->getPropertyPath());
        self::assertSame('This value is already used.', $violations[0]->getMessage());
        self::assertSame(['{{ value }}' => '"y"'], $violations[0]->getParameters());
    }

    public function testTheRecordItselfANullAndNothingFoundPass(): void
    {
        $record = new UniqueRecord('/a', 'y');
        self::assertCount(0, $this->validate($record, new Unique('source'), [$record])[0]);
        self::assertCount(0, $this->validate($record, new Unique('source'), [])[0]);

        [$violations, $asked] = $this->validate(new UniqueRecord(null, 'y'), new Unique(['source', 'target']), [new UniqueRecord('/a', 'y')]);
        self::assertCount(0, $violations);
        self::assertNull($asked, 'a null field ignored: no lookup');

        [$violations] = $this->validate(new UniqueRecord(null, 'y'), new Unique(['source', 'target'], ignoreNull: false), [new UniqueRecord(null, 'y')]);
        self::assertCount(1, $violations);
        self::assertSame(['{{ value }}' => 'null'], $violations[0]->getParameters());
    }

    /**
     * @param list<object> $found
     *
     * @return array{0: \Symfony\Component\Validator\ConstraintViolationListInterface, 1: list<mixed>|null}
     */
    private function validate(object $record, Unique $constraint, array $found): array
    {
        $asked = null;
        $entities = $this->createMock(Entities::class);
        $entities->method('findBy')->willReturnCallback(static function (...$arguments) use (&$asked, $found): array {
            $asked = $arguments;

            return $found;
        });
        $repositories = $this->createMock(Repositories::class);
        $repositories->method('of')->with(UniqueRecord::class)->willReturn($entities);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('className')->willReturnCallback(static fn (object $entity): string => $entity::class);

        $validator = new UniqueValidator($unitOfWork, $repositories);
        $factory = new class($validator) extends ConstraintValidatorFactory {
            public function __construct(private readonly UniqueValidator $unique)
            {
                parent::__construct();
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return $constraint instanceof Unique ? $this->unique : parent::getInstance($constraint);
            }
        };

        $violations = Validation::createValidatorBuilder()->setConstraintValidatorFactory($factory)->getValidator()->validate($record, $constraint);

        return [$violations, $asked];
    }
}

final class UniqueRecord
{
    public function __construct(private ?string $source, protected ?string $target)
    {
    }
}
