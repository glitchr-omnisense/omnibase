<?php

namespace Tests\Base\Repository;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\User;
use Base\Entity\User\Group;
use Base\Entity\User\Permission;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A user leaving a group, or losing a permission: the link removed (the join table's row), the group or the permission
 * kept - shared with other users and groups. Through the collection, on both engines (User::$groups and
 * User::$permissions no longer remove their target as an orphan: gitbud's leaveGroups()).
 */
final class LeaveGroupParityTest extends TestCase
{
    /** @return array<string, array{0: string, 1: class-string, 2: string, 3: string}> */
    public static function links(): array
    {
        return [
            'groups on doctrine' => ['doctrine', Group::class, 'groups', 'removeGroup'],
            'groups on eloquent' => ['eloquent', Group::class, 'groups', 'removeGroup'],
            'permissions on doctrine' => ['doctrine', Permission::class, 'permissions', 'removePermission'],
            'permissions on eloquent' => ['eloquent', Permission::class, 'permissions', 'removePermission'],
        ];
    }

    /** @dataProvider links (PHPUnit 9, the harness's) */

    #[DataProvider('links')]
    public function testRemovingALinkKeepsTheSharedTarget(string $backend, string $target, string $property, string $remove): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            self::markTestSkipped('Needs omnibridge/doctrine and omnibridge/eloquent.');
        }
        $engines = Engines::of([User::class, $target]);
        $description = $engines->description();
        $users = $description->entity(User::class);
        $relation = $users?->relations[$property] ?? throw new \LogicException('No User::$'.$property.'.');
        $userId = $engines->insert((string) $users->table, self::row($users) + self::discriminated($engines, User::class));
        $groupId = $engines->insert((string) $description->entity($target)?->table, self::row($description->entity($target)) + self::discriminated($engines, $target));
        $engines->insert($relation->joinTable->name, [$relation->joinTable->joinColumns[0]->name => $userId, $relation->joinTable->inverseJoinColumns[0]->name => $groupId]);

        $finders = $engines->finders($backend);
        $user = (new ServiceEntityRepository($finders, User::class))->find($userId);
        self::assertInstanceOf(User::class, $user);
        $group = $user->{'get'.ucfirst($property)}()->first();
        self::assertInstanceOf($target, $group);
        $user->{$remove}($group);
        $finders->unitOfWork()->persist($user);
        $finders->unitOfWork()->flush();

        self::assertSame(0, (int) $engines->pdo()->query('SELECT COUNT(*) FROM "'.$relation->joinTable->name.'"')->fetchColumn(), 'the link removed');
        self::assertSame(1, (new ServiceEntityRepository($engines->finders($backend), $target))->count(['id' => $groupId]), 'the shared target kept');
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
