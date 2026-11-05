<?php

/*
 * omnibase's access policies (plan "omnibase agnostic", step F5e, slice 1: Base\Auth\Policy\*) where there
 * is no framework: run by tests/BareTest.php through docker/bin/bare --strict, with Composer's autoloader of
 * var/bare/vendor alone. What it returns is compared with policies-without-framework.expected.php; the
 * Symfony voters and SecuritySubscriber of omnibridge/symfony, and omnibridge/laravel's gate and middleware,
 * ask the same policies.
 */

use Base\Auth\Policy\Access;
use Base\Auth\Policy\Admin;
use Base\Auth\Policy\Launch;
use Base\Auth\Policy\Maintenance;
use Base\Auth\Policy\Page;
use Base\Auth\Policy\Permission;
use Base\Auth\Policy\Restriction;
use Base\Auth\Policy\Welcome;

/** An object of the interface whose methods answer what $answers says by name (null, false, 0, '', [] otherwise). */
$stub = static function (string $interface, array $answers) {
    static $n = 0;
    $class = 'PolicyStub'.++$n;
    $methods = '';
    foreach ((new ReflectionClass($interface))->getMethods() as $method) {
        $parameters = implode(', ', array_map(static fn (ReflectionParameter $p) => ($p->hasType() ? $p->getType().' ' : '').($p->isPassedByReference() ? '&' : '').'$'.$p->getName().($p->isDefaultValueAvailable() ? ' = '.var_export($p->getDefaultValue(), true) : ($p->isOptional() ? ' = null' : '')), $method->getParameters()));
        $return = $method->hasReturnType() ? ': '.$method->getReturnType() : '';
        $void = 'void' === (string) $method->getReturnType();
        $static = $method->isStatic() ? 'static ' : '';
        $methods .= sprintf("public %sfunction %s(%s)%s { %s }\n", $static, $method->getName(), $parameters, $return, $void ? '' : 'return '.$class.'::$answers['.var_export($method->getName(), true).'] instanceof Closure ? ('.$class.'::$answers['.var_export($method->getName(), true).'])(...func_get_args()) : ('.$class.'::$answers['.var_export($method->getName(), true).'] ?? null);');
    }
    eval(sprintf('final class %s implements \%s { public static array $answers = []; %s }', $class, $interface, $methods));
    $class::$answers = $answers;

    return new $class();
};

$parameters = static fn (array $values) => $stub(Base\Service\ParameterBagInterface::class, ['get' => static fn (string $key = '') => $values[$key] ?? null]);
$localizer = $stub(Base\Service\LocalizerInterface::class, ['getLocale' => 'fr-FR', 'getLocaleLang' => 'fr', 'getLocaleCountry' => 'FR', 'getDefaultLocale' => 'en-GB', 'getDefaultLocaleLang' => 'en']);

$restricted = [
    'base.access_restriction.admin_access' => true,
    'base.access_restriction.user_access' => false,
    'base.access_restriction.public_access' => false,
    'base.access_restriction.firewalls' => ['main'],
    'base.access_restriction.exceptions' => [['locale' => 'fr-FR', 'path' => '^\\/presse'], ['locale' => 'fr-FR', 'env' => 'prod', 'path' => '^\\/prod']],
];
$firewalls = static fn ($url) => str_starts_with((string) $url, 'https://site.test/api') ? 'api' : 'main';
$access = new Access($parameters($restricted), $localizer, $firewalls, 'dev');

$result = [];
foreach ([Access::ADMIN_ACCESS, Access::USER_ACCESS, Access::ANONYMOUS_ACCESS] as $attribute) {
    $result['access'][$attribute] = $access->decide($attribute, null);
}
foreach (['https://site.test/presse/x', 'https://site.test/prod/x', 'https://site.test/blog'] as $url) {
    $result['exception'][$url] = (new Access($parameters($restricted), $localizer, $firewalls, 'dev'))->decide(Access::EXCEPTION_ACCESS, $url);
}
$result['exception']['api, outside the restricted firewall'] = (new Access($parameters($restricted), $localizer, $firewalls, 'dev'))->exception('https://site.test/api/x');
$result['exception']['no firewall'] = (new Access($parameters($restricted), $localizer, static fn () => null, 'dev'))->exception('https://site.test/blog');

$maintenance = $stub(Base\Service\MaintenanceProviderInterface::class, ['isUnderMaintenance' => true]);
$launcher = $stub(Base\Service\LauncherInterface::class, ['isLaunched' => false]);
$result['maintenance'] = [(new Maintenance($maintenance, $access))->allows('https://site.test/blog'), (new Maintenance($maintenance, new Access($parameters($restricted), $localizer, $firewalls, 'dev')))->allows('https://site.test/presse')];
$result['launch'] = [(new Launch($launcher, $access))->allows('https://site.test/blog')];

$admin = new class {
    public function isGranted(string $role): bool
    {
        return 'ROLE_ADMIN' === $role;
    }
};
$result['admin'] = [(new Admin())->supports('BACKEND', false), (new Admin())->supports('BACKEND', static fn () => true), (new Admin())->decide($admin), (new Admin())->decide(null)];

$complaint = new Base\Auth\Policy\Complaint($stub(Omnibridge\Contract\Roles::class, ['reachable' => static fn (array $roles) => $roles]));
$result['complaint'] = [$complaint->supports('COMPLAINT_READ', null), $complaint->supports('COMPLAINT_HANDLE', new stdClass()), $complaint->supports('BACKEND', null), $complaint->decide('COMPLAINT_READ', null, new stdClass(), static fn () => true)];

$permission = new Permission();
$result['permission'] = [$permission->supports('ARTICLE.PUBLISH'), $permission->supports('ROLE_ADMIN'), $permission->supports('EA_ACCESS'), $permission->supports('article'), $permission->decide('ARTICLE.PUBLISH', null)];

// A restricted site (user access refused): the decision per page, the grants given.
$page = static fn (?string $name, bool $admin = false, bool $security = false, array $redirects = ['security_pendingForApproval']) => new class($name, $admin, $security, $redirects) implements Page {
    public function __construct(private ?string $name, private bool $admin, private bool $security, private array $redirects)
    {
    }

    public function isSecurity(): bool
    {
        return $this->security;
    }

    public function isProfiler(): bool
    {
        return false;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function redirectsOnDeny(): array
    {
        return $this->redirects;
    }
};
$grants = static fn (array $yes) => static fn (string $attribute, mixed $subject = null) => in_array($attribute.(null === $subject ? '' : '@user'), $yes, true);
$decide = static fn (array $yes, $user, Page $page) => (array) (new Restriction($grants($yes)))->decide($user, $page);
$result['restriction'] = [
    'open site' => $decide(['ADMIN_ACCESS', 'USER_ACCESS', 'ANONYMOUS_ACCESS'], null, $page('blog')),
    'visitor sent away' => $decide(['ADMIN_ACCESS'], null, $page('blog')),
    'visitor on the sign-in' => $decide(['ADMIN_ACCESS'], null, $page('security_login', security: true)),
    'visitor in the back office' => $decide(['ADMIN_ACCESS'], null, $page('admin', admin: true)),
    'visitor on an exception' => $decide(['ADMIN_ACCESS', 'EXCEPTION_ACCESS'], null, $page('blog')),
    'visitor on the page the denied go to' => $decide(['ADMIN_ACCESS'], null, $page('security_pendingForApproval')),
    'granted user' => $decide(['ADMIN_ACCESS', 'USER_ACCESS@user'], new stdClass(), $page('blog')),
    'granted super administrator' => $decide(['ADMIN_ACCESS', 'USER_ACCESS@user', 'ROLE_SUPERADMIN'], new stdClass(), $page('blog')),
    'granted user on an exception' => $decide(['ADMIN_ACCESS', 'USER_ACCESS@user', 'EXCEPTION_ACCESS'], new stdClass(), $page('blog')),
    'granted user where the denied go' => $decide(['ADMIN_ACCESS', 'USER_ACCESS@user'], new stdClass(), $page('security_pendingForApproval')),
];

$account = static fn (bool $persistent, bool $verified, ?string $activeAt) => new class($persistent, $verified, $activeAt) {
    public function __construct(private bool $persistent, private bool $verified, private ?string $activeAt)
    {
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function getActiveAt(): ?DateTime
    {
        return null === $this->activeAt ? null : new DateTime($this->activeAt);
    }
};
$result['welcome'] = [
    'social' => Welcome::key($account(false, false, null)),
    'unverified' => Welcome::key($account(true, false, null)),
    'alien' => Welcome::key($account(true, true, 'today 08:00'), false),
    'this morning' => Welcome::key($account(true, true, 'today 08:00')),
    'yesterday afternoon' => Welcome::key($account(true, true, 'yesterday 13:00')),
    'two days ago, late' => Welcome::key($account(true, true, '-2 days 22:00')),
    'today at noon' => Welcome::key($account(true, true, 'today 11:00')),
];

return $result;
