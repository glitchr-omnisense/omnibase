<?php

/*
 * omnibase's other pages (plan "omnibase agnostic", step F5e, slice 5b: Base\Auth\Account\{SecondFactor, Devices,
 * MemberSearch}, Base\Thread\{ThreadSearch, ThreadActions}, Base\Notifier\{NotificationCenter, ContactMessage,
 * TestRecipient}, Base\Request\{TimezoneSwitch, ErrorPage, ShortLinkRedirect, AttachmentDownload, SitemapPage},
 * Base\Screen\Welcome, Base\Field\Validation\Autovalidate, Base\Field\Search\ExchangeRate) where there is no
 * framework: run by tests/BareTest.php through docker/bin/bare --strict, with Composer's autoloader of var/bare/vendor
 * alone. What it returns is compared with pages-without-framework.expected.php; omnibridge/symfony's controllers ask
 * the same rules. Each case: what the rule answered (an Outcome, JSON, a value), the notifications sent, the flushes.
 */

use Base\Auth\Account\Devices;
use Base\Auth\Account\MemberSearch;
use Base\Auth\Account\Outcome;
use Base\Auth\Account\SecondFactor;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Field\Search\ExchangeRate;
use Base\Field\Validation\Autovalidate;
use Base\Notifier\ContactMessage;
use Base\Notifier\NotificationCenter;
use Base\Notifier\TestRecipient;
use Base\Request\AttachmentDownload;
use Base\Request\ErrorPage;
use Base\Request\ShortLinkRedirect;
use Base\Request\SitemapPage;
use Base\Request\TimezoneSwitch;
use Base\Screen\Json;
use Base\Screen\Template;
use Base\Screen\Welcome;
use Base\Service\Model\ClassAliases;
use Base\Service\SecurityPolicy;
use Base\Thread\ThreadActions;
use Base\Thread\ThreadSearch;

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

$sent = [];
Omnibridge\Runtime::set(Omnibridge\Contract\Locales::class, $stub(Omnibridge\Contract\Locales::class, ['getLocale' => 'fr-FR', 'getDefaultLocale' => 'fr-FR', 'getLocaleLang' => 'fr', 'getDefaultLocaleLang' => 'fr', 'getAvailableLocales' => ['fr-FR']]));
Omnibridge\Runtime::set(Omnibridge\Contract\Environment::class, $stub(Omnibridge\Contract\Environment::class, ['isDebug' => false, 'name' => 'test', 'secret' => 'bare-secret']));
Omnibridge\Runtime::set(Base\Service\ParameterBagInterface::class, $stub(Base\Service\ParameterBagInterface::class, ['get' => null]));
Omnibridge\Runtime::set(Base\Service\TranslatorInterface::class, $stub(Base\Service\TranslatorInterface::class, ['trans' => static fn ($id, array $parameters = []) => $id]));
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
    (fn () => $this->id = $state['id'] ?? 7)->call($user);

    return $user;
};

$flushes = 0;
$unitOfWork = $stub(Omnibridge\Persistence\UnitOfWork::class, ['flush' => static function () use (&$flushes) { ++$flushes; }]);
$tokens = $stub(Omnibridge\Contract\Tokens::class, ['csrf' => static fn (string $id) => 'token-'.$id, 'isValid' => static fn (string $intent, ?string $token) => 'token-'.$intent === $token]);
$access = static fn (?object $user, array $granted = []) => $stub(Omnibridge\Contract\Access::class, ['getUser' => $user, 'isGranted' => static fn ($attribute) => \in_array($attribute, $granted, true)]);
$session = [];
$flashes = [];
$requests = $stub(Omnibridge\Contract\Requests::class, [
    'has' => true,
    'session' => static function (string $key, $default = null) use (&$session) { return $session[$key] ?? $default; },
    'remember' => static function (string $key, $value) use (&$session): void { $session[$key] = $value; },
    'forget' => static function (string $key) use (&$session): void { unset($session[$key]); },
    'flash' => static function (string $type, string $message) use (&$flashes): void { $flashes[] = [$type, $message]; },
]);
$translator = $stub(Base\Service\TranslatorInterface::class, ['trans' => static fn ($id, array $parameters = []) => 'trans:'.$id]);
$settings = static fn (array $values) => $stub(Base\Service\SettingBagInterface::class, ['get' => static fn (string $path) => $values[$path] ?? [], 'getScalar' => static fn (string $path) => $values[$path] ?? null]);

/** A rule's call: what it answered (an Outcome or JSON as arrays), the notifications sent, the flushes, the flashes. */
$run = static function (Closure $call) use (&$sent, &$flushes, &$flashes) {
    $sent = [];
    $flushes = 0;
    $flashes = [];
    $returned = $call();
    $shown = match (true) {
        $returned instanceof Outcome => $returned->toArray(),
        $returned instanceof Json => ['json' => $returned->data, 'status' => $returned->status],
        $returned instanceof Template => ['template' => $returned->template, 'data' => array_keys($returned->data)],
        \is_object($returned) => $returned::class,
        default => $returned,
    };

    return ['answer' => $shown, 'sent' => $sent, 'flushes' => $flushes, 'flashes' => $flashes];
};

$result = [];
// The authenticator application (SecondFactor).
$registry = new Base\Demo\DemoAccountRegistry([new class implements Base\Demo\DemoAccountProviderInterface {
    public function getDemoAccounts(): iterable
    {
        return [new Base\Demo\DemoAccount('demo@site.test', 'Demo')];
    }
}]);
$policy = static fn (array $values = [], bool $demo = false) => new SecurityPolicy($settings($values), demoMode: new Base\Demo\DemoMode($demo ? 'demo' : 'test'), demoAccounts: $registry);
// Untyped where the interface types Security's user: in bare, omnibase's account is no PasswordAuthenticatedUserInterface.
$hasher = new class implements Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface {
    public function hashPassword($user, string $plainPassword): string { return 'hash'; }
    public function isPasswordValid($user, string $plainPassword): bool { return 'right' === $plainPassword; }
    public function needsRehash($user): bool { return false; }
};
$factor = static fn (?object $user, array $values = [], bool $demo = false) => new SecondFactor($access($user), $policy($values, $demo), $unitOfWork, $requests, $tokens, $translator, $hasher);
$member = $account();
$enabled = $account(['name' => 'enabled']);
$enabled->setTotpSecret('SECRET');
$result['second factor'] = [
    'a visitor' => $run(static fn () => $factor(null)->enrolment()),
    'a demonstration account' => $run(static fn () => $factor($account(['name' => 'demo']), [], true)->enrolment()),
    'switched off' => $run(static fn () => $factor($member, [SecurityPolicy::TWO_FACTOR => '0'])->enrolment()),
    'already on' => $run(static fn () => $factor($enabled)->enrolment()),
    'the form' => $run(static fn () => $factor($member)->enrolment()),
    'the pending secret, made' => $factor($member)->pending(static fn () => 'NEW'),
    'the pending secret, kept' => $factor($member)->pending(static fn () => 'OTHER'),
    'carrying' => [SecondFactor::carrying($member, 'NEW')->getTotpSecret(), $member->getTotpSecret()],
    'waiting' => $factor($member)->waiting()?->getTotpSecret(),
    'a wrong code' => $run(static fn () => $factor($member)->confirm($member, 'NEW', false)),
    'the form says' => $factor($member)->invalidCode(),
    'a right code' => $run(static fn () => $factor($member)->confirm($member, 'NEW', true)),
    'turned on' => [$member->getTotpSecret(), $member->getBackupCodesCount(), $session[SecondFactor::PENDING] ?? null],
    'nothing waits' => $factor($member)->waiting(),
    'its QR' => $factor($member)->enabled()?->getTotpSecret(),
    'no QR' => $factor($account(['name' => 'plain']))->enabled(),
    'off: a demonstration account' => $run(static fn () => $factor($account(['name' => 'demo']), [], true)->disable('token-2fa_disable', 'right')),
    'off: no token' => $run(static fn () => $factor($member)->disable(null, 'right')),
    'off: required' => $run(static fn () => $factor($member, [SecurityPolicy::TWO_FACTOR_MANDATORY => '1'])->disable('token-2fa_disable', 'right')),
    'off: a wrong password' => $run(static fn () => $factor($member)->disable('token-2fa_disable', 'wrong')),
    'off' => $run(static fn () => $factor($member)->disable('token-2fa_disable', 'right')),
    'turned off' => [$member->getTotpSecret(), $member->getBackupCodesCount()],
    'backup codes' => array_map(static fn (string $code) => (bool) preg_match('~^[0-9A-F]{6}-[0-9A-F]{6}$~', $code), SecondFactor::backupCodes(3)),
];

// The sessions and passkeys (Devices).
$record = static function (string $class, int $id, ?object $owner) {
    $record = new $class();
    (fn () => $this->id = $id)->call($record);
    $record->setUser($owner);

    return $record;
};
$other = $account(['name' => 'other', 'id' => 8]);
$passkey = $record(Base\Entity\User\Passkey::class, 1, $member);
$theirs = $record(Base\Entity\User\Passkey::class, 2, $other);
$removed = [];
$passkeys = $stub(Base\Repository\User\Passkeys::class, ['find' => static fn ($id) => [1 => $passkey, 2 => $theirs][$id] ?? null, 'flush' => static function () use (&$flushes) { ++$flushes; }, 'remove' => static function ($entity, bool $flush = false) use (&$removed) { $removed[] = $entity->getId(); }]);
$connection = $record(Base\Entity\User\Connection::class, 5, $member);
$connections = $stub(Base\Repository\User\Connections::class, ['find' => static fn ($id) => [5 => $connection][$id] ?? null, 'findBy' => static fn (array $criteria, ?array $orderBy = null, $limit = null) => [[array_keys($criteria), $orderBy, $limit]], 'flush' => static function () use (&$flushes) { ++$flushes; }]);
$devices = new Devices($access($member), $connections, $passkeys, $tokens);
$result['devices'] = [
    'sessions' => $devices->sessions(),
    'rename: no token' => $run(static fn () => $devices->renamePasskey(1, null, 'Laptop')),
    'rename: another\'s' => $run(static fn () => $devices->renamePasskey(2, 'token-passkey_rename_2', 'Laptop')),
    'rename: none' => $run(static fn () => $devices->renamePasskey(3, 'token-passkey_rename_3', 'Laptop')),
    'rename' => $run(static fn () => $devices->renamePasskey(1, 'token-passkey_rename_1', '  Laptop ')),
    'renamed' => $passkey->getLabel(),
    'rename to nothing' => $run(static fn () => $devices->renamePasskey(1, 'token-passkey_rename_1', ' ')),
    'renamed to nothing' => $passkey->getLabel(),
    'delete: the rename\'s token' => $run(static fn () => $devices->deletePasskey(1, 'token-passkey_rename_1')),
    'delete' => $run(static fn () => $devices->deletePasskey(1, 'token-passkey_delete_1')),
    'removed' => $removed,
    'sign out: none' => $run(static fn () => $devices->signOut(6, 'token-session_logout')),
    'sign out' => $run(static fn () => $devices->signOut(5, 'token-session_logout')),
];

// The members' search.
$banned = $account(['name' => 'banned']);
$banned->setStates([Base\Enum\UserState::BANNED]);
$search = new MemberSearch($stub(Base\Repository\Users::class, ['byUsernameContaining' => static fn (string $term, int $limit = 50) => $term === 'an' && 50 === $limit ? [$account(['name' => 'anne']), $banned, $account(['name' => 'jean'])] : []]));
$result['members'] = [
    'no term' => $search->find('  '),
    'found' => array_map('strval', $search->find(' an ')),
];

// The threads' search and API.
eval('class BareArticle extends \App\Entity\Thread {} class BareTopic extends \App\Entity\Thread {}');
$hierarchy = static fn (int $level) => $stub(Base\Repository\Threads::class, ['getHierarchy' => $level]);
$repositories = static fn (array $of) => $stub(Base\Database\Repository\Repositories::class, ['of' => static fn (string $class) => $of[$class]]);
$searched = [];
$translations = $stub(Base\Repository\ThreadTranslations::class, ['search' => static function (array $terms, array $states = [], $parent = null) use (&$searched) {
    $searched[] = [$terms, $states, $parent];

    return array_map(static fn (object $thread) => new class($thread) { public function __construct(private object $thread) {} public function getTranslatable(): object { return $this->thread; } }, [new BareTopic(), new BareArticle()]);
}]);
$threadSearch = static fn (array $granted) => new ThreadSearch($access($member, $granted), $translations, $repositories(['BareArticle' => $hierarchy(1), 'BareTopic' => $hierarchy(2)]));
$model = new class { public ?string $generic = 'tokyo'; public ?string $title = 'kyoto'; public ?string $excerpt = null; public ?string $content = null; public ?int $parent_id = null; };
[$completed, $found] = $threadSearch([])->find($model);
$threadSearch(['ROLE_ADMIN'])->find($model);
$result['thread search'] = [
    'nothing asked' => $threadSearch([])->find(null),
    'the model completed' => [$completed->title, $completed->excerpt, $completed->content, $model->content],
    'the order' => array_map(static fn (object $thread) => $thread::class, $found),
    'searched' => $searched,
];

$thread = new BareArticle();
$threads = $stub(Base\Repository\Threads::class, ['oneBySlug' => static fn (string $slug, bool $cached = true) => 'here' === $slug ? $thread : null]);
$likeOf = [];
$likes = $stub(Base\Repository\Thread\Likes::class, ['oneByThreadAndUser' => static fn ($thread, $user) => $likeOf[spl_object_id($user)] ?? null]);
Base\Database\Attribute\Backend\Backends::register($unitOfWork::class, Base\Database\Backend\RowLocks::class, static fn () => new class implements Base\Database\Backend\RowLocks {
    public function locked(object $entity, callable $work): mixed
    {
        return $work();
    }
});
$api = static fn (?object $user, array $granted = []) => new ThreadActions($access($user, $granted), $tokens, $unitOfWork, $threads, $likes, $translator, $requests);
$liker = $account(['name' => 'liker']);
$result['thread api'] = [
    'no token' => $run(static fn () => $api($member, ['ROLE_ADMIN'])->publish('here', 'wrong')),
    'publish: not an administrator' => $run(static fn () => $api($member, ['ROLE_USER'])->publish('here', 'token-api_thread')),
    'publish: no such thread' => $run(static fn () => $api($member, ['ROLE_ADMIN'])->publish('nowhere', 'token-api_thread')),
    'publish' => $run(static fn () => $api($member, ['ROLE_ADMIN'])->publish('here', 'token-api_thread')),
    'published' => $thread->getState(),
    'hide' => $run(static fn () => $api($member, ['ROLE_ADMIN'])->hide('here', 'token-api_thread')),
    'hidden' => $thread->getState(),
    'follow: a visitor' => $run(static fn () => $api(null)->follow('here', 'token-api_thread')),
    'follow' => $run(static fn () => $api($member, ['ROLE_USER'])->follow('here', 'token-api_thread')),
    'followers' => \count($thread->getFollowers()),
    'unfollow' => $run(static fn () => $api($member, ['ROLE_USER'])->unfollow('here', 'token-api_thread')),
    'no longer followed' => \count($thread->getFollowers()),
    'like: no such thread' => $run(static fn () => $api($liker)->like('nowhere', 'token-api_thread')),
    'like: a visitor' => $run(static fn () => $api(null)->like('here', 'token-api_thread')),
    'like' => $run(static fn () => $api($liker)->like('here', 'token-api_thread')),
    'unlike: a visitor' => $run(static fn () => $api(null)->unlike('here', 'token-api_thread')),
    'unlike' => $run(static function () use ($api, $liker, $thread, &$likeOf) {
        $likeOf[spl_object_id($liker)] = $thread->getLikes()->first();

        return $api($liker)->unlike('here', 'token-api_thread');
    }),
];

// The notification center.
eval('class BareWebPush extends \Base\Service\Push\WebPushService { public array $subscribed = []; public function __construct(public bool $on = true) {} public function isConfigured(): bool { return $this->on; } public function getPublicKey(): ?string { return $this->on ? "VAPID" : null; } public function subscribe(\Base\Entity\User $user, array $subscription, ?string $userAgent = null): \Base\Entity\User\PushSubscription { if (!isset($subscription["endpoint"])) { throw new \InvalidArgumentException("No endpoint."); } $this->subscribed[] = [$subscription["endpoint"], $userAgent]; return new \Base\Entity\User\PushSubscription(); } public function unsubscribe(\Base\Entity\User $user, string $endpoint): bool { return "https://push.test/1" === $endpoint; } }');
$notifications = $stub(Base\Repository\User\Notifications::class, ['findVisibleFor' => static fn ($user, int $limit = 8, int $offset = 0, bool $unreadOnly = false) => [[$limit, $offset, $unreadOnly]], 'countUnreadFor' => 3]);
$center = static fn (?object $user, bool $on = true) => new NotificationCenter($access($user), $notifications, new BareWebPush($on), $tokens);
$result['notifications'] = [
    'a visitor\'s page' => $run(static fn () => $center(null)->page(false)),
    'the page, unread' => $center($member)->page(true),
    'push: a visitor' => $run(static fn () => $center(null)->pushConfig()),
    'push: a member' => $run(static fn () => $center($member)->pushConfig()),
    'push: off' => $run(static fn () => $center($member, false)->pushConfig()),
    'subscribe: no token' => $run(static fn () => $center($member)->subscribe(['subscription' => ['endpoint' => 'https://push.test/1']], 'Firefox')),
    'subscribe: off' => $run(static fn () => $center($member, false)->subscribe(['token' => 'token-notifications'], 'Firefox')),
    'subscribe: no endpoint' => $run(static fn () => $center($member)->subscribe(['token' => 'token-notifications', 'subscription' => []], 'Firefox')),
    'subscribe' => $run(static fn () => $center($member)->subscribe(['token' => 'token-notifications', 'subscription' => ['endpoint' => 'https://push.test/1']], 'Firefox')),
    'unsubscribe: a visitor' => $run(static fn () => $center(null)->unsubscribe(['token' => 'token-notifications', 'endpoint' => 'https://push.test/1'])),
    'unsubscribe' => $run(static fn () => $center($member)->unsubscribe(['token' => 'token-notifications', 'endpoint' => 'https://push.test/1'])),
    'a body' => [NotificationCenter::body('{"token":"t"}', ['form' => 1]), NotificationCenter::body('', ['form' => 1])],
    'the service worker' => str_contains(NotificationCenter::serviceWorker(), 'self.addEventListener'),
];

// The contact page's message, the profiler's test e-mail.
$mails = new ArrayObject();
$contact = new ContactMessage(new class($mails) {
    public function __construct(private ArrayObject $mails) {}
    public function __call(string $method, array $arguments): mixed
    {
        $this->mails[] = [$method, $arguments[0]->name];

        return null;
    }
});
$message = new Base\Form\Model\ContactModel();
$message->name = 'Anne';
$result['contact'] = [$run(static fn () => $contact->send($message)), $mails->getArrayCopy()];

$technical = static fn (string $address) => $stub(Base\Notifier\Abstract\BaseNotifierInterface::class, ['getTechnicalRecipient' => new Base\Notifier\Recipient\Recipient($address, null, 'fr-FR')]);
$known = $account(['name' => 'tech']);
$recipient = static fn (?object $user, string $address, array $byEmail = []) => (new TestRecipient($access($user), $technical($address), $stub(Base\Repository\Users::class, ['oneByEmail' => static fn (?string $email) => $byEmail[$email] ?? null])))->recipient();
$made = $recipient(null, 'Site <tech@site.test>');
$result['test e-mail'] = [
    'the account signed in' => (string) $recipient($member, 'tech@site.test'),
    'the technical recipient\'s account' => (string) $recipient(null, 'Site <tech@site.test>', ['tech@site.test' => $known]),
    'an address alone' => (string) $recipient(null, 'tech@site.test', ['tech@site.test' => $known]),
    'made for the e-mail' => [$made::class, $made->getEmail(), method_exists($made, 'getUsername') ? $made->getUsername() : null],
];

// The time zone's choice, the error page, a short link, an attachment, the sitemap.
$zoned = $account(['name' => 'zoned']);
$result['timezone'] = [
    'chosen' => [TimezoneSwitch::choose('Europe/Paris'), TimezoneSwitch::choose('UTC'), TimezoneSwitch::choose('Mars/Olympus'), TimezoneSwitch::choose(null)],
    'remembered, a member' => $run(static fn () => (new TimezoneSwitch($access($zoned), $unitOfWork))->remember('Asia/Tokyo')),
    'the member\'s' => $zoned->getTimezone(),
    'remembered, impersonating' => $run(static fn () => (new TimezoneSwitch($access($account(), ['IS_IMPERSONATOR']), $unitOfWork))->remember('Asia/Tokyo')),
    'back' => [TimezoneSwitch::back('https://site.test/japon/articles?page=2', 'site.test'), TimezoneSwitch::back('https://elsewhere.test/x', 'site.test'), TimezoneSwitch::back('https://site.test/timezone/UTC', 'site.test'), TimezoneSwitch::back(null, 'site.test')],
    'cookie' => TimezoneSwitch::COOKIE,
];

$environment = static fn (string $name, bool $debug) => $stub(Omnibridge\Contract\Environment::class, ['name' => $name, 'isDebug' => $debug, 'secret' => 'bare-secret']);
$result['error page'] = [
    'a developer' => (new ErrorPage($environment('dev', true)))->choose('app_index', '/'),
    'a developer, previewing' => (new ErrorPage($environment('dev', true)))->choose('_preview_error', '/_error/404'),
    'production' => (new ErrorPage($environment('prod', false)))->choose(null, '/nowhere'),
    'production, the back office' => (new ErrorPage($environment('prod', false)))->choose(null, '/admin/nowhere'),
];

// Its address read as the translation gives it (TranslatableTrait's magic reads it through PropertyAccess, whose
// property-info bare --strict does not keep).
$link = new class extends Base\Entity\Layout\ShortLink {
    public function __construct() {}
    public function getUrl(): ?string { return 'https://site.test/a/long/address'; }
};
$finder = static fn (array $bySlug) => $repositories([
    Base\Entity\Layout\ShortLink::class => eval('return new class extends '.get_class($stub(Base\Database\Repository\Entities::class, [])).' { public static array $bySlug = []; public function findOneBySlug(string $slug) { return self::$bySlug[$slug] ?? null; } };'),
    Base\Entity\Layout\Widget\Attachment::class => eval('return new class extends '.get_class($stub(Base\Database\Repository\Entities::class, [])).' { public static array $bySlug = []; public function findOneBySlug(string $slug) { return self::$bySlug[$slug] ?? null; } };'),
]);
$links = $finder([]);
$links->of(Base\Entity\Layout\ShortLink::class)::$bySlug = ['abc' => $link];
$result['short link'] = [
    'followed' => (new ShortLinkRedirect($links))->follow('abc')->toArray(),
    'none' => (new ShortLinkRedirect($links))->follow('xyz')->toArray(),
];

$file = tempnam(sys_get_temp_dir(), 'bare');
file_put_contents($file, "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
$attachment = new class($file) extends Base\Entity\Layout\Widget\Attachment {
    public function __construct(private string $file) {}
    public function getPublic(): ?string { return $this->file; }
};
$links->of(Base\Entity\Layout\Widget\Attachment::class)::$bySlug = ['brochure' => $attachment];
$download = (new AttachmentDownload($links))->file('brochure');
$result['attachment'] = [
    'downloaded' => [$download?->path === $file, $download?->type],
    'none' => (new AttachmentDownload($links))->file('nothing'),
    'a text' => AttachmentDownload::type(__FILE__),
    'no file' => AttachmentDownload::type('/nowhere/at/all'),
];
unlink($file);

$built = [];
$sitemapper = $stub(Base\Service\SitemapperInterface::class, []);
$sitemapper = eval('return new class extends '.get_class($sitemapper).' { public array $calls = []; public function setHostname(string $hostname): static { $this->calls[] = ["setHostname", $hostname]; return $this; } public function registerAttributes(): static { $this->calls[] = ["registerAttributes"]; return $this; } };');
$dispatcher = $stub(Psr\EventDispatcher\EventDispatcherInterface::class, ['dispatch' => static function (object $event) use (&$built) { $built[] = [$event::class, \func_get_args()[1] ?? null, $event->getHostname()]; return $event; }]);
(new SitemapPage($sitemapper, $dispatcher))->build('https://site.test');
$result['sitemap'] = [$sitemapper->calls, $built, SitemapPage::template('xml')];

// The welcome page, a field's validation, a money field's rate.
$welcome = Welcome::html('7.3.1', '/srv/app', '1.0.0');
$result['welcome'] = [str_contains($welcome, '7.3.1'), str_contains($welcome, '/srv/app'), str_contains($welcome, '</html>'), Welcome::STATUS];

$autovalidate = static fn (bool $debug) => new Autovalidate($environment($debug ? 'dev' : 'prod', $debug), $tokens);
$hash = $autovalidate(false)->encode(['token' => 'token-validation', 'value' => 'x', 'form_type' => 'Form']);
$result['autovalidate'] = [
    'decoded' => $autovalidate(true)->decode($hash),
    'nothing hidden' => $autovalidate(true)->decode('nothing'),
    'debug, GET' => $run(static fn () => $autovalidate(true)->answer($hash, 'GET')),
    'debug, POST' => $run(static fn () => $autovalidate(true)->answer($hash, 'POST')),
    'production, POST' => $run(static fn () => $autovalidate(false)->answer($hash, 'POST')),
    'another token' => $run(static fn () => $autovalidate(false)->answer($autovalidate(false)->encode(['token' => 'stolen']), 'POST')),
];

$obfuscator = $stub(Base\Service\ObfuscatorInterface::class, ['decode' => static fn (string $hash, bool $short = false) => ['good' => ['token' => 'token-money', 'token_name' => 'money'], 'bad' => ['token' => 'x', 'token_name' => 'money']][$hash] ?? null]);
$trading = $stub(Base\Service\TradingInterface::class, ['getFallback' => null]);
$rate = new ExchangeRate($obfuscator, $tokens, $trading);
$result['exchange rate'] = [
    'a wrong token' => $run(static fn () => $rate->rate('eur', 'jpy', 'bad')),
    'nothing hidden' => $run(static fn () => $rate->rate('eur', 'jpy', 'none')),
    'no rate stored' => $run(static fn () => $rate->rate('eur', 'jpy', 'good')),
];

return $result;
