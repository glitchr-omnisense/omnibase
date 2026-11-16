<?php

namespace Tests\Base\Validator;

use Base\Entity\Thread\Tag;
use Base\Service\TranslatorInterface;
use Base\Testing\Engines;
use Base\Validator\ConstraintEntityValidator;
use Base\Validator\Constraints\StringCaseEntity;
use Base\Validator\Constraints\StringCaseEntityValidator;
use Base\Validator\Constraints\UniqueEntity;
use Base\Validator\Constraints\UniqueEntityValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;

/**
 * The entity validators (UniqueEntity, StringCaseEntity, over ConstraintEntityValidator) on any persistence: the host's
 * unit of work and the entities' shapes, the same violations on Doctrine and on Eloquent over the same rows.
 */
final class EntityValidatorsParityTest extends TestCase
{
    private ?Engines $engines = null;

    protected function setUp(): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            self::markTestSkipped('Needs omnibridge/doctrine and omnibridge/eloquent.');
        }
        $this->engines = Engines::of([Tag::class]);
        $table = (string) $this->engines->description()->entity(Tag::class)?->table;
        $this->engines->insert($table, ['slug' => 'red', 'priority' => 0, 'class' => $this->engines->discriminator(Tag::class)]);
    }

    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return Engines::backends();
    }

    /** @return list<string> the violations' messages */
    private function violations(string $backend, object $entity, Constraint $constraint, ConstraintEntityValidator $validator, ?\Base\Database\Repository\Finders $finders = null): array
    {
        // The Finders the entity was read with (another call clears the identity maps: the entity would be detached).
        $validator->setUnitOfWork(($finders ?? ($this->engines ?? throw new \LogicException())->finders($backend))->unitOfWork());
        (new \ReflectionProperty($validator, 'translator'))->setValue($validator, $this->createStub(TranslatorInterface::class));
        $factory = new class($validator) implements ConstraintValidatorFactoryInterface {
            public function __construct(private readonly ConstraintValidatorInterface $validator)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return $this->validator;
            }
        };
        $list = Validation::createValidatorBuilder()->setConstraintValidatorFactory($factory)->getValidator()->validate($entity, $constraint);

        return array_map(static fn ($violation): string => (string) $violation->getMessageTemplate(), iterator_to_array($list));
    }

    private function newTag(string $slug): Tag
    {
        $tag = (new \ReflectionClass(Tag::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Tag::class, 'slug'))->setValue($tag, $slug);

        return $tag;
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */

    #[DataProvider('backends')]
    public function testUniqueEntity(string $backend): void
    {
        $engines = $this->engines ?? throw new \LogicException();
        self::assertCount(1, $this->violations($backend, $this->newTag('red'), new UniqueEntity(['slug']), new UniqueEntityValidator()), 'a row has that slug');
        self::assertSame([], $this->violations($backend, $this->newTag('blue'), new UniqueEntity(['slug']), new UniqueEntityValidator()));
        $finders = $engines->finders($backend);
        $red = (new \Base\Database\Repository\ServiceEntityRepository($finders, Tag::class))->findOneBy(['slug' => 'red']);
        self::assertInstanceOf(Tag::class, $red);
        self::assertSame([], $this->violations($backend, $red, new UniqueEntity(['slug']), new UniqueEntityValidator(), $finders), 'the row itself');
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */

    #[DataProvider('backends')]
    public function testStringCaseEntity(string $backend): void
    {
        $finders = ($this->engines ?? throw new \LogicException())->finders($backend);
        $tags = new \Base\Database\Repository\ServiceEntityRepository($finders, Tag::class);
        $red = $tags->findOneBy(['slug' => 'red']);
        self::assertInstanceOf(Tag::class, $red);
        $validator = new StringCaseEntityValidator();
        $validator->setUnitOfWork($finders->unitOfWork());
        (new \ReflectionProperty(Tag::class, 'slug'))->setValue($red, 'RED');
        self::assertSame([], $this->validate($validator, $red), 'the case changed only');
        (new \ReflectionProperty(Tag::class, 'slug'))->setValue($red, 'green');
        self::assertCount(1, $this->validate($validator, $red), 'another value');
    }

    /** @return list<string> */
    private function validate(StringCaseEntityValidator $validator, Tag $tag): array
    {
        (new \ReflectionProperty($validator, 'translator'))->setValue($validator, $this->createStub(TranslatorInterface::class));
        $factory = new class($validator) implements ConstraintValidatorFactoryInterface {
            public function __construct(private readonly ConstraintValidatorInterface $validator)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return $this->validator;
            }
        };

        return array_map(static fn ($violation): string => (string) $violation->getMessageTemplate(), iterator_to_array(Validation::createValidatorBuilder()->setConstraintValidatorFactory($factory)->getValidator()->validate($tag, new StringCaseEntity(['slug']))));
    }
}
