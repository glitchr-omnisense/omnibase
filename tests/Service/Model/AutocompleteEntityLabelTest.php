<?php

namespace Tests\Base\Service\Model;

use Base\Entity\User\Group;
use Base\Entity\User\Permission;
use Base\Service\Model\Autocomplete;
use Base\Service\TranslatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * What a picker lists for a record: a group by its name, a permission by its
 * tag, and a record without a name of its own by the noun of its class (or
 * of the nearest parent that has one) and its id - never the translation key
 * of the class. A permission's group picker on wedding and genealogist read
 * "occasion_group._singular #1" and "tree_group._singular #1".
 */
class AutocompleteEntityLabelTest extends TestCase
{
    private function autocomplete(): Autocomplete
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('transEntityExists')->willReturnCallback(
            static fn (mixed $class): bool => \in_array($class, [AutocompleteNamedBase::class, Group::class, Permission::class], true)
        );
        $translator->method('transEntity')->willReturnCallback(
            static fn (mixed $class): string => match ($class) {
                AutocompleteNamedBase::class => 'chose',
                Group::class => 'groupe',
                Permission::class => 'permission',
                default => 'autocomplete_raw_key._singular',
            }
        );

        return new Autocomplete($translator);
    }

    public function testAGroupIsListedByItsName(): void
    {
        $group = new AutocompleteGroup();
        $group->setName('Mariage de Léa & Hugo · Hôtes');

        $this->assertSame('Mariage de Léa & Hugo · Hôtes', $this->autocomplete()->resolve($group, AutocompleteGroup::class)['text']);
    }

    public function testAPermissionIsListedByItsTag(): void
    {
        $permission = new Permission();
        $permission->setTag('guests.edit');

        $this->assertSame('guests.edit', $this->autocomplete()->resolve($permission, Permission::class)['text']);
    }

    public function testARecordWithoutANameTakesTheNounOfItsNearestNamedClass(): void
    {
        $this->assertSame('chose #7', $this->autocomplete()->resolve(new AutocompleteNamedChild(), AutocompleteNamedChild::class)['text']);
    }

    public function testARecordWhoseClassesHaveNoNounTakesItsShortClassName(): void
    {
        $this->assertSame('AutocompleteOrphan #3', $this->autocomplete()->resolve(new AutocompleteOrphan(), AutocompleteOrphan::class)['text']);
    }
}

/** An application's group, its class named by no translation (OccasionGroup, TreeGroup). */
class AutocompleteGroup extends Group
{
}

class AutocompleteNamedBase
{
    public function getId(): int
    {
        return 7;
    }
}

class AutocompleteNamedChild extends AutocompleteNamedBase
{
}

class AutocompleteOrphan
{
    public function getId(): int
    {
        return 3;
    }
}
