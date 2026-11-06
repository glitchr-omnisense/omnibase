<?php

/*
 * omnibase's console tasks (plan "omnibase agnostic", step F5e, slice 3: Base\Console\Task\*) where there is no
 * framework: run by tests/BareTest.php through docker/bin/bare --strict, with Composer's autoloader of var/bare/vendor
 * alone. What it returns is compared with tasks-without-framework.expected.php; omnibridge/symfony's commands and
 * omnibridge/laravel's artisan commands run the same tasks and print the same Reports.
 */

use Base\Console\Commands;
use Base\Console\Report;
use Base\Console\Task\AutocompleteEntities;
use Base\Console\Task\BaseMapping;
use Base\Console\Task\BaseMappingInput;
use Base\Console\Task\EntitiesInput;
use Base\Console\Task\EntityDiscriminator;
use Base\Console\Task\EntityDiscriminatorInput;
use Base\Console\Task\IconEntities;
use Base\Console\Task\IconEnums;
use Base\Console\Task\IconEnumsInput;
use Base\Console\Task\MailerTrap;
use Base\Console\Task\MailerTrapInput;
use Base\Console\Task\NotifierOverview;
use Base\Console\Task\NotifierOverviewInput;
use Base\Console\Task\ThreadPublishable;
use Base\Console\Task\ThreadPublishableInput;
use Base\Console\Task\TimeMachineSnapshot;
use Base\Console\Task\TimeMachineSnapshotBackupInput;
use Base\Console\Task\TimeMachineSnapshotInput;
use Base\Console\Task\TimeMachineSnapshotRestoreInput;
use Base\Console\Task\TimeMachineStorage;
use Base\Console\Task\TrashPurge;
use Base\Console\Task\TrashPurgeInput;
use Base\Console\Task\UserCreate;
use Base\Console\Task\UserCreateInput;
use Base\Console\Task\CacheClear;
use Base\Console\Task\CacheClearInput;
use Base\Console\Task\CacheClears;
use Base\Console\Task\CacheClearSessions;
use Base\Console\Task\CacheClearSessionsInput;
use Base\Console\Task\DemoReset;
use Base\Console\Task\DemoResetInput;
use Base\Console\Task\TranslationControllers;
use Base\Console\Task\TranslationControllersInput;
use Base\Console\Task\TranslationCrud;
use Base\Console\Task\TranslationCrudInput;
use Base\Console\Task\TranslationEntities;
use Base\Console\Task\TranslationEntitiesInput;
use Base\Console\Task\TranslationEnums;
use Base\Console\Task\TranslationEnumsInput;
use Base\Console\Task\TranslationSettings;
use Base\Console\Task\TranslationSettingsInput;
use Base\Console\Task\UploaderEntities;
use Base\Console\Task\UploaderEntitiesInput;
use Base\Console\Task\UploaderImages;
use Base\Console\Task\UploaderImagesCrop;
use Base\Console\Task\UploaderImagesInput;
use Base\Database\Repository\Repositories;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoGuard;
use Base\Demo\DemoMode;
use Base\Service\LocalizerInterface;
use Base\Service\MediaServiceInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Base\Service\Model\ClassAliases;
use Omnibridge\Contract\Locales;

// The App\ convention: the core's entities name App\ classes, the core's own where an application has none.
ClassAliases::register();

/** An object of the interface whose methods answer what $answers says by name (null otherwise), with $extra methods of its own. */
$stub = static function (string $interface, array $answers, string $extra = '') {
    static $n = 0;
    $class = 'TaskStub'.++$n;
    $methods = '';
    foreach ((new ReflectionClass($interface))->getMethods() as $method) {
        $parameters = implode(', ', array_map(static fn (ReflectionParameter $p) => ($p->hasType() ? $p->getType().' ' : '').($p->isPassedByReference() ? '&' : '').($p->isVariadic() ? '...' : '').'$'.$p->getName().($p->isDefaultValueAvailable() ? ' = '.var_export($p->getDefaultValue(), true) : ($p->isOptional() && !$p->isVariadic() ? ' = null' : '')), $method->getParameters()));
        $return = $method->hasReturnType() ? ': '.$method->getReturnType() : '';
        $void = \in_array((string) $method->getReturnType(), ['void', 'never'], true);
        $static = $method->isStatic() ? 'static ' : '';
        $name = var_export($method->getName(), true);
        $methods .= sprintf("public %sfunction %s(%s)%s { %s }\n", $static, $method->getName(), $parameters, $return, $void ? 'if ('.$class.'::$answers['.$name.'] ?? null) { ('.$class.'::$answers['.$name.'])(...func_get_args()); }' : 'return '.$class.'::$answers['.$name.'] instanceof Closure ? ('.$class.'::$answers['.$name.'])(...func_get_args()) : ('.$class.'::$answers['.$name.'] ?? null);');
    }
    eval(sprintf('final class %s implements \%s { public static array $answers = []; %s %s }', $class, $interface, $methods, $extra));
    $class::$answers = $answers;

    return new $class();
};

// An application of a few files: a CRUD controller, an entity and its translations' table, an enumeration.
$root = sys_get_temp_dir().'/omnibase-bare-tasks-'.getmypid();
$files = [
    'src/Controller/Admin/Crud/Blog/PostCrudController.php' => "<?php\n\nnamespace App\\Controller\\Admin\\Crud\\Blog;\n\nclass PostCrudController\n{\n}\n",
    'src/Entity/Blog/Post.php' => "<?php\n\nnamespace App\\Entity\\Blog;\n\nclass Post\n{\n}\n",
    'src/Entity/Blog/PostIntl.php' => "<?php\n\nnamespace App\\Entity\\Blog;\n\nclass PostIntl\n{\n}\n",
    'src/Entity/Blog/PostInterface.php' => "<?php\n\nnamespace App\\Entity\\Blog;\n\ninterface PostInterface\n{\n}\n",
    'src/Enum/Mood.php' => "<?php\n\nnamespace App\\Enum;\n\nclass Mood extends \\Base\\Database\\Type\\EnumType\n{\n    const HAPPY = 'happy';\n    const SAD = 'sad';\n}\n",
];
foreach ($files as $path => $code) {
    @mkdir(\dirname($root.'/'.$path), 0777, true);
    file_put_contents($root.'/'.$path, $code);
}
require_once $root.'/src/Enum/Mood.php';

$translations = [
    'fr-FR' => ['@entities.blog.post._singular' => 'billet', '@enums.mood._singular' => 'humeur', '@enums.mood.happy._singular' => 'joyeux', '@admin.crud.admin.crud.blog.post.index.title' => 'Billets', '@controllers.blog_post.title' => 'Le billet'],
    'en-GB' => ['@entities.blog.post._singular' => 'post', '@enums.mood.sad._singular' => 'sad', '@controllers.blog_post.title' => 'The post'],
];
$translator = $stub(TranslatorInterface::class, ['trans' => static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null) => $translations[$locale][$id] ?? $id]);
$localizer = $stub(LocalizerInterface::class, ['getLocale' => static fn (?string $locale = null) => ['fr' => 'fr-FR', 'en' => 'en-GB', 'de' => 'de-DE'][$locale] ?? $locale]);
$locales = $stub(Locales::class, ['getAvailableLocales' => ['fr-FR', 'en-GB'], 'getDefaultLocale' => 'fr-FR', 'getLocale' => 'fr-FR']);
$source = $root.'/src';

/** The lines of a report's text up to the first that names the core ($stop), and whether the core's were listed after. */
$application = static function (Report $report, string $stop): array {
    $lines = explode("\n", $report->text());
    $at = 0;
    while ($at < \count($lines) && !str_contains($lines[$at], $stop)) {
        ++$at;
    }

    return ['application' => \array_slice($lines, 0, $at), 'core' => $at < \count($lines)];
};

$result = [];

// The Report: text at its verbosity, PSR-3 blocks with their placeholders, a table, the status; the sink sees each entry.
$seen = [];
$report = new Report(static function (array $entry) use (&$seen) { $seen[] = $entry['kind']; });
$report->line('<info>one</info> <ln>line</ln>')->write('no end, ')->line('then the end')->line('verbose', Report::VERBOSE);
$report->warning('a {thing} warned', ['thing' => 'task']);
$report->notice('a note');
$report->success('done');
$report->table(['a', 'b'], [['<info>✓</info>', 'x']]);
$result['report'] = ['text' => $report->text(), 'verbose' => $report->text(Report::VERBOSE), 'kinds' => $seen, 'status' => $report->fail(2)->status()];

// The translation keys.
$tasks = [$translator, $localizer, $locales];
$result['crud'] = (new TranslationCrud(...$tasks))->run(new TranslationCrudInput($source))->text();
$result['crud, one language'] = (new TranslationCrud(...$tasks))->run(new TranslationCrudInput($source, locale: 'fr'))->text();
try {
    (new TranslationCrud(...$tasks))->run(new TranslationCrudInput($source, locale: 'de'));
    $result['crud, a language the site has not got'] = 'not refused';
} catch (Exception $e) {
    $result['crud, a language the site has not got'] = $e->getMessage();
}
$result['entities'] = $application((new TranslationEntities(...$tasks))->run(new TranslationEntitiesInput($source)), 'Base\\Entity');
$result['entities, restricted'] = (new TranslationEntities(...$tasks))->run(new TranslationEntitiesInput($source, 'App\\Entity\\Blog\\Post'))->text();
$result['enums'] = $application((new TranslationEnums(...$tasks))->run(new TranslationEnumsInput($source)), 'Base\\Enum');
$result['controllers'] = (new TranslationControllers(...$tasks))->run(new TranslationControllersInput([
    'blog_post' => 'App\\Controller\\BlogController::post',
    'user_profile' => 'Base\\Controller\\UserController::profile',
    'vendor_page' => 'Vendor\\Controller::page',
]))->text();

$setting = static fn (array $values, array $labels = []) => new class($values, $labels) {
    public function __construct(private array $values, private array $labels)
    {
    }

    public function getTranslations(): object
    {
        return new class(array_keys($this->values)) {
            public function __construct(private array $keys)
            {
            }

            public function getKeys(): array
            {
                return $this->keys;
            }
        };
    }

    public function translate(string $locale): object
    {
        return new class($this->values[$locale] ?? null, $this->labels[$locale] ?? null) {
            public function __construct(private mixed $value, private ?string $label)
            {
            }

            public function getValue(): mixed
            {
                return $this->value;
            }

            public function getLabel(): ?string
            {
                return $this->label;
            }

            public function getHelp(): ?string
            {
                return null;
            }
        };
    }
};
$settings = ['title' => $setting(['fr-FR' => 'Les Ringos', 'en-GB' => 'The Ringos'], ['fr-FR' => 'Titre']), 'menu' => $setting(['fr-FR' => [['home', '/'], ['blog', '/blog']]])];
$settingBag = $stub(SettingBagInterface::class, ['raw' => $settings], 'public function getRaw($path = null) { return self::$answers["raw"]; } public function denormalize(array $settings, ?string $path = null) { return $settings; }');
$result['settings'] = (new TranslationSettings($translator, $localizer, $locales, $settingBag))->run(new TranslationSettingsInput('site'))->text();
$result['settings, raw'] = (new TranslationSettings($translator, $localizer, $locales, $settingBag))->run(new TranslationSettingsInput('site', true))->text();

// The sessions: the host's session files gone, the others kept.
@mkdir($root.'/var/sessions', 0777, true);
foreach (['sess_a', 'sess_b', 'keep'] as $file) {
    touch($root.'/var/sessions/'.$file);
}
$text = (new CacheClearSessions())->run(new CacheClearSessionsInput($root.'/var/sessions'))->text();
$result['sessions'] = ['text' => str_replace($root, '<root>', $text), 'left' => array_values(array_diff(scandir($root.'/var/sessions'), ['.', '..']))];

// cache:clear without the checks: the host's clear run under the lock, counted; a second process waiting for the lock skips.
$parameters = $stub(ParameterBagInterface::class, ['get' => static fn (string $name) => ['base.autoclear' => true][$name] ?? null]);
$cacheDir = $root.'/var/cache/prod';
$cleared = [];
$clear = new CacheClear($parameters);
$first = $clear->run(new CacheClearInput($root, $cacheDir, checks: false, optionalWarmers: false, sessions: true), static function () use (&$cleared) { $cleared[] = 'clear'; return 0; }, static function () use (&$cleared) { $cleared[] = 'again'; }, static function () use (&$cleared) { $cleared[] = 'sessions'; });
$clears = new CacheClears($cacheDir.'.txt');
$held = fopen($cacheDir.'/.lock', 'c+');
flock($held, LOCK_EX | LOCK_NB);
$second = $clear->run(new CacheClearInput($root, $cacheDir, checks: false), static function () use (&$cleared) { $cleared[] = 'clear while held'; return 0; });
flock($held, LOCK_UN);
fclose($held);
$failed = $clear->run(new CacheClearInput($root, $cacheDir, checks: false), static fn () => 3);
$result['cache:clear'] = [
    'first' => $first->text(), 'second, the lock held' => $second->text(), 'second status' => $second->status(),
    'host steps' => $cleared, 'clears' => $clears->count(), 'first clear' => $clears->isFirst(), 'failing host status' => $failed->status(),
    'lock left' => file_exists($cacheDir.'/.lock'),
];

// demo:reset's refusals: outside demo, in debug, without symfony/lock (the reset itself runs the host's commands).
$commands = new class implements Commands {
    public array $ran = [];

    public function run(string $name, array $arguments = []): ?int
    {
        $this->ran[] = $name;

        return 0;
    }
};
$accounts = (new ReflectionClass(DemoAccountFactory::class))->newInstanceWithoutConstructor();
$demo = new DemoMode(DemoMode::ENVIRONMENT);
$reset = static fn (DemoMode $mode, DemoGuard $guard) => (new DemoReset($mode, $guard, $accounts))->run(new DemoResetInput(), $commands);
$result['demo:reset'] = [
    'outside demo' => [$reset(new DemoMode('prod'), new DemoGuard(new DemoMode('prod'), false))->text(), $reset(new DemoMode('prod'), new DemoGuard(new DemoMode('prod'), false))->status()],
    'in debug' => $reset($demo, new DemoGuard($demo, true))->text(),
    'without a lock' => $reset($demo, new DemoGuard($demo, false, 'mysql://database:3306/site'))->text(),
    'commands run' => $commands->ran,
];

// The uploads: an entity that has none, on both sides; uploader:images:crop on a class that is no image.
$repositories = $stub(Repositories::class, []);
$media = $stub(MediaServiceInterface::class, []);
$result['uploader:entities'] = (new UploaderEntities($repositories))->run(new UploaderEntitiesInput($source, 'App\\Entity\\Nowhere', orphans: true))->text();
$result['uploader:images'] = (new UploaderImages($repositories, $media))->run(new UploaderImagesInput($source, 'Nowhere', warmup: true))->text();
$crop = (new UploaderImagesCrop($repositories, $media))->run(new UploaderImagesInput($source, 'Blog\\Post'));
$result['uploader:images:crop, not an image'] = [$crop->text(), $crop->status()];

// Slice 3b. base:mapping: the application's classes over the core's (an App\Entity\User of its own), the summary.
@mkdir($root.'/src/Entity', 0777, true);
file_put_contents($root.'/src/Entity/User.php', "<?php\n\nnamespace App\\Entity;\n\nclass User\n{\n}\n");
$lines = explode("\n", trim((new BaseMapping())->run(new BaseMappingInput($source))->text()));
$result['base:mapping'] = ['first' => $lines[0], 'user' => array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, 'App\\Entity\\User ') || str_ends_with($line, 'App\\Entity\\User (no alias)'))), 'summary' => array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, '- ')))];
$result['base:mapping'] = ['first' => $result['base:mapping']['first'], 'user' => $result['base:mapping']['user'], 'summary' => array_map(static fn (string $line): string => preg_replace('~\d+~', 'N', $line), $result['base:mapping']['summary'])];

// icon:enums, one enumeration of the core's.
$result['icon:enums'] = (new IconEnums())->run(new IconEnumsInput($source, 'Base\\Enum\\UserRole'))->text();

// The entities' tasks on a unit of work whose shapes know App\Entity\Blog\Post only, its rows none.
$unitOfWork = $stub(Omnibridge\Persistence\UnitOfWork::class, []);
$rows = $stub(Base\Database\Repository\Entities::class, ['findAll' => [], 'findBy' => [], 'findOneBy' => static fn (array $criteria) => ($criteria['email'] ?? null) === 'taken@example.org' ? new stdClass() : null]);
Base\Database\Attribute\Backend\Backends::register($unitOfWork::class, Base\Database\Mapping\EntityShapes::class, static fn () => $stub(Base\Database\Mapping\EntityShapes::class, ['has' => static fn ($class) => 'App\\Entity\\Blog\\Post' === (is_object($class) ? $class::class : $class)]));
Base\Database\Attribute\Backend\Backends::register($unitOfWork::class, Repositories::class, static fn () => $stub(Repositories::class, ['of' => $rows]));
require_once $root.'/src/Entity/Blog/Post.php';
$refused = static function (Closure $task): string {
    try {
        $task();

        return 'not refused';
    } catch (Exception $e) {
        return $e->getMessage();
    }
};
$result['entity:discriminator'] = [
    'an entity' => (new EntityDiscriminator($unitOfWork))->run(new EntityDiscriminatorInput('App\\Entity\\Blog\\Post'))->text(),
    'not one' => $refused(static fn () => (new EntityDiscriminator($unitOfWork))->run(new EntityDiscriminatorInput('App\\Nowhere'))),
];
$result['icon:entities'] = [
    'not an entity' => $refused(static fn () => (new IconEntities($unitOfWork))->run(new EntitiesInput($source, 'App\\Nowhere'))),
    'no icons' => $refused(static fn () => (new IconEntities($unitOfWork))->run(new EntitiesInput($source, 'App\\Entity\\Blog\\Post'))),
];
$result['autocomplete:entities, no picker'] = $refused(static fn () => (new AutocompleteEntities($unitOfWork))->run(new EntitiesInput($source, 'App\\Entity\\Blog\\Post')));

// app:notifier on a notifier of two templates.
eval('namespace App\\Bare; class Notifier { public function welcome() {} public function goodbye() {} }');
$result['app:notifier'] = (new NotifierOverview())->run(new NotifierOverviewInput('App\\Bare\\Notifier'))->text();

// thread:publishable with nothing scheduled.
$result['thread:publishable'] = (new ThreadPublishable($stub(Base\Repository\Threads::class, ['byStateUncached' => []]), $unitOfWork, $translator))->run(new ThreadPublishableInput())->text(Report::VERBOSE);

// trash:purge: empty, then a dry run and a purge of one expired entry.
$trash = static fn (array $expired) => new class($expired) extends Base\Service\TrashManager {
    public function __construct(private array $entries)
    {
    }

    public function expired(?DateTimeInterface $now = null, ?int $limit = null): array
    {
        return $this->entries;
    }

    public function count(?string $entityClass = null): int
    {
        return count($this->entries);
    }

    public function purge(?DateTimeInterface $now = null, ?int $limit = null): int
    {
        return count($this->entries);
    }
};
$ball = new class {
    public function getEntityData(): array
    {
        return ['label' => 'Hello'];
    }

    public function getEntityClass(): string
    {
        return 'App\\Entity\\Blog\\Post';
    }

    public function getEntityId(): int
    {
        return 7;
    }

    public function getPermanentAfter(): DateTime
    {
        return new DateTime('2026-01-02 03:04');
    }
};
$result['trash:purge'] = [
    'empty' => (new TrashPurge($trash([])))->run(new TrashPurgeInput())->text(),
    'dry run' => (new TrashPurge($trash([$ball])))->run(new TrashPurgeInput())->text(),
    'forced' => (new TrashPurge($trash([$ball])))->run(new TrashPurgeInput(true))->text(),
];

// user:create's refusals (nothing written): an address taken, a role unknown, an e-mail without a notifier.
$create = static fn (UserCreateInput $input) => (new UserCreate($unitOfWork))->run($input);
$taken = $create(new UserCreateInput('someone', 'taken@example.org', noEmail: true));
$role = $create(new UserCreateInput('someone', 'free@example.org', ['ROLE_NOBODY'], noEmail: true));
$result['user:create'] = [
    'taken' => [$taken->text(), $taken->status()],
    'unknown role' => [$role->text(), $role->status()],
    'no notifier' => $refused(static fn () => $create(new UserCreateInput('someone', 'free@example.org'))),
    'password change token' => UserCreate::PASSWORD_CHANGE,
];

// base:mailer:trap on a folder of its own: status, list, show without an id, an unknown action, armed then disarmed.
$trap = new Base\Mailer\MailTrap($root.'/var/mail-trap');
$mailer = static fn (string $action, ?string $id = null, ?string $reason = null) => (new MailerTrap($trap))->run(new MailerTrapInput($action, $id, reason: $reason));
$armed = $mailer('on', reason: 'bare');
$result['base:mailer:trap'] = [
    'status' => preg_replace('~since [\d-]+ [\d:]+~', 'since <time>', str_replace($root, '<root>', $mailer('status')->text())),
    'list' => $mailer('list')->text(),
    'show' => [$mailer('show')->text(), $mailer('show')->status()],
    'unknown' => [$mailer('bogus')->text(), $mailer('bogus')->status()],
    'armed' => [$armed->text(), $trap->isArmed()],
    'disarmed' => [$mailer('off')->text(), $trap->isArmed()],
    'release, nothing named' => [$mailer('release')->text(), $mailer('release')->status()],
];

// The TimeMachine's tasks on a time machine of one storage and one connection, a snapshot taken on 2026-01-02.
$machine = $stub(Base\Service\TimeMachineInterface::class, ['getStorageList' => ['local' => new stdClass()], 'getDatabaseList' => ['default' => new stdClass()], 'findByCycle' => ['local' => ['20260102' => ['site-20260102.tar.gz']]], 'backup' => true, 'restore' => true]);
$storages = $stub(Base\Service\Storages::class, ['hasStorage' => true, 'getPublic' => '/backups/', 'isRemote' => false]);
$snapshot = new TimeMachineSnapshot($machine, $storages);
$asked = [];
$confirm = static function (string $question, bool $default) use (&$asked): bool {
    $asked[] = $question;

    return true;
};
$backup = $snapshot->backup(new TimeMachineSnapshotBackupInput(['local'], 'default', 'prod', -1, userlog: true), $confirm);
$restore = $snapshot->restore(new TimeMachineSnapshotRestoreInput(['local']), $confirm);
$none = $snapshot->backup(new TimeMachineSnapshotBackupInput(), $confirm);
$result['timemachine'] = [
    'storage' => (new TimeMachineStorage($machine))->run()->text(),
    'snapshot' => $snapshot->run(new TimeMachineSnapshotInput(['local'], 'default', 'prod'))->text(Report::VERBOSE),
    'backup' => [$backup->text(), $backup->status()],
    'restore' => [$restore->status(), $asked],
    'backup, no storage' => [$none->status(), str_contains($none->text(), 'Please select a storage. aborted.')],
];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
    $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($root);

return $result;
