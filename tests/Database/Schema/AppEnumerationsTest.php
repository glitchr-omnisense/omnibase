<?php

namespace Tests\Base\Database\Schema;

use Base\Database\Schema\EntitySchema;
use Base\Discovery;
use Base\Entity\Thread;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The Base\ / App\ convention for the core's column types: App\Enum\X is the type of its name where the host declares
 * one (App\Enum\UserRole extending Base\Enum\UserRole with its own roles), Base\Enum\X through the alias otherwise. Every
 * registry agrees: the schema's (EntitySchema::types(), what Eloquent casts and the tables are made from), Doctrine's
 * (omnibridge/doctrine's Types, registered by Engines as a host does) and the Symfony host's discovery (Base\Discovery).
 * A private site's schema:validate wanted the core's 4 roles in place of its 9.
 */
final class AppEnumerationsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAHostsEnumerationWithMoreCasesIsTheType(): void
    {
        eval('namespace App\Enum; class UserRole extends \Base\Enum\UserRole { public const MANAGER = "ROLE_MANAGER"; public const COACH = "ROLE_COACH"; }');

        self::assertSame(['App\Enum\UserRole', ['ROLE_COACH', 'ROLE_MANAGER']], $this->userRole());
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWithoutOneTheCoresIsTheType(): void
    {
        self::assertSame([\Base\Enum\UserRole::class, []], $this->userRole());
    }

    /** @return array{0: class-string, 1: list<string>} the class of user_role, the same in each registry, and its roles beyond the core's */
    private function userRole(): array
    {
        $options = ['project_dir' => sys_get_temp_dir().'/omnibase-no-project'];
        $schema = EntitySchema::types($options)['user_role'] ?? null;
        $discovered = Discovery::enumerations($options['project_dir'])['user_role'] ?? null;
        self::assertSame($schema, $discovered, 'the Symfony host\'s discovery');

        $roles = EntitySchema::columnTypes($options)->sets['user_role'] ?? [];
        self::assertSame($schema::getPermittedValues(), $roles, 'the set the tables and Eloquent\'s casts are made from');

        if (class_exists(\Omnibridge\Doctrine\EntityManagers::class) && class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            $engines = Engines::of([Thread::class], $options);
            // The connection's type provider (omnibridge/doctrine 4.0: DBAL's static registry holds none of omnibase's types).
            $entityManager = (new \ReflectionProperty(Engines::class, 'entityManager'))->getValue($engines);
            \assert($entityManager instanceof \Doctrine\ORM\EntityManagerInterface);
            $doctrine = $entityManager->getConnection()->getConfiguration()->getTypeProvider()->get('user_role');
            self::assertInstanceOf(\Omnibridge\Doctrine\Omnibase\EnumTypeAdapter::class, $doctrine);
            self::assertSame($schema, $doctrine->enumeration(), 'Doctrine\'s type');
        }

        $extra = array_values(array_diff($roles, \Base\Enum\UserRole::getPermittedValues()));
        sort($extra);

        return [(string) $schema, $extra];
    }
}
