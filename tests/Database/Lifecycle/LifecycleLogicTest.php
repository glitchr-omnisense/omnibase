<?php

namespace Tests\Base\Database\Lifecycle;

use Base\Database\Attribute\Trasheable;
use Base\Database\Attribute\Versionable;
use Base\Database\Lifecycle\RevisionRecorder;
use Base\Database\Lifecycle\SettingsInvalidation;
use Base\Database\Lifecycle\TrashRecorder;
use Base\Entity\Layout\Setting;
use Base\Enum\EntityAction;
use Base\Service\SettingBagInterface;
use Omnibridge\Persistence\Lifecycle;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * The lifecycle listeners' logic, split from their Doctrine wiring (plan "omnibase agnostic", step F2b):
 * the settings' invalidation, the trash entry and the revision, on a unit of work that is omnibridge's
 * contract alone - no entity manager.
 */
final class LifecycleLogicTest extends TestCase
{
    public function testASettingWrittenEmptiesTheSnapshotAndNothingElseDoes(): void
    {
        $cleared = 0;
        $bag = $this->createMock(SettingBagInterface::class);
        $bag->method('clearAll')->willReturnCallback(function () use (&$cleared, $bag) {
            ++$cleared;

            return $bag;
        });
        $invalidation = new SettingsInvalidation(static fn () => $bag);
        $unitOfWork = $this->createStub(UnitOfWork::class);

        $invalidation->onPostUpdate(new Lifecycle\PostUpdate(new \stdClass(), $unitOfWork));
        self::assertSame(0, $cleared);

        $invalidation->onPostPersist(new Lifecycle\PostPersist(new Setting('base.settings.title'), $unitOfWork));
        $invalidation->onPostRemove(new Lifecycle\PostRemove(new Setting('base.settings.title'), $unitOfWork));
        self::assertSame(2, $cleared);
    }

    public function testTheTrashEntrySnapshotsTheLabelAndTheExpiry(): void
    {
        $recorder = new TrashRecorder(5);
        self::assertSame('+5 days', TrashRecorder::expiry(5));
        self::assertSame('+1 month', TrashRecorder::expiry('+1 month'));
        self::assertTrue($recorder->isPurge(new \DateTime()), 'in the trash already: deleted for real');
        self::assertFalse($recorder->isPurge(null));

        $entity = new class() {
            public function getId(): int
            {
                return 42;
            }

            public function __toString(): string
            {
                return ' An article ';
            }
        };
        $deletedAt = new \DateTime('2026-11-01 10:00:00');
        $entry = $recorder->entry($entity, 'App\Entity\Article', new Trasheable(), $deletedAt);

        self::assertSame('App\Entity\Article', $entry->getEntityClass());
        self::assertSame(EntityAction::DELETE, $entry->getAction());
        self::assertSame(['label' => 'An article', 'deletedAt' => $deletedAt->format(DATE_ATOM)], $entry->getEntityData());
        self::assertEqualsWithDelta((new \DateTime('+5 days'))->getTimestamp(), $entry->getPermanentAfter()->getTimestamp(), 5);

        $own = $recorder->entry($entity, 'App\Entity\Article', new Trasheable(expiry: '2'), $deletedAt);
        self::assertEqualsWithDelta((new \DateTime('+2 days'))->getTimestamp(), $own->getPermanentAfter()->getTimestamp(), 5, 'the attribute\'s own expiry first');
    }

    public function testTheRevisionRecordsTheDiffOnceAndEntitiesByIdentifier(): void
    {
        $author = new class() {
            public function __toString(): string
            {
                return 'Ringo';
            }
        };
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('contains')->willReturnCallback(static fn (object $entity) => $entity === $author);
        $unitOfWork->method('identifier')->willReturn(7);
        $recorder = new RevisionRecorder($unitOfWork);

        $versioned = ['title' => new Versionable(), 'author' => new Versionable(), 'tags' => new Versionable(), 'state' => new Versionable()];
        $diff = $recorder->diff(
            $versioned,
            ['title' => ['Help', 'Help!'], 'author' => [null, $author], 'state' => ['draft', 'draft']],
            ['tags' => [['rock'], ['rock', 'pop']]],
            'en.',
        );
        self::assertSame([
            'en.title' => ['Help', 'Help!'],
            'en.author' => [null, ['id' => 7, 'label' => 'Ringo']],
            'en.tags' => [['rock'], ['rock', 'pop']],
        ], $diff, 'an unchanged value is no change');

        $target = new class() {
            public function getId(): int
            {
                return 3;
            }
        };
        $revision = $recorder->revision('App\Entity\Article', $target, $diff);
        self::assertNotNull($revision);
        self::assertSame($diff, $revision->getEntityData());
        self::assertSame(EntityAction::UPDATE, $revision->getAction());
        self::assertNull($recorder->revision('App\Entity\Article', $target, $diff), 'the same diff again: no second revision');
        self::assertNotNull($recorder->revision('App\Entity\Article', $target, ['en.title' => ['Help!', 'Help']]));
    }
}
