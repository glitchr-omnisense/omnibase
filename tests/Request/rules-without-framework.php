<?php

/*
 * omnibase's request rules (plan "omnibase agnostic", step F5e, slice 2: Base\Request\Rule\*) where there is no
 * framework: run by tests/BareTest.php through docker/bin/bare --strict, with Composer's autoloader of
 * var/bare/vendor alone. What it returns is compared with rules-without-framework.expected.php; omnibridge/
 * symfony's subscribers and omnibridge/laravel's middleware ask the same rules.
 */

use Base\Entity\Layout\Redirection as Row;
use Base\Entity\User;
use Base\Request\Decision;
use Base\Request\Page;
use Base\Request\Rule\AccessControl;
use Base\Request\Rule\Accounts;
use Base\Request\Rule\Analytics;
use Base\Request\Rule\Approval;
use Base\Request\Rule\EntryPoint;
use Base\Request\Rule\Integrity;
use Base\Request\Rule\Kicked;
use Base\Request\Rule\LaunchPage;
use Base\Request\Rule\Locale;
use Base\Request\Rule\MaintenancePage;
use Base\Request\Rule\Presence;
use Base\Request\Rule\Redirection;
use Base\Request\Rule\Verification;
use Base\Service\Model\ClassAliases;

// The App\ convention: the core's entities name App\ classes, the core's own where an application has none.
ClassAliases::register();

/** An object of the interface whose methods answer what $answers says by name (null otherwise; a void one calls its closure). */
$stub = static function (string $interface, array $answers) {
    static $n = 0;
    $class = 'RuleStub'.++$n;
    $methods = '';
    foreach ((new ReflectionClass($interface))->getMethods() as $method) {
        $parameters = implode(', ', array_map(static fn (ReflectionParameter $p) => ($p->hasType() ? $p->getType().' ' : '').($p->isPassedByReference() ? '&' : '').($p->isVariadic() ? '...' : '').'$'.$p->getName().($p->isDefaultValueAvailable() ? ' = '.var_export($p->getDefaultValue(), true) : ($p->isOptional() && !$p->isVariadic() ? ' = null' : '')), $method->getParameters()));
        $return = $method->hasReturnType() ? ': '.$method->getReturnType() : '';
        $void = \in_array((string) $method->getReturnType(), ['void', 'never'], true);
        $static = $method->isStatic() ? 'static ' : '';
        $name = var_export($method->getName(), true);
        $methods .= sprintf("public %sfunction %s(%s)%s { %s }\n", $static, $method->getName(), $parameters, $return, $void ? 'if ('.$class.'::$answers['.$name.'] ?? null) { ('.$class.'::$answers['.$name.'])(...func_get_args()); }' : 'return '.$class.'::$answers['.$name.'] instanceof Closure ? ('.$class.'::$answers['.$name.'])(...func_get_args()) : ('.$class.'::$answers['.$name.'] ?? null);');
    }
    eval(sprintf('final class %s implements \%s { public static array $answers = []; %s }', $class, $interface, $methods));
    $class::$answers = $answers;

    return new $class();
};

$page = static fn (?string $name, array $is = [], string $uri = 'https://site.test/blog') => new class($name, $is, $uri) implements Page {
    public function __construct(private ?string $name, private array $is, private string $uri)
    {
    }

    public function isSecurity(): bool
    {
        return \in_array('security', $this->is, true);
    }

    public function isProfiler(): bool
    {
        return \in_array('profiler', $this->is, true);
    }

    public function isAdmin(): bool
    {
        return \in_array('admin', $this->is, true);
    }

    public function isSecured(): bool
    {
        return str_starts_with((string) $this->name, 'security_');
    }

    public function isUx(): bool
    {
        return str_starts_with((string) $this->name, 'ux_');
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function redirectsOnDeny(): array
    {
        return [];
    }

    public function uri(): string
    {
        return $this->uri;
    }
};

$account = static function (array $state = []) {
    $user = new class extends User {
        public array $state = [];

        public function isTester()
        {
            return $this->state['tester'] ?? false;
        }

        public function __toString(): string
        {
            return 'account';
        }
    };
    $user->state = $state;
    $user->setIsVerified($state['verified'] ?? true);
    $user->approve($state['approved'] ?? true);
    if ($state['kicked'] ?? false) {
        $user->kick();
    }
    if ($state['active'] ?? false) {
        $user->poke(new DateTime('now'));
    }

    return $user;
};

// What an account asks the host (its language, the environment): a plain PHP host's answers.
Omnibridge\Runtime::set(Omnibridge\Contract\Locales::class, $stub(Omnibridge\Contract\Locales::class, ['getLocale' => 'fr-FR', 'getDefaultLocale' => 'fr-FR', 'getLocaleLang' => 'fr', 'getDefaultLocaleLang' => 'fr', 'getAvailableLocales' => ['fr-FR']]));
Omnibridge\Runtime::set(Omnibridge\Contract\Environment::class, $stub(Omnibridge\Contract\Environment::class, ['isDebug' => false, 'getName' => 'test']));
Omnibridge\Runtime::set(Base\Service\ParameterBagInterface::class, $stub(Base\Service\ParameterBagInterface::class, ['get' => static fn (string $key = '') => ['base.user.active_delay' => 300, 'base.user.online_delay' => 900][$key] ?? null]));

$flushes = 0;
$unitOfWork = $stub(Omnibridge\Persistence\UnitOfWork::class, ['flush' => static function () use (&$flushes) { ++$flushes; }]);
$urls = $stub(Omnibridge\Contract\Urls::class, ['path' => static fn (string $name, array $parameters = []) => '/'.$name.($parameters ? '?'.http_build_query($parameters) : '')]);
$tokens = $stub(Omnibridge\Contract\Tokens::class, ['csrf' => static fn (string $id) => 'token-'.$id]);
$granted = static fn (array $yes) => static fn (string $attribute): bool => \in_array($attribute, $yes, true);
$accounts = static fn (bool $autoapprove = false) => new Accounts(new Kicked($tokens), new Presence($unitOfWork), new Verification($urls), new Approval($unitOfWork, $autoapprove));
$no = static fn (): bool => false;
$yes = static fn (): bool => true;

$result = [];

// The account rules, in SecuritySubscriber's order.
$result['accounts']['anonymous'] = $accounts()->request(null, $page('blog'), $no)->toArray();
$result['accounts']['an active account'] = $accounts()->request($account(['active' => true]), $page('blog'), $no)->toArray();
$result['accounts']['impersonated'] = $accounts()->request($account(['active' => true]), $page('blog'), $no, true, true, '_impersonate')->toArray();
$result['accounts']['kicked'] = $accounts()->request($account(['kicked' => true]), $page('blog', [], 'https://site.test/blog/a'), $no)->toArray();
$flushes = 0;
$idle = $account();
$result['accounts']['idle: poked and written'] = [$accounts()->request($idle, $page('blog'), $no)->toArray(), $idle->isActive(), $flushes];
$flushes = 0;
$result['accounts']['idle, a sub-request: not poked'] = [$accounts()->request($idle = $account(), $page('blog'), $no, false)->toArray(), $idle->isActive(), $flushes];
$result['accounts']['not verified'] = $accounts()->request($account(['verified' => false, 'active' => true]), $page('blog'), $no)->toArray();
$result['accounts']['not verified, a tester'] = $accounts()->request($account(['verified' => false, 'tester' => true, 'active' => true]), $page('blog'), $no)->toArray();
$result['accounts']['not verified, the account\'s pages'] = $accounts()->request($account(['verified' => false, 'active' => true]), $page('security_login'), $no)->toArray();
foreach (['an administrator' => [$yes, false], 'autoapprove' => [$no, true], 'neither' => [$no, false]] as $who => [$administrator, $auto]) {
    $flushes = 0;
    $pending = $account(['approved' => false, 'active' => true]);
    $result['accounts']['not approved, '.$who] = [$accounts($auto)->request($pending, $page('blog'), $administrator)->toArray(), $pending->isApproved(), $flushes];
}
$flushes = 0;
$result['presence']['on the answer'] = [(new Presence($unitOfWork))->poke($account()), (new Presence($unitOfWork))->poke($account(['active' => true])), (new Presence($unitOfWork))->poke(new stdClass()), $flushes];

// The page's language.
$locale = new Locale(['fr', 'en', 'de'], static fn (string $l) => ['fr' => 'fr-FR', 'en' => 'en-GB', 'de' => 'de-DE'][substr($l, 0, 2)] ?? $l);
$result['locale'] = [
    'address' => $locale->decide('de', 'en-GB', 'fr', false, 'en', 'fr-FR'),
    'member before LANG' => $locale->decide(null, 'en-GB', 'fr', false, 'en', 'en-GB'),
    'impersonating: LANG' => $locale->decide(null, 'en_GB', 'fr', true, null, 'fr-FR'),
    'LANG' => $locale->decide(null, 'en', null, false, 'fr-FR', 'fr-FR'),
    'a language not spoken' => $locale->decide(null, 'ja', 'it', false, null, 'fr-FR'),
    'the browser' => $locale->decide(null, null, null, false, $locale->browser('en-US,en;q=0.9', static fn (array $languages) => 'en'), 'fr-FR'),
    'no word from the browser' => [$locale->browser(null, static fn () => 'en'), $locale->browser(Locale::REQUEST_DEFAULT_LANGUAGE, static fn () => 'en')],
    'remembered' => [Locale::remember('fr-FR', 'en-GB', false)->toArray(), Locale::remember('fr-FR', 'fr-FR', false)->toArray(), Locale::remember('fr-FR', 'en-GB', true)->toArray()],
    'time zone' => [Locale::timezone(null)[0], Locale::timezone('Europe/Paris')[0], [Locale::timezone('Mars/Olympus')[0], Locale::timezone('Mars/Olympus')[1]->toArray()]],
];

// The session's integrity.
$integrity = new Integrity(null);
$result['integrity'] = [
    'intact' => $integrity->decide(true, null, 'blog')->toArray(),
    'broken' => $integrity->decide(false, $account(), 'blog')->toArray(),
    'broken, the rescue page' => $integrity->decide(false, $account(), 'security_rescue')->toArray(),
    'no secret' => $integrity->secretIntact(null),
    'checksum' => [Integrity::checksum(['driver' => 'pdo_mysql', 'user' => 'u', 'host' => 'db', 'port' => 3306, 'dbname' => 'site', 'charset' => 'utf8mb4']), Integrity::checksum([])],
    'database' => [Integrity::databaseIntact(false, null, 'x'), Integrity::databaseIntact(true, 'x', 'x'), Integrity::databaseIntact(true, 'x', 'y'), Integrity::databaseIntact(true, null, 'x')],
];

// The back office's figures.
$online = [$account(['active' => true]), $account()];
$users = $stub(Base\Repository\Users::class, ['activeWithin' => static fn (int $seconds, ?int $except = null) => $online]);
$translator = $stub(Base\Service\TranslatorInterface::class, ['trans' => static fn ($id, array $parameters = []) => $id.'('.implode(',', $parameters).')']);
$analytics = new Analytics($users, $translator);
$figures = $analytics->users(null, 300, ['user_analytics' => ['kept' => 1]]);
$result['analytics'] = [
    'applies' => [Analytics::applies($page('admin_x', ['admin']), true), Analytics::applies($page('admin_x', ['admin']), false), Analytics::applies($page('blog'), true), Analytics::applies($page('_profiler', ['admin', 'profiler']), true)],
    'users' => [$figures['user_analytics'], array_map('count', $figures['user_manager'])],
    'google' => $analytics->google(['users' => 3, 'views' => 0, 'sessions' => 2], ['user_analytics' => ['label' => 'x']]),
];

// The redirections.
$rows = ['/old' => new Row('/old', '/new'), '/self' => new Row('/self', '/self'), '/away' => new Row('/away', 'https://elsewhere.test/', 302)];
$hits = 0;
$redirections = $stub(Base\Repository\Layout\Redirections::class, ['forAddress' => static fn (string $address) => $rows[strtok($address, '?')] ?? null, 'hit' => static function () use (&$hits) { ++$hits; }]);
$redirection = new Redirection($redirections);
$result['redirection'] = [
    'found' => $redirection->decide(true, '/old', 'utm_source=x', '/app')->toArray(),
    'posted' => $redirection->decide(false, '/old')->toArray(),
    'onto itself' => $redirection->decide(true, '/self')->toArray(),
    'elsewhere' => $redirection->decide(true, '/away')->toArray(),
    'none' => $redirection->decide(true, '/nothing')->toArray(),
    'no table' => (new Redirection($stub(Base\Repository\Layout\Redirections::class, ['forAddress' => static fn () => throw new RuntimeException('no table')])))->decide(true, '/old')->toArray(),
    'hits' => $hits,
];

// The maintenance and launch pages.
$maintenance = static fn (bool $under) => $stub(Base\Service\MaintenanceProviderInterface::class, ['isUnderMaintenance' => $under]);
$result['maintenance'] = [
    'over, elsewhere' => (new MaintenancePage($maintenance(false), null, 'app_index'))->decide($page('blog'), $granted([]))->toArray(),
    'over, its page' => (new MaintenancePage($maintenance(false), null, 'app_index'))->decide($page('security_maintenance'), $granted([]))->toArray(),
    'an editor' => (new MaintenancePage($maintenance(true)))->decide($page('blog'), $granted(['ROLE_EDITOR']))->toArray(),
    'its page' => (new MaintenancePage($maintenance(true)))->decide($page('security_maintenance'), $granted([]))->toArray(),
    'granted' => (new MaintenancePage($maintenance(true)))->decide($page('blog'), $granted(['MAINTENANCE_ACCESS']))->toArray(),
    'denied' => (new MaintenancePage($maintenance(true), 'app_closed'))->decide($page('blog'), $granted([]))->toArray(),
];
$launcher = static fn (?string $date, bool $launched) => $stub(Base\Service\LauncherInterface::class, ['getLaunchdate' => null === $date ? null : new DateTime($date), 'isLaunched' => $launched]);
$result['launch'] = [
    'not asked' => (new LaunchPage($launcher('2030-01-01 10:00', false), false))->decide($page('blog'), $granted([]))->toArray(),
    'no date' => (new LaunchPage($launcher(null, false), true))->decide($page('blog'), $granted([]))->toArray(),
    'the back office' => (new LaunchPage($launcher('2030-01-01 10:00', false), true))->decide($page('admin_x', ['admin']), $granted([]))->toArray(),
    'launched' => (new LaunchPage($launcher('2020-01-01 10:00', true), true, 'app_index'))->decide($page('blog'), $granted([]))->toArray(),
    'an editor' => (new LaunchPage($launcher('2030-01-01 10:00', false), true))->decide($page('blog'), $granted(['ROLE_EDITOR']), 'fr')->toArray(),
    'granted' => (new LaunchPage($launcher('2030-01-01 10:00', false), true))->decide($page('blog'), $granted(['LAUNCH_ACCESS']))->toArray(),
    'denied' => (new LaunchPage($launcher('2030-01-01 10:00', false), true))->decide($page('blog'), $granted([]))->toArray(),
];

// The pages kept for some accounts, and the entry point.
$control = new AccessControl([['path' => '^/rescue', 'roles' => 'PUBLIC_ACCESS'], ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'], ['path' => '^/profile', 'roles' => ['ROLE_USER']]]);
$result['access control'] = [
    'a page for nobody in particular' => $control->decide('/blog', false, $granted([]))->toArray(),
    'public' => $control->decide('/rescue', false, $granted([]))->toArray(),
    'a visitor on a member\'s page' => $control->decide('/profile/nobody/edit', false, $granted([]), 'https://site.test/profile/nobody/edit')->toArray(),
    'a member' => $control->decide('/profile/1/edit', true, $granted(['ROLE_USER']))->toArray(),
    'a member in the back office' => $control->decide('/admin', true, $granted(['ROLE_USER']))->toArray(),
    'the entry point' => [EntryPoint::decide(false)->toArray(), EntryPoint::decide(true)->toArray()],
];
$result['decision'] = Decision::continue()->withNotice('a')->then(Decision::redirect('b')->withNotice('c', [], 'warning')->withCookie('X', '1'))->toArray();

return $result;
