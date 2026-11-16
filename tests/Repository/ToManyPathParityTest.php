<?php

namespace Tests\Base\Repository;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\User;
use Base\Entity\User\Group;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A criterion whose path ends on a to-many association, compared with an entity ('groups.members' => $user: the
 * accounts that share a group with $user), as the one that ends on a to-one: the entity one of the collection's
 * members, on both engines (Doctrine compiled "e_groups.members = :x", no DQL; gitbud wrote members.id).
 */
final class ToManyPathParityTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return Engines::backends();
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */
    #[DataProvider('backends')]
    public function testAnEntityComparedWithACollectionAtThePathsEnd(string $backend): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            self::markTestSkipped('Needs omnibridge/doctrine and omnibridge/eloquent.');
        }
        $engines = Engines::of([User::class, Group::class]);
        $description = $engines->description();
        $users = $description->entity(User::class) ?? throw new \LogicException('No User.');
        $groups = $description->entity(Group::class) ?? throw new \LogicException('No Group.');
        $link = $users->relations['groups']->joinTable ?? throw new \LogicException('No User::$groups join table.');

        $ids = [];
        foreach (['anne', 'bob', 'carl'] as $name) {
            $ids[$name] = $engines->insert((string) $users->table, self::row($users) + self::discriminated($engines, User::class));
        }
        $teams = [];
        foreach (['one', 'two'] as $name) {
            $teams[$name] = $engines->insert((string) $groups->table, self::row($groups) + self::discriminated($engines, Group::class));
        }
        foreach ([['anne', 'one'], ['bob', 'one'], ['carl', 'two']] as [$user, $team]) {
            $engines->insert($link->name, [$link->joinColumns[0]->name => $ids[$user], $link->inverseJoinColumns[0]->name => $teams[$team]]);
        }

        $repository = new ServiceEntityRepository($engines->finders($backend), User::class);
        $anne = $repository->find($ids['anne']);
        $carl = $repository->find($ids['carl']);
        self::assertInstanceOf(User::class, $anne);
        self::assertInstanceOf(User::class, $carl);
        $found = static fn (array $users): array => array_map(static fn (User $user): int => (int) $user->getId(), $users);

        self::assertSame([$ids['anne'], $ids['bob']], $found($repository->findBy(['groups.members' => $anne], ['id' => 'ASC'])), 'who shares a group with anne');
        self::assertSame([$ids['carl']], $found($repository->findBy(['groups.members' => $carl], ['id' => 'ASC'])));
        self::assertSame(array_values($ids), $found($repository->findBy(['groups.members' => [$anne, $carl]], ['id' => 'ASC'])), 'with any of them');
        self::assertSame(2, $repository->count(['groups.members' => $anne]));
    }

    /** @return array<string, string> the discriminator column of the class, when it has one */
    private static function discriminated(Engines $engines, string $class): array
    {
        $root = $engines->description()->entity($class);
        $root = null !== $root?->root ? $engines->description()->entity($root->root) : $root;
        $column = $root?->inheritance?->column->name;

        return null === $column ? [] : [$column => $engines->discriminator($class)];
    }

    /** @return array<string, mixed> every column a row needs: the non-nullable ones given a neutral value */
    private static function row(?\Omnibridge\Schema\Entity $entity): array
    {
        $row = [];
        foreach ($entity?->fields ?? [] as $field) {
            if ($field->id || $field->nullable || null !== $field->inheritedFrom && $field->inheritedFrom !== $entity->class) {
                continue;
            }
            $row[$field->column] = match (true) {
                str_contains($field->type, 'int') || str_contains($field->type, 'bool') || str_contains($field->type, 'decimal') || str_contains($field->type, 'float') => 0,
                str_contains($field->type, 'date') || str_contains($field->type, 'time') => '2026-01-01 00:00:00',
                str_contains($field->type, 'json') || str_contains($field->type, 'array') => '[]',
                default => 'x'.bin2hex(random_bytes(3)),
            };
        }

        return $row;
    }
}
