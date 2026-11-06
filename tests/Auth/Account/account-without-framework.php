<?php

/*
 * omnibase's account pages (plan "omnibase agnostic", step F5e, slice 5a: Base\Auth\Account\*, Base\Demo\DemoSignIn,
 * Base\Request\LocaleSwitch) where there is no framework: run by tests/BareTest.php through docker/bin/bare --strict,
 * with Composer's autoloader of var/bare/vendor alone. What it returns is compared with
 * account-without-framework.expected.php; omnibridge/symfony's controllers and omnibridge/laravel's pages ask the
 * same rules. Each case: the Outcome, the notifications sent (message, importance), what was written (flushes).
 */

use Base\Auth\Account\AlreadySignedIn;
use Base\Auth\Account\Closing;
use Base\Auth\Account\Outcome;
use Base\Auth\Account\Passwords;
use Base\Auth\Account\ProfileAccess;
use Base\Auth\Account\RescuePage;
use Base\Auth\Account\SignIn;
use Base\Auth\Account\SignOutPage;
use Base\Auth\Account\Verification;
use Base\Auth\Account\WaitingPages;
use Base\Demo\DemoSignIn;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\Request\LocaleSwitch;
use Base\Service\Model\ClassAliases;
use Base\Service\SecurityPolicy;

ClassAliases::register();

/** An object of the interface whose methods answer what $answers says by name (null otherwise; a closure is called). */
$stub = static function (string $interface, array $answers) {
    static $n = 0;
    $class = 'AccountStub'.++$n;
    $methods = '';
    foreach ((new ReflectionClass($interface))->getMethods() as $method) {
        $parameters = implode(', ', array_map(static fn (ReflectionParameter $p) => ($p->hasType() ? $p->getType().' ' : '').($p->isPassedByReference() ? '&' : '').($p->isVariadic() ? '...' : '').'$'.$p->getName().($p->isDefaultValueAvailable() ? ' = '.var_export($p->getDefaultValue(), true) : ($p->isOptional() && !$p->isVariadic() ? ' = null' : '')), $method->getParameters()));
        $return = $method->hasReturnType() ? ': '.$method->getReturnType() : '';
        $void = \in_array((string) $method->getReturnType(), ['void', 'never'], true);
        $static = $method->isStatic() ? 'static ' : '';
        $name = var_export($method->getName(), true);
        $methods .= sprintf("public %sfunction %s(%s)%s { %s }\n", $static, $method->getName(), $parameters, $return, $void ? 'if ('.$class.'::$answers['.$name.'] ?? null) { ('.$class.'::$answers['.$name.'])(...func_get_args()); }' : 'return '.$class.'::$answers['.$name.'] instanceof Closure ? ('.$class.'::$answers['.$name.'])(...func_get_args()) : ('.$class.'::$answers['.$name.'] ?? null);');
    }
    eval(sprintf('class %s implements \%s { public static array $answers = []; %s }', $class, $interface, $methods));
    $class::$answers = $answers;

    return new $class();
};

// What the notifications ask the host: who receives them is noted here.
$sent = [];
Omnibridge\Runtime::set(Omnibridge\Contract\Locales::class, $stub(Omnibridge\Contract\Locales::class, ['getLocale' => 'fr-FR', 'getDefaultLocale' => 'fr-FR', 'getLocaleLang' => 'fr', 'getDefaultLocaleLang' => 'fr', 'getAvailableLocales' => ['fr-FR']]));
Omnibridge\Runtime::set(Omnibridge\Contract\Environment::class, $stub(Omnibridge\Contract\Environment::class, ['isDebug' => false, 'getName' => 'test', 'secret' => 'bare-secret']));
Omnibridge\Runtime::set(Base\Service\ParameterBagInterface::class, $stub(Base\Service\ParameterBagInterface::class, ['get' => static fn (string $key = '') => ['base.user.active_delay' => 300, 'base.user.online_delay' => 900][$key] ?? null]));
Omnibridge\Runtime::set(Base\Service\TranslatorInterface::class, $stub(Base\Service\TranslatorInterface::class, ['trans' => static fn ($id, array $parameters = []) => $id.($parameters ? '('.implode(',', array_map('strval', $parameters)).')' : '')]));
Omnibridge\Runtime::set(Omnibridge\Contract\Sessions::class, $stub(Omnibridge\Contract\Sessions::class, ['signOut' => true]));
Omnibridge\Runtime::set(Base\Notifier\Abstract\BaseNotifierInterface::class, $stub(Base\Notifier\Abstract\BaseNotifierInterface::class, ['sendUsers' => static function (Notification $notification) use (&$sent) { $sent[] = [$notification->getContent() ?: $notification->getTitle() ?: $notification->getSubject(), $notification->getImportance()]; }]));

$account = static function (array $state = []) {
    $user = new class extends \App\Entity\User {
        public string $name = 'account';

        public function __toString(): string
        {
            return $this->name;
        }
    };
    $user->name = $state['name'] ?? 'account';
    $user->setEmail($user->name.'@site.test');
    $user->setIsVerified($state['verified'] ?? true);
    $user->approve($state['approved'] ?? true);
    if ($state['disabled'] ?? false) {
        $user->disable();
    }
    (fn () => $this->id = $state['id'] ?? 7)->call($user);

    return $user;
};

$flushes = 0;
$unitOfWork = $stub(Omnibridge\Persistence\UnitOfWork::class, ['flush' => static function () use (&$flushes) { ++$flushes; }]);
$tokens = $stub(Omnibridge\Contract\Tokens::class, ['csrf' => static fn (string $id) => 'token-'.$id, 'isValid' => static fn (string $intent, ?string $token) => 'token-'.$intent === $token]);
$access = static fn (?object $user, array $granted = []) => $stub(Omnibridge\Contract\Access::class, ['getUser' => $user, 'isGranted' => static fn ($attribute) => \in_array($attribute, $granted, true)]);
$parameters = static fn (array $values) => $stub(Base\Service\ParameterBagInterface::class, ['get' => static fn (string $key = '') => $values[$key] ?? null]);
$settings = $stub(Base\Service\SettingBagInterface::class, ['get' => static fn (string $path) => ['base.settings.logo' => ['_self' => 'site.png']][$path] ?? []]);
$policy = new SecurityPolicy($settings);
$notifier = $stub(Base\Notifier\NotifierInterface::class, [
    'sendVerificationEmail' => static fn () => new \App\Entity\User\Notification('mail.verifyEmail'),
    'sendUserApprovalRequest' => static fn () => new \App\Entity\User\Notification('mail.adminApproval'),
]);
// The application's mails a notifier answers by name (Base\Notifier\Notifier's, not its interface's).
$notifier = eval('return new class extends '.get_class($notifier).' {
    public function loginToken(): \App\Entity\User\Notification { return new \App\Entity\User\Notification("mail.loginToken"); }
    public function resetPasswordRequest(): \App\Entity\User\Notification { return new \App\Entity\User\Notification("mail.resetPassword"); }
    public function sendVerificationEmail(): \App\Entity\User\Notification { return new \App\Entity\User\Notification("mail.verifyEmail"); }
    public function sendUserApprovalRequest(): \App\Entity\User\Notification { return new \App\Entity\User\Notification("mail.adminApproval"); }
};');

/** A rule's call: its Outcome (or what it returned), the notifications sent, the flushes. */
$run = static function (Closure $call) use (&$sent, &$flushes) {
    $sent = [];
    $flushes = 0;
    $returned = $call();

    return [$returned instanceof Outcome ? $returned->toArray() : (\is_object($returned) ? $returned::class : $returned), $sent, $flushes];
};

$tokenOf = static function (User $user, string $name, int $lifetime = 3600) {
    $token = new \App\Entity\User\Token($name, $lifetime);
    $token->setUser($user);

    return $token;
};
$tokenRepository = static fn (array $tokens) => $stub(Base\Repository\User\Tokens::class, [
    'oneByValueAndName' => static fn (?string $value, string $name) => array_values(array_filter($tokens, static fn (Token $t) => $t->get() === $value && $t->getName() === $name))[0] ?? null,
    'oneByValue' => static fn (?string $value) => array_values(array_filter($tokens, static fn (Token $t) => $t->get() === $value))[0] ?? null,
    'oneSigningInByNameAndValue' => static fn (string $name, ?string $value) => array_values(array_filter($tokens, static fn (Token $t) => $t->get() === $value && $t->getName() === $name))[0] ?? null,
]);
$users = static fn (array $byEmail = [], array $byId = []) => $stub(Base\Repository\Users::class, [
    'oneByEmail' => static fn (?string $email) => $byEmail[$email] ?? null,
    'oneById' => static fn (int $id) => $byId[$id] ?? null,
    'find' => static fn ($id) => $byId[$id] ?? null,
]);

$result = [];

// The sign-in page and its link.
$member = $account();
$signIn = static fn ($access, array $values = ['base.user.login_with_token' => true], array $tokenList = [], array $byEmail = []) => new SignIn($access, $parameters($values), $unitOfWork, $users($byEmail), $tokenRepository($tokenList), $notifier);
$link = $tokenOf($member, SignIn::LINK_TOKEN);
$link->markAsLogginable();
$result['sign-in'] = [
    'a visitor' => $run(static fn () => $signIn($access(null))->page()),
    'in during a maintenance' => $run(static fn () => $signIn($access($member, ['EXCEPTION_ACCESS']))->page()),
    'signed in fully' => $run(static fn () => $signIn($access($member, ['IS_AUTHENTICATED_FULLY']))->page()),
    'remembered only' => $run(static fn () => $signIn($access($member))->page()),
    'links off' => $run(static fn () => $signIn($access(null), [])->link('x')),
    'a valid link' => $run(static fn () => $signIn($access(null), tokenList: [$link])->link($link->get())),
    'a link unknown' => $run(static fn () => $signIn($access(null), tokenList: [$link])->link('nope')),
    'by omniauth' => $run(static fn () => $signIn($access(null), tokenList: [$link])->link($link->get(), true)),
    'a link asked, an account' => $run(static fn () => $signIn($access(null), byEmail: ['a@b.c' => $account()])->linkRequested('a@b.c')),
    'a link asked, no account' => $run(static fn () => $signIn($access(null))->linkRequested('x@y.z')),
    'a link asked, by omniauth' => $run(static fn () => $signIn($access(null), byEmail: ['a@b.c' => $account()])->linkRequested('a@b.c', true)),
];

// The sign-out page.
$kicked = $account(['id' => 3]);
$kicked->kick();
$signOut = new SignOutPage($tokens, $users([], [3 => $kicked, 4 => $account(['id' => 4])]), $unitOfWork);
$result['sign-out'] = [
    'confirms' => [$signOut->confirms(true, 'logout', null), $signOut->confirms(true, 'logout', 'token-logout'), $signOut->confirms(false, 'logout', null), (new SignOutPage(null))->confirms(true, 'logout', null)],
    'left, put out' => $run(static fn () => $signOut->left(3)),
    'left, kick forgotten' => $kicked->isKicked(),
    'left' => $run(static fn () => $signOut->left(4)),
    'left, nobody' => $run(static fn () => $signOut->left(null)),
    'after' => [$signOut->after(false)->toArray(), $signOut->after(true)->toArray()],
];

// The pages for visitors only.
$result['already signed in'] = [
    'a visitor' => $run(static fn () => (new AlreadySignedIn($access(null)))->check()),
    'a member' => $run(static fn () => (new AlreadySignedIn($access($member)))->check()),
];

// The e-mail's verification and the approval.
$unverified = $account(['verified' => false, 'approved' => false]);
$verifyToken = $tokenOf($unverified, Verification::TOKEN);
$verification = static fn (?object $user, array $tokenList = []) => new Verification($access($user), $unitOfWork, $tokenRepository($tokenList), $notifier);
$result['verification'] = [
    'asked, a visitor' => $run(static fn () => $verification(null)->request()),
    'asked, verified' => $run(static fn () => $verification($member)->request()),
    'asked' => $run(static fn () => $verification($account(['verified' => false]))->request()),
    'the link\'s owner' => $verification(null, [$verifyToken])->tokenOwner($verifyToken->get())?->getUser() === $unverified,
    'confirmed, nobody signed in' => $run(static fn () => $verification(null)->confirm(null)),
    'confirmed, no token' => $run(static fn () => $verification($account(['verified' => false]))->confirm(null)),
    'confirmed' => $run(static fn () => $verification($unverified)->confirm($verifyToken)),
    'confirmed: verified' => $unverified->isVerified(),
    'confirmed again' => $run(static fn () => $verification($unverified)->confirm($verifyToken)),
    'approval, not verified' => $run(static fn () => $verification($account(['verified' => false, 'approved' => false]))->approval()),
    'approval, approved' => $run(static fn () => $verification($member)->approval()),
];

// The account closed, opened again.
$closing = static fn (?object $user, array $tokenList = []) => new Closing($access($user), $unitOfWork, $tokenRepository($tokenList), $policy);
$closed = $account(['disabled' => true]);
$welcome = $tokenOf($closed, Closing::TOKEN);
$result['closing'] = [
    'a visitor' => $run(static fn () => $closing(null)->close()),
    'closed already' => $run(static fn () => $closing($account(['disabled' => true]))->close()),
    'closed' => $run(static fn () => $closing($open = $account())->close()),
    'welcome back' => $run(static fn () => $closing(null, [$welcome])->welcomeBack($welcome->get())),
    'welcome back: enabled' => !$closed->isDisabled(),
    'welcome back, open already' => $run(static fn () => $closing(null, [$welcome])->welcomeBack($welcome->get())),
    'welcome back, no token' => $run(static fn () => $closing(null)->welcomeBack('nope')),
];

// The passwords.
$forgetful = $account();
$reset = $tokenOf($forgetful, Passwords::RESET_TOKEN);
$change = $tokenOf($forgetful, Passwords::CHANGE_TOKEN);
$passwords = static fn (?object $user, array $tokenList = [], array $byEmail = []) => new Passwords($access($user), $unitOfWork, $users($byEmail), $tokenRepository($tokenList), $policy, $notifier);
$result['passwords'] = [
    'reset asked, an account' => $run(static fn () => $passwords(null, byEmail: ['f@b.c' => $account()])->resetRequested('f@b.c')),
    'reset asked, no account' => $run(static fn () => $passwords(null)->resetRequested('x@y.z')),
    'reset link, unknown' => $run(static fn () => $passwords(null)->resetToken('nope')),
    'reset link, another kind' => $run(static fn () => $passwords(null, [$change])->resetToken($change->get())),
    'reset link' => $run(static fn () => $passwords(null, [$reset])->resetToken($reset->get())),
    'reset' => $run(static fn () => $passwords(null)->reset($reset, 'new password')),
    'reset: used up' => $reset->isValid(),
    'change, a visitor' => $run(static fn () => $passwords(null)->changeToken()),
    'change, no token' => $run(static fn () => $passwords($account())->changeToken()),
    'change' => $run(static fn () => $passwords($forgetful)->changeToken()),
    'changed' => $run(static fn () => $passwords($forgetful)->change($change, 'chosen')),
];

// The waiting pages.
$waiting = static fn (?object $user) => new WaitingPages($access($user));
$result['waiting'] = [
    'maintenance' => $waiting(null)->maintenance()->toArray(),
    'launch' => $waiting(null)->launch()->toArray(),
    'pending' => $waiting(null)->pending()->toArray(),
    'waiting for approval' => [$waiting($account(['approved' => false]))->pendingForApproval()->toArray(), $waiting($member)->pendingForApproval()->toArray()],
];

// Whose profile.
$other = $account(['id' => 9, 'name' => 'other']);
$profiles = static fn (?object $user, array $granted = []) => new ProfileAccess($access($user, $granted), $users([], [9 => $other, 7 => $member]));
$show = static fn ($r) => $r instanceof Outcome ? $r->toArray() : (string) $r;
$result['profile'] = [
    'shown, by id' => $show($profiles(null)->show(9)),
    'shown, no such account' => $show($profiles(null)->show(5)),
    'shown, a visitor' => $show($profiles(null)->show()),
    'shown, one\'s own' => $show($profiles($member)->show()),
    'edited, another\'s' => $show($profiles($member)->edit(9)),
    'edited, another\'s by an administrator' => $show($profiles($member, ['ROLE_ADMIN'])->edit(9)),
    'edited, one\'s own' => $show($profiles($member)->edit(7)),
];

// The back office's sign-in page.
$referrer = static fn (?string $url, bool $same = true) => $stub(Base\Service\ReferrerInterface::class, ['getUrl' => $url, 'sameSite' => $same]);
$result['rescue'] = [
    'signed in' => (new RescuePage($access($member, ['IS_AUTHENTICATED_FULLY']), $referrer('https://site.test/admin/avatars')))->signedIn('/admin')?->toArray(),
    'signed in, no referrer' => (new RescuePage($access($member, ['IS_AUTHENTICATED_FULLY']), $referrer(null)))->signedIn('/admin')?->toArray(),
    'a visitor' => (new RescuePage($access(null), $referrer(null)))->signedIn('/admin'),
    'logo' => (new RescuePage($access(null), $referrer(null), $settings))->logo(),
    'targets' => [
        (new RescuePage($access(null), $referrer('https://site.test/admin/avatars?page=2')))->target('/admin'),
        (new RescuePage($access(null), $referrer('https://elsewhere.test/x', false)))->target('/admin'),
        (new RescuePage($access(null), $referrer('https://site.test/')))->target('/admin'),
        (new RescuePage($access(null), $referrer(null)))->target('/'),
    ],
];

// The demonstration's sign-in in one click.
$registry = new Base\Demo\DemoAccountRegistry([new class implements Base\Demo\DemoAccountProviderInterface {
    public function getDemoAccounts(): iterable
    {
        return [new Base\Demo\DemoAccount('reader@demo.test', 'Reader'), new Base\Demo\DemoAccount('editor@demo.test', 'Editor', roles: ['ROLE_ADMIN'])];
    }
}]);
$load = static fn (string $identifier) => ['reader@demo.test' => $account(['name' => 'reader']), 'editor@demo.test' => $account(['name' => 'editor'])][$identifier] ?? null;
$demo = static fn (bool $on) => new DemoSignIn(new Base\Demo\DemoMode($on ? 'demo' : 'test'), $registry, $tokens);
$result['demo'] = [
    'off' => $run(static fn () => $demo(false)->decide('token-demo_login', 'reader@demo.test', $load)),
    'no token' => $run(static fn () => $demo(true)->decide(null, 'reader@demo.test', $load)),
    'undeclared' => $run(static fn () => $demo(true)->decide('token-demo_login', 'nobody', $load)),
    'the back office' => $run(static fn () => $demo(true)->decide('token-demo_login', 'editor@demo.test', $load)),
    'a reader' => $run(static fn () => $demo(true)->decide('token-demo_login', 'reader@demo.test', $load)),
];

// The language menu.
eval('final class BareLocalizer extends \Base\Service\Localizer { public function __construct() {} public static function getAvailableLocaleLangs(): array { return ["fr", "en"]; } public static function getAvailableLocales(): array { return ["fr-FR", "en-GB"]; } public function getLocale(?string $locale = null): string { return ["fr" => "fr-FR", "en" => "en-GB"][$locale] ?? (string) $locale; } public function getLocaleLang(?string $locale = null): string { return substr((string) $locale, 0, 2); } public function getLocaleLangName(?string $locale = null): ?string { return ["fr" => "Français", "en" => "English"][substr((string) $locale, 0, 2)] ?? null; } }');
$translator = $stub(Base\Service\TranslatorInterface::class, ['trans' => static fn ($id, array $parameters = []) => $id.'('.implode(',', $parameters).')']);
$switch = static fn (?object $user, array $granted = []) => new LocaleSwitch(new BareLocalizer(), $translator, $access($user, $granted), $unitOfWork);
$speaker = $account();
$result['locale'] = [
    'chosen' => [$switch(null)->choose('en'), $switch(null)->choose('en-GB'), $switch(null)->choose('ja'), $switch(null)->choose(null)],
    'remembered, a member' => $run(static fn () => $switch($speaker)->remember('en', 'en-GB')),
    'remembered: the member\'s' => $speaker->getLocale(),
    'remembered, impersonating' => $run(static fn () => $switch($account(), ['IS_IMPERSONATOR'])->remember('en', 'en-GB')),
    'referred' => [LocaleSwitch::referred('https://site.test/japon/articles', 'site.test'), LocaleSwitch::referred('/blog', 'site.test'), LocaleSwitch::referred('https://elsewhere.test/x', 'site.test'), LocaleSwitch::referred(null, 'site.test'), LocaleSwitch::referred('https://site.test', 'site.test')],
    'back' => [
        $switch(null)->back(['_route' => 'app_articles.fr', '_canonical_route' => 'app_articles', 'destination' => 'japon', '_locale' => 'fr'], 'en'),
        $switch(null)->back(['_route' => 'blog.fr'], 'en'),
        $switch(null)->back(['_route' => '_switch_locale'], 'en'),
        $switch(null)->back(null, 'en'),
    ],
];

return $result;
