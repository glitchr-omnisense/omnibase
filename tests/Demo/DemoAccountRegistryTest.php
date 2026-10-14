<?php

namespace Tests\Base\Demo;

use Base\Demo\DemoAccount;
use Base\Demo\DemoAccountProviderInterface;
use Base\Demo\DemoAccountRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The declared demonstration accounts gathered: a bundle's and the
 * application's, the application's kept for the same identifier, the
 * excluded ones dropped - and never a super-administrator.
 */
class DemoAccountRegistryTest extends TestCase
{
    /** @param DemoAccount[] $accounts */
    private function provider(array $accounts): DemoAccountProviderInterface
    {
        return new class($accounts) implements DemoAccountProviderInterface {
            public function __construct(private array $accounts)
            {
            }

            public function getDemoAccounts(): iterable
            {
                return $this->accounts;
            }
        };
    }

    public function testTheDeclaredAccountsAreGatheredInOrder(): void
    {
        $registry = new DemoAccountRegistry([
            $this->provider([new DemoAccount('patient', 'Patient', position: 20), new DemoAccount('docteur', 'Médecin', 'Son agenda.', group: 'Praticiens', groupRoles: ['ROLE_PRACTITIONER'], position: 10)]),
            $this->provider([new DemoAccount('client', 'Client')]),
        ]);

        $this->assertSame(['client', 'docteur', 'patient'], array_keys($registry->all()), 'by position, then as declared');
        $this->assertSame('Médecin', $registry->get('docteur')->label);
        $this->assertSame('docteur', $registry->get('docteur')->getPassword(), 'the password is the identifier unless one is given');
        $this->assertSame('docteur@example.org', $registry->get('docteur')->getEmail());
        $this->assertSame(['ROLE_USER', 'ROLE_PRACTITIONER'], $registry->get('docteur')->getAllRoles());
        $this->assertTrue($registry->has('patient'));
        $this->assertFalse($registry->has('nobody'));
        $this->assertNull($registry->get(null));
    }

    public function testALaterDeclarationReplacesAnEarlierOneAndExcludedAccountsAreDropped(): void
    {
        $registry = new DemoAccountRegistry([
            $this->provider([new DemoAccount('docteur', 'Praticien'), new DemoAccount('kine', 'Kinésithérapeute')]),
            $this->provider([new DemoAccount('docteur', 'Dr Tilleul')]),
        ], ['kine']);

        $this->assertSame(['docteur'], array_keys($registry->all()));
        $this->assertSame('Dr Tilleul', $registry->get('docteur')->label);
    }

    public function testAnAccountIsRecognisedByItsIdentifier(): void
    {
        $registry = new DemoAccountRegistry([$this->provider([new DemoAccount('patient', 'Patient')])]);

        $this->assertSame('patient', $registry->of(new InMemoryUser('patient', null))?->identifier);
        $this->assertNull($registry->of(new InMemoryUser('someone', null)));
        $this->assertNull($registry->of(null));
    }

    public function testASuperAdministratorIsNeverADemonstrationAccount(): void
    {
        $registry = new DemoAccountRegistry([$this->provider([new DemoAccount('glitchr', 'Glitch Art', roles: ['ROLE_SUPERADMIN'])])]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be a demonstration account');
        $registry->all();
    }

    public function testNorAnAccountWhoseRolesReachItThroughAGroupOrTheHierarchy(): void
    {
        $hierarchy = new RoleHierarchy(['ROLE_EDITOR' => ['ROLE_SUPERADMIN'], 'ROLE_SUPERADMIN' => ['ROLE_ADMIN']]);

        $this->assertTrue((new DemoAccountRegistry([], [], $hierarchy))->reachesSuperAdmin(['ROLE_EDITOR']));
        $this->assertFalse((new DemoAccountRegistry([], [], $hierarchy))->reachesSuperAdmin(['ROLE_ADMIN']));
        $this->assertTrue((new DemoAccountRegistry([], [], $hierarchy))->isSuperAdmin(new InMemoryUser('root', null, ['ROLE_EDITOR'])));

        $registry = new DemoAccountRegistry([$this->provider([new DemoAccount('agence', 'Agence', group: 'Éditeurs', groupRoles: ['ROLE_EDITOR'])])], [], $hierarchy);
        $this->expectException(\LogicException::class);
        $registry->all();
    }

    public function testAnAccountThatOpensTheBackOfficeIsDeclaredAndOfferedToNobody(): void
    {
        $hierarchy = new RoleHierarchy(['ROLE_SUPERADMIN' => ['ROLE_ADMIN'], 'ROLE_ADMIN' => ['ROLE_STAFF'], 'ROLE_MANAGER' => ['ROLE_ADMIN']]);
        $accounts = [$this->provider([
            new DemoAccount('boutique', 'La boutique', roles: ['ROLE_ADMIN'], position: 10),
            new DemoAccount('gerant', 'Le gérant', group: 'Gérance', groupRoles: ['ROLE_MANAGER'], position: 20),
            new DemoAccount('cuisine', 'La cuisine', roles: ['ROLE_STAFF'], position: 30),
            new DemoAccount('client', 'Client', position: 40),
        ])];

        $registry = new DemoAccountRegistry($accounts, [], $hierarchy);
        $this->assertSame(['boutique', 'gerant', 'cuisine', 'client'], array_keys($registry->all()), 'the fixtures create them all');
        $this->assertSame(['cuisine', 'client'], array_keys($registry->offered()), 'a visitor is offered those below the back office');
        $this->assertTrue($registry->reachesAdministration(['ROLE_MANAGER']), 'through the hierarchy');
        $this->assertFalse($registry->reachesAdministration(['ROLE_STAFF']));
        $this->assertTrue($registry->isAdministrator(new InMemoryUser('root', null, ['ROLE_SUPERADMIN'])), 'the super-administrator\'s roles reach it');
        $this->assertFalse($registry->isAdministrator(null));

        // base.demo.admin_roles: another role opens the back office here - or none, and it is as public as the rest.
        $this->assertSame(['client'], array_keys((new DemoAccountRegistry($accounts, [], $hierarchy, ['ROLE_STAFF']))->offered()));
        $this->assertSame(['boutique', 'gerant', 'cuisine', 'client'], array_keys((new DemoAccountRegistry($accounts, [], $hierarchy, []))->offered()));
    }

    public function testEachRoleBelowTheBackOfficeHasAnAccount(): void
    {
        $hierarchy = new RoleHierarchy(['ROLE_SUPERADMIN' => ['ROLE_ADMIN'], 'ROLE_ADMIN' => ['ROLE_STAFF'], 'ROLE_MANAGER' => ['ROLE_ADMIN'], 'ROLE_EDITOR' => ['ROLE_SUPERADMIN']]);
        $roles = ['ROLE_EDITOR', 'ROLE_SUPERADMIN', 'ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_STAFF', 'ROLE_ARTIST', 'ROLE_USER'];

        $registry = new DemoAccountRegistry([$this->provider([
            new DemoAccount('boutique', 'La boutique', roles: ['ROLE_ADMIN']),
            new DemoAccount('client', 'Client'),
        ])], [], $hierarchy);
        $this->assertSame(['ROLE_STAFF', 'ROLE_ARTIST'], $registry->missingRoles($roles), 'the back office\'s roles need none; ROLE_USER is any account\'s');

        $registry = new DemoAccountRegistry([$this->provider([
            new DemoAccount('cuisine', 'La cuisine', roles: ['ROLE_STAFF']),
            new DemoAccount('artiste', 'Artiste', group: 'Artistes', groupRoles: ['ROLE_ARTIST']),
        ])], [], $hierarchy);
        $this->assertSame([], $registry->missingRoles($roles), 'a group\'s role counts');

        $this->assertSame(['ROLE_USER'], (new DemoAccountRegistry([], [], $hierarchy))->missingRoles(['ROLE_ADMIN', 'ROLE_USER']), 'no account at all: not even the public\'s');
    }
}
