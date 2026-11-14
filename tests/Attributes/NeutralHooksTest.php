<?php

namespace Tests\Base\Attributes;

use Base\Attributes\AbstractAttribute;
use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Base\Database\Attribute\Alias;
use Base\Database\Attribute\Associate;
use Base\Database\Attribute\Blameable;
use Base\Database\Attribute\Cache;
use Base\Database\Attribute\Countify;
use Base\Database\Attribute\GenerateUuid;
use Base\Database\Attribute\Hashify;
use Base\Database\Attribute\OrderColumn;
use Base\Database\Attribute\Randomize;
use Base\Database\Attribute\Slugify;
use Base\Database\Attribute\Timestamp;
use Base\Database\Attribute\Timezone;
use Base\Database\Attribute\Uploader;
use Base\Database\Attribute\Vault;
use Base\Entity\Layout\TextOverride;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Persistence\Lifecycle;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The behaviours' neutral hooks (plan "omnibase agnostic", step F2b): omnisense/omnibridge's lifecycle
 * events in place of Doctrine's arguments, dispatched by the Doctrine relay (AttributeSubscriber) before
 * the Doctrine-typed hooks, which the applications' and bundles' attributes may still override; and the
 * unit of work they carry, omnibridge's contract over the entity manager.
 */
final class NeutralHooksTest extends KernelTestCase
{
    private const DOMAIN = 'neutral-hooks-test';

    /** AbstractAttribute's ten neutral hooks take omnibridge's events, no ORM type. */
    public function testTheNeutralHooksTakeOmnibridgesEvents(): void
    {
        $hooks = [
            'onPrePersist' => Lifecycle\PrePersist::class, 'onPreUpdate' => Lifecycle\PreUpdate::class, 'onPreRemove' => Lifecycle\PreRemove::class,
            'onPostLoad' => Lifecycle\PostLoad::class, 'onPostPersist' => Lifecycle\PostPersist::class, 'onPostUpdate' => Lifecycle\PostUpdate::class,
            'onPostRemove' => Lifecycle\PostRemove::class, 'onPreFlush' => Lifecycle\PreFlush::class, 'onOnFlush' => Lifecycle\OnFlush::class,
            'onPostFlush' => Lifecycle\PostFlush::class,
        ];
        foreach ($hooks as $hook => $event) {
            $parameters = (new \ReflectionMethod(AbstractAttribute::class, $hook))->getParameters();
            self::assertSame($event, (string) $parameters[0]->getType(), $hook);
        }
    }

    /** The behaviours moved to the neutral hooks no longer declare a Doctrine-typed one of their own. */
    public function testTheMovedBehavioursAnswerTheNeutralHooks(): void
    {
        $moved = [
            Timestamp::class => ['onPrePersist', 'onPreUpdate'], Timezone::class => ['onPrePersist', 'onPreUpdate'],
            Blameable::class => ['onPrePersist', 'onPreUpdate'], Countify::class => ['onPostLoad', 'onPrePersist', 'onPreUpdate'],
            Hashify::class => ['onPrePersist', 'onPreUpdate', 'onPostPersist', 'onPostUpdate'], Randomize::class => ['onOnFlush'],
            GenerateUuid::class => ['onOnFlush'],
            Vault::class => ['onPreFlush', 'onPrePersist', 'onPreUpdate', 'onPostFlush', 'onPostLoad'], Slugify::class => ['onOnFlush'],
            Cache::class => ['onPrePersist', 'onPreUpdate', 'onPreRemove'], Uploader::class => ['onPreFlush', 'onOnFlush', 'onPreUpdate', 'onPostRemove'],
            Alias::class => ['onPostLoad', 'onPrePersist', 'onPreUpdate'], OrderColumn::class => ['onPrePersist', 'onPreUpdate', 'onPostLoad'],
            Associate::class => ['onPrePersist', 'onPreUpdate', 'onPostPersist', 'onPostUpdate', 'onPostLoad'],
        ];
        foreach ($moved as $class => $hooks) {
            foreach ($hooks as $hook) {
                self::assertSame($class, (new \ReflectionMethod($class, $hook))->getDeclaringClass()->getName(), $class.'::'.$hook);
            }
            foreach (['prePersist', 'preUpdate', 'preRemove', 'postLoad', 'postPersist', 'postUpdate', 'postRemove', 'preFlush', 'onFlush', 'postFlush'] as $doctrineHook) {
                self::assertSame(AbstractAttribute::class, (new \ReflectionMethod($class, $doctrineHook))->getDeclaringClass()->getName(), $class.'::'.$doctrineHook);
            }
        }
    }

    /** The unit of work over Doctrine: new, persisted, flushed, changed, removed. */
    public function testTheDoctrineUnitOfWorkAnswersOmnibridgesContract(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $unitOfWork = new DoctrineUnitOfWork($entityManager);
        self::assertInstanceOf(UnitOfWork::class, $unitOfWork);

        $override = (new TextOverride())->setDomain(self::DOMAIN)->setKey('greeting')->setLocale('en-GB')->setValue('Hello');
        self::assertTrue($unitOfWork->isNew($override));
        self::assertFalse($unitOfWork->contains($override));

        $unitOfWork->persist($override);
        self::assertTrue($unitOfWork->contains($override));
        self::assertTrue($unitOfWork->isNew($override), 'persisted, not flushed: no row yet');
        self::assertSame(TextOverride::class, $unitOfWork->className($override));

        $unitOfWork->transactional(static fn (UnitOfWork $work) => null);
        self::assertFalse($unitOfWork->isNew($override));
        self::assertIsInt($unitOfWork->identifier($override));

        $override->setValue('Hi');
        $entityManager->getUnitOfWork()->computeChangeSets();
        self::assertSame(['Hello', 'Hi'], $unitOfWork->changes($override)['value'] ?? null);

        $id = $unitOfWork->identifier($override);
        self::assertSame($override, $unitOfWork->reference(TextOverride::class, $id));

        $unitOfWork->remove($override);
        $unitOfWork->flush();
        self::assertFalse($unitOfWork->contains($override));
    }
}
