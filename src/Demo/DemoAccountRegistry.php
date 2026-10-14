<?php

namespace Base\Demo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Every demonstration account declared by the bundles and the application
 * (DemoAccountProviderInterface), by identifier. The application's
 * declarations come last and replace a bundle's; base.demo.exclude drops the
 * ones a site has no use for.
 *
 * It exists in every environment - the fixtures of dev and test create the
 * same accounts as the demonstration's - but only the `demo` environment
 * does anything with it at sign-in (DemoMode).
 *
 * A declared account that opens the back office (base.demo.admin_roles) is
 * created like the others and offered to nobody: offered() leaves it out.
 */
class DemoAccountRegistry
{
    public const SUPER_ADMIN = 'ROLE_SUPERADMIN';

    /** @var array<string, DemoAccount>|null */
    private ?array $accounts = null;

    /**
     * @param iterable<DemoAccountProviderInterface> $providers
     * @param string[]                               $exclude    identifiers left out (base.demo.exclude)
     * @param string[]                               $adminRoles the roles that open the back office (base.demo.admin_roles)
     */
    public function __construct(
        #[AutowireIterator('base.demo_account_provider')] private readonly iterable $providers = [],
        #[Autowire('%base.demo.exclude%')] private readonly array $exclude = [],
        private readonly ?RoleHierarchyInterface $roleHierarchy = null,
        #[Autowire('%base.demo.admin_roles%')] private readonly array $adminRoles = ['ROLE_ADMIN'],
    ) {
    }

    /** @return array<string, DemoAccount> by identifier, in the order of the sign-in page */
    public function all(): array
    {
        if (null !== $this->accounts) {
            return $this->accounts;
        }

        $providers = [...$this->providers];
        // The application's own last: its declaration is the one kept.
        usort($providers, static fn (object $a, object $b): int => str_starts_with($a::class, 'App\\') <=> str_starts_with($b::class, 'App\\'));

        $accounts = [];
        foreach ($providers as $provider) {
            foreach ($provider->getDemoAccounts() as $account) {
                if ($this->reachesSuperAdmin($account->getAllRoles())) {
                    throw new \LogicException(sprintf('"%s" (declared by %s) cannot be a demonstration account: its roles reach %s. The super-administrator signs in with its own password, never through the demonstration.', $account->identifier, $provider::class, self::SUPER_ADMIN));
                }
                if (\in_array($account->identifier, $this->exclude, true)) {
                    continue;
                }
                // Replaced in place: the application's version keeps the bundle's rank unless it gives its own.
                $accounts[$account->identifier] = $account;
            }
        }

        $rank = array_flip(array_keys($accounts));
        uasort($accounts, static fn (DemoAccount $a, DemoAccount $b): int => [$a->position, $rank[$a->identifier]] <=> [$b->position, $rank[$b->identifier]]);

        return $this->accounts = $accounts;
    }

    /**
     * The declared accounts a visitor is offered - in the banner, on the
     * sign-in page, at a click: every one but those that open the back office.
     *
     * @return array<string, DemoAccount> by identifier, in the order of the sign-in page
     */
    public function offered(): array
    {
        return array_filter($this->all(), fn (DemoAccount $account): bool => !$this->reachesAdministration($account->getAllRoles()));
    }

    public function get(?string $identifier): ?DemoAccount
    {
        return null === $identifier ? null : ($this->all()[$identifier] ?? null);
    }

    public function has(?string $identifier): bool
    {
        return null !== $this->get($identifier);
    }

    /** The declaration this account was created from, if it is a demonstration account. */
    public function of(?UserInterface $user): ?DemoAccount
    {
        if (null === $user) {
            return null;
        }

        try {
            return $this->get($user->getUserIdentifier());
        } catch (\Throwable) {
            // an account being built, with no identifier yet
            return null;
        }
    }

    /**
     * Do these roles reach ROLE_SUPERADMIN, directly or through the role
     * hierarchy (a ROLE_EDITOR above it, in the sites where Glitch Art is one)?
     *
     * @param array<string|\BackedEnum> $roles
     */
    public function reachesSuperAdmin(array $roles): bool
    {
        return \in_array(self::SUPER_ADMIN, $this->reachable($roles), true);
    }

    public function isSuperAdmin(?UserInterface $user): bool
    {
        return null !== $user && $this->reachesSuperAdmin($user->getRoles());
    }

    /**
     * Do these roles open the back office - one of base.demo.admin_roles,
     * directly or through the role hierarchy? A super-administrator's do,
     * wherever the hierarchy puts ROLE_SUPERADMIN above them.
     *
     * @param array<string|\BackedEnum> $roles
     */
    public function reachesAdministration(array $roles): bool
    {
        return [] !== array_intersect($this->adminRoles, $this->reachable($roles));
    }

    public function isAdministrator(?UserInterface $user): bool
    {
        return null !== $user && $this->reachesAdministration($user->getRoles());
    }

    /**
     * @param array<string|\BackedEnum> $roles
     *
     * @return string[]
     */
    private function reachable(array $roles): array
    {
        $roles = array_map(static fn ($role): string => $role instanceof \BackedEnum ? (string) $role->value : (string) $role, $roles);

        return $this->roleHierarchy ? $this->roleHierarchy->getReachableRoleNames($roles) : $roles;
    }
}
