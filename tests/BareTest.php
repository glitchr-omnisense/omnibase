<?php

namespace Tests\Base;

use Base\BaseBundle;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * The core outside the framework: docker/bin/bare, run in a PHP process of
 * its own - this one has loaded PHPUnit and the suite - requires Composer's
 * autoloader alone (no kernel, no container, no bundle booted), loads every
 * class of the Fondation (tests/Fondation.php) and reports every class,
 * interface, trait and file PHP loaded on the way. None may be the
 * framework's: the Symfony bundles and bridges, dependency-injection, config,
 * http-kernel, http-foundation, routing, security-*, form, console, API
 * Platform, the third-party bundles. The standalone Symfony components,
 * Doctrine and Twig are allowed (plan "omnibase agnostique", §0, D1, D2, D4).
 *
 * The Fondation is measured where the framework is not installed (plan
 * "omnibase agnostique", C7c): on var/bare/vendor, the core installed alone by
 * `composer install --no-dev` (docker/bin/bare --install, which the harness's
 * demo.sh runs before this suite), not on this run's vendor, where Symfony
 * and the bundles are - a class declares what is installed (Base\Entity\User,
 * Security's and scheb/2fa's interfaces) -, and so are the repository and the
 * scripts run "without the framework". Where that vendor was not made (an
 * application's suite reading this package in its vendor/) those cases are
 * skipped, saying so. The two that show the check is not blind (the bundle,
 * the users' repository, the user's interfaces) run on this run's vendor,
 * where the framework is installed.
 *
 * The Fondation only grows: a class leaves it only by a decision written
 * down in the plan and in CHANGELOG.md.
 */
final class BareTest extends TestCase
{
    public function testTheFondationLoadsWithNoClassNorFileOfTheFramework(): void
    {
        [$status, $report] = self::bare([]);

        $fondation = require __DIR__.'/Fondation.php';
        self::assertSame($fondation, array_keys($report['classes']), 'every class of the Fondation was loaded');
        self::assertSame([], $report['baseline']['symbols'], 'the autoloader alone loads no class of the framework');

        $failures = [];
        foreach ($report['classes'] as $class => $result) {
            if (null !== $result['error']) {
                $failures[] = $class.' does not load: '.$result['error'];
            }
            foreach ($result['framework'] as $symbol => $askedBy) {
                $failures[] = $class.' loads '.$symbol.(null === $askedBy || $askedBy === $class ? '' : ', asked by '.$askedBy);
            }
            foreach ($result['framework_files'] as $file) {
                $failures[] = $class.' loads the file '.$file;
            }
        }
        self::assertSame([], $failures, "a class of the Fondation loads the framework:\n".implode("\n", $failures));
        self::assertSame(0, $status);
    }

    public function testTheFondationIsSortedAndNamesClassesOfTheCore(): void
    {
        $fondation = require __DIR__.'/Fondation.php';
        $sorted = $fondation;
        sort($sorted);

        self::assertSame($sorted, $fondation, 'sorted');
        self::assertSame(array_values(array_unique($fondation)), $fondation, 'each class once');
        foreach ($fondation as $class) {
            self::assertFileExists(__DIR__.'/../src/'.str_replace('\\', '/', substr($class, \strlen('Base\\'))).'.php', $class);
        }
    }

    /**
     * The check is not blind: the same report, for the bundle and the users' repository, names the
     * framework and who asked for it. Where Security is installed (this run's vendor) the users'
     * repository declares its interfaces (Security's user provider, doctrine-bridge's user loader)
     * through Base\Contract\SecurityUserProvider and SecurityUserLoader (audit R2); since step C3b its parent, omnibase's
     * ServiceEntityRepository, is doctrine/orm's EntityRepository and no longer brings DoctrineBundle.
     */
    public function testAClassOfTheFrameworkIsNamed(): void
    {
        [$status, $report] = self::bare([BaseBundle::class, UserRepository::class], self::withTheFramework());

        self::assertSame(1, $status);
        $bundle = $report['classes'][BaseBundle::class];
        self::assertNull($bundle['error']);
        self::assertSame('Base\\Bundle\\AbstractBaseBundle', $bundle['framework']['Symfony\\Component\\HttpKernel\\Bundle\\Bundle'] ?? null);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $bundle['framework_files']));

        $repository = $report['classes'][UserRepository::class];
        self::assertNull($repository['error']);
        self::assertSame([
            'Symfony\\Bridge\\Doctrine\\Security\\User\\UserLoaderInterface' => 'Base\\Contract\\SecurityUserLoader',
            'Symfony\\Component\\Security\\Core\\User\\PasswordUpgraderInterface' => 'Base\\Contract\\SecurityUserProvider',
            'Symfony\\Component\\Security\\Core\\User\\UserProviderInterface' => 'Base\\Contract\\SecurityUserProvider',
        ], array_intersect_key($repository['framework'], array_flip([
            'Symfony\\Bridge\\Doctrine\\Security\\User\\UserLoaderInterface',
            'Symfony\\Component\\Security\\Core\\User\\PasswordUpgraderInterface',
            'Symfony\\Component\\Security\\Core\\User\\UserProviderInterface',
        ])));
        self::assertNotEmpty(preg_grep('~/symfony/security-core/~', $repository['framework_files']));
        self::assertArrayNotHasKey('Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository', $repository['framework']);
        self::assertSame([], preg_grep('~/doctrine/doctrine-bundle/~', $repository['framework_files']));
    }

    /**
     * The vendor the Fondation is measured on holds nothing of the framework (plan "omnibase
     * agnostique", C7c): none of Symfony's framework packages, no bundle, not omnibridge/symfony,
     * security-core nor scheb/2fa - symfony/console aside, which doctrine/orm requires and no class
     * of the Fondation loads; composer/ca-bundle, certificates, is no bundle -, and the classes
     * measured are this checkout's src/.
     */
    public function testTheFondationIsMeasuredOnAVendorWithoutTheFramework(): void
    {
        $autoload = self::withoutTheFramework();
        $installed = json_decode((string) file_get_contents(\dirname($autoload).'/composer/installed.json'), true);
        $names = array_column($installed['packages'] ?? $installed, 'name');

        self::assertContains('doctrine/orm', $names);
        self::assertContains('twig/twig', $names);
        self::assertSame([], array_values(preg_grep('~^(?:omnibridge/symfony|symfony/(?:framework-bundle|dependency-injection|config|http-kernel|http-foundation|routing|security-[a-z-]+|form|ux-[a-z-]+|[a-z-]+-bundle|[a-z-]+-bridge)|(?!composer/ca-bundle$)[a-z0-9_.-]+/[a-z0-9_.-]*-bundle|api-platform/.+|hwi/.+|scheb/.+|nelmio/.+|web-auth/webauthn-symfony-bundle)$~', $names)), 'no package of the framework is installed');
        // The map read, not the autoloader required: it is not to be registered in this process.
        $psr4 = require \dirname($autoload).'/composer/autoload_psr4.php';
        self::assertSame([realpath(__DIR__.'/../src')], array_map('realpath', $psr4['Base\\'] ?? []), 'the classes measured are this checkout\'s');
    }

    /**
     * Base\Entity\User where the framework is not installed (plan "omnibase agnostique", C7b, C7c):
     * its Base\Contract\SecurityUser and TwoFactorUser are empty interfaces, and loading it loads
     * nothing of the framework.
     */
    public function testTheUserLoadsWhereTheFrameworkIsNotInstalled(): void
    {
        [$status, $report] = self::bare([\Base\Entity\User::class]);
        self::assertSame(0, $status);
        $user = $report['classes'][\Base\Entity\User::class];
        self::assertNull($user['error']);
        self::assertSame([], $user['framework']);
        self::assertSame([], $user['framework_files']);
    }

    /**
     * Base\Entity\User where Security and scheb/2fa are installed (this run's vendor): it declares
     * their interfaces, whoever loads it - here a PHP process with the autoloader alone, no kernel.
     * Installed means declared (C7c): the check is not blind, and nothing but the vendor decides.
     */
    public function testTheUserDeclaresTheFrameworksInterfacesWhereTheyAreInstalled(): void
    {
        [$status, $report] = self::bare([\Base\Entity\User::class], self::withTheFramework());
        self::assertSame(1, $status);
        $user = $report['classes'][\Base\Entity\User::class];
        self::assertNull($user['error']);
        self::assertSame([
            'Scheb\\TwoFactorBundle\\Model\\BackupCodeInterface' => 'Base\\Contract\\TwoFactorUser',
            'Scheb\\TwoFactorBundle\\Model\\Email\\TwoFactorInterface' => 'Base\\Contract\\TwoFactorUser',
            'Scheb\\TwoFactorBundle\\Model\\Totp\\TwoFactorInterface' => 'Base\\Contract\\TwoFactorUser',
            'Scheb\\TwoFactorBundle\\Model\\TrustedDeviceInterface' => 'Base\\Contract\\TwoFactorUser',
            'Symfony\\Component\\Security\\Core\\User\\PasswordAuthenticatedUserInterface' => 'Base\\Contract\\SecurityUser',
            'Symfony\\Component\\Security\\Core\\User\\UserInterface' => 'Base\\Contract\\SecurityUser',
        ], $user['framework']);
    }

    /**
     * Base\Repository\UserRepository where the framework is not installed (audit "lesringostars face au
     * master plan", R2): its Base\Contract\SecurityUserProvider and SecurityUserLoader are empty interfaces, and loading it
     * loads nothing of the framework - the last hard seam between the core and Security on the way of
     * a Laravel application reading a site's App\Repository.
     */
    public function testTheUsersRepositoryLoadsWhereTheFrameworkIsNotInstalled(): void
    {
        [$status, $report] = self::bare([UserRepository::class]);
        self::assertSame(0, $status);
        $repository = $report['classes'][UserRepository::class];
        self::assertNull($repository['error']);
        self::assertSame([], $repository['framework']);
        self::assertSame([], $repository['framework_files']);
    }

    /** A repository of omnibase loads doctrine/orm's EntityRepository and nothing of the framework (step C3b). */
    public function testARepositoryLoadsWithoutTheFramework(): void
    {
        [$status, $report] = self::bare([\Base\Repository\ThreadRepository::class]);

        self::assertSame(0, $status);
        $repository = $report['classes'][\Base\Repository\ThreadRepository::class];
        self::assertNull($repository['error']);
        self::assertSame([], $repository['framework']);
        self::assertSame([], $repository['framework_files']);
        self::assertTrue(is_subclass_of(\Base\Repository\ThreadRepository::class, \Doctrine\ORM\EntityRepository::class));
    }

    /**
     * The contracts of the Fondation work where there is no framework (plan "omnibase agnostique",
     * C4): tests/Contract/runtime-without-framework.php, run by bare with the autoloader alone, binds
     * plain PHP implementations by the contracts' names in a PSR-11 container, as another framework
     * does at boot, hands it to Base\Contract\Runtime, and a user of the contracts written as C3e
     * writes the entities' getters answers through them: its link, its address, what the visitor may
     * do, an ICU label in two languages, its age by the clock, its thumbnail, an event. Nothing of
     * the framework is loaded on the way.
     */
    public function testTheContractsWorkWithoutTheFramework(): void
    {
        $script = __DIR__.'/Contract/runtime-without-framework.php';
        [$status, $report] = self::bare([$script, \Base\Contract\Runtime::class]);

        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame([
            'empty' => 'MissingContractException',
            'link' => '/articles/abbey-road',
            'url' => 'https://lesringostars.test/articles/abbey-road',
            'view' => true,
            'edit, nobody' => false,
            'label fr' => '« Abbey Road », sans commentaire',
            'label fr, 2' => '« Abbey Road », 2 commentaires',
            'days' => 16,
            'thumbnail' => '/uploads/covers/abbey-road.jpg',
            'asset' => '/images/logo.svg',
            'edit, alice' => true,
            'user' => 'alice',
            'label en, 1' => '"Abbey Road", 1 comment',
            'available' => ['fr-FR', 'en-GB'],
            'events' => ['abbey-road'],
            'mailer' => false,
        ], $run['result']);

        $fondation = require __DIR__.'/Fondation.php';
        foreach ([\Base\Contract\Runtime::class, \Base\Contract\Urls::class, \Base\Contract\Access::class, \Base\Contract\Locales::class, \Base\Contract\Files::class, \Base\Contract\SystemClock::class, \Base\Exception\MissingContractException::class] as $contract) {
            self::assertContains($contract, $fondation, $contract.' is in the Fondation');
        }
    }

    /**
     * omnibase's own entities answer through the contracts where there is no framework (plan
     * "omnibase agnostique", C3e): tests/Contract/entities-without-framework.php, run by bare with
     * the autoloader alone, sets plain PHP implementations of the contracts by hand and calls the
     * getters C3e moved off BaseTrait's services on the core's real classes. Nothing of the
     * framework is loaded on the way; tests/Contract/EntityGettersTest compares the same getters
     * with the services they used under Symfony.
     */
    public function testTheEntitiesAnswerThroughTheContractsWithoutTheFramework(): void
    {
        $script = __DIR__.'/Contract/entities-without-framework.php';
        [$status, $report] = self::bare([$script]);

        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame([
            'semantic, no Urls' => 'MissingContractException',
            'semantic, link' => '/article?slug=abbey-road',
            'semantic, url' => 'https://lesringostars.test/article?slug=abbey-road',
            'semantic, getUrl' => '/article?slug=abbey-road',
            'semantic, relative path' => null,
            'semantic, missing route' => null,
            'short link, no slug' => null,
            'short link, link' => '/short_redirect?slug=x7Kq2',
            'short link, url' => 'https://lesringostars.test/short_redirect?utm=mail&slug=x7Kq2',
            'short link, network path' => 'InvalidArgumentException',
            'link' => '/blog_post?slug=help',
            'thread published, nobody' => true,
            'thread draft, nobody' => false,
            'thread secret, nobody' => false,
            'thread published, an administrator' => true,
            'thread draft, an administrator' => true,
            'thread secret, an administrator' => true,
            'thread, no Locales' => null,
            'normalized' => ['fr' => 'fr-FR', 'en' => 'en-GB', 'en_US' => 'en-US', 'ja' => 'ja-FR', 'xx' => 'fr-FR'],
            'title en' => 'Abbey Road (en)',
            'title fr' => 'Abbey Road (fr)',
            'title en-US, the same language elsewhere' => 'Abbey Road (en)',
            'title ja, the default' => 'Abbey Road (fr)',
            'translation en' => 'Abbey Road (en)',
            'translations' => ['fr-FR', 'en-GB'],
            'default' => 'fr-FR',
            'intl locale' => 'en-GB',
            'short link, label' => 'Abbey Road',
        ], $run['result']);
    }

    /**
     * The screens' view models and their builder where there is no framework (plan "omnibase
     * agnostique", C5): tests/Screen/screens-without-framework.php, run by bare with the autoloader
     * alone, gives plain contracts (Locales, Texts, Pictures, a clock) and turns omnibase's own Thread,
     * Tag and Comment into a card, a story, a link and comments in a frame. Nothing of the framework
     * is loaded; the props are lesringostars' names, with the additions the pilot agreed.
     */
    public function testTheScreensAreBuiltWithoutTheFramework(): void
    {
        $script = __DIR__.'/Screen/screens-without-framework.php';
        [$status, $report] = self::bare([$script]);

        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        $result = $run['result'];
        self::assertSame(['slug', 'title', 'excerpt', 'hasContent', 'publishedAt', 'readingMinutes', 'authors', 'tags', 'likes', 'likeUrl', 'comments', 'url', 'headline', 'cover', 'taxa', 'recent'], array_keys($result['card']));
        self::assertSame(['slug' => 'sorties', 'label' => 'Sorties', 'color' => null, 'icon' => null, 'url' => '/chroniques?sujet=sorties'], $result['card']['tags'][0]);
        self::assertSame([null, null, null], [$result['card']['likes'], $result['card']['likeUrl'], $result['card']['comments']], 'none counted: null, never 0');
        self::assertSame('/uploads/couverture.jpg?w=800', $result['card']['cover']);
        self::assertSame('2026-05-11T08:00:00+00:00', $result['card']['publishedAt']);
        self::assertTrue($result['card']['recent'], 'published four hours before the clock\'s now');
        self::assertSame([['tag' => 'h2', 'slug' => 'depart', 'title' => 'Départ']], $result['story']['toc']);
        self::assertTrue($result['story']['commentable']);
        self::assertSame(['title' => 'La rentrée', 'url' => '/chroniques/la-rentree', 'state' => 'STATE_DRAFT'], $result['link']);
        self::assertSame(['locale', 'site', 'destination', 'links', 'viewer', 'theme', 'comments', 'hasMore', 'open', 'canReply', 'signIn', 'flashes'], $result['props']);
        self::assertSame(7, $result['comment']['id']);
        self::assertSame([['name' => 'Inès', 'profile' => 'https://ines.example.org', 'avatar' => null, 'staff' => false]], $result['comment']['authors']);
        self::assertFalse($result['comment']['recent']);
        self::assertSame('refused', $result['reserved'], 'a site\'s extra may not override the frame\'s own fields');
    }

    /**
     * The account's screens where there is no framework (plan "omnibase agnostique", C5, the account's
     * part): tests/Screen/accounts-without-framework.php, run by bare with the autoloader alone, gives
     * plain Urls, Tokens and a clock, turns omnibase's own Notification into the notifications' page
     * (the days' groups by the clock, in a time zone given), and makes the profile's and the settings'
     * models. Nothing of the framework is loaded. (Accounts::profile() and settings() from real
     * accounts are proven by the harness's AccountPagesSnapshotTest.)
     */
    public function testTheAccountScreensAreBuiltWithoutTheFramework(): void
    {
        $script = __DIR__.'/Screen/accounts-without-framework.php';
        [$status, $report] = self::bare([$script]);

        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        $result = $run['result'];

        self::assertSame(['member', 'own', 'recent', 'account', 'editUrl', 'settingsUrl', 'logoutUrl'], array_keys($result['profile']));
        self::assertSame(['id' => 12, 'name' => 'anne@example.org', 'avatar' => '/uploads/avatars/anne.jpg?w=128', 'avatarHtml' => null, 'roles' => ['ROLE_USER'], 'online' => false, 'active' => false, 'registeredAt' => '2025-11-02T10:20:00+00:00', 'lastSeenAt' => null, 'threads' => 2, 'likes' => 0], $result['profile']['member']);
        self::assertSame([['title' => 'Le club de lecture', 'url' => '/bbs/le-club-de-lecture', 'state' => 'STATE_PUBLISH', 'publishedAt' => '2026-04-08T18:00:00+00:00']], $result['profile']['recent']);
        self::assertSame(['twoFactor' => true, 'passkeys' => 0, 'identityProvider' => null, 'locale' => 'fr-FR', 'timezone' => 'Europe/Paris'], $result['profile']['account']);
        self::assertSame('/user/profileEdit/12', $result['profile']['editUrl']);

        $settings = $result['settings'];
        self::assertSame(['name', 'demo', 'twoFactor', 'passkeys', 'identifier', 'providers', 'sessions', 'language', 'timezone'], array_keys($settings));
        self::assertSame(['url' => '/user/settings/2fa/email/toggle', 'token' => 'token-of-2fa_email'], $settings['twoFactor']['emailToggle']);
        self::assertSame(['url' => '/user/settings/session/logout/3', 'token' => 'token-of-session_logout'], $settings['sessions'][0]['logout']);
        self::assertSame([true, false], $result['confidential'], 'the settings carry tokens, the profile none');

        $notifications = $result['notifications'];
        self::assertSame([['id' => 5, 'title' => 'Nouvelle réponse', 'content' => 'Paul a répondu.', 'url' => '/bbs/le-club-de-lecture', 'adminUrl' => null, 'importance' => 'default', 'isRead' => false, 'sentAt' => '2026-04-10T18:05:00+00:00', 'group' => 'yesterday'], ['id' => 4, 'title' => 'Bienvenue', 'content' => null, 'url' => null, 'adminUrl' => null, 'importance' => 'low', 'isRead' => true, 'sentAt' => '2026-03-30T07:00:00+00:00', 'group' => 'earlier']], $notifications['notifications']);
        self::assertSame(['token-of-notifications', '/user/notifications', '/user/notifications/read', false, null], [$notifications['token'], $notifications['allUrl'], $notifications['readUrl'], $notifications['push'], $notifications['pushKey']]);
        self::assertSame(['today', 'yesterday', 'week', 'earlier'], $result['groups'], 'the days are Tokyo\'s: 10:00 and 23:00 there, the week, before it');
        self::assertStringStartsWith('{"member":{"id":12,', $result['json']);
    }

    /**
     * What omnibase's classes read through BaseTrait's services, answered through the contracts where
     * there is no framework (plan "omnibase agnostique", C4b): tests/Contract/basetrait-seams-without-
     * framework.php, run by bare with the autoloader alone, gives plain contracts and asks the real
     * classes of the core. NounsTest, DurationTest, MediaTest, RoutesTest, EnvironmentTest and SessionsTest in the script compare each
     * answer with the service it came from, under Symfony.
     */
    public function testTheBaseTraitSeamsAnswerWithoutTheFramework(): void
    {
        $script = __DIR__.'/Contract/basetrait-seams-without-framework.php';
        [$status, $report] = self::bare([$script]);

        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame([
            // Nouns: EntityNouns over a Translations with exists(), a bundle's entity by its namespace.
            'thread, no Nouns' => 'MissingContractException',
            'key, thread' => 'thread',
            'key, app' => 'thread.tag',
            'key, bundle' => 'marketplace.order',
            'thread' => 'Article',
            'threads' => 'Articles',
            'threads, feminine' => 'Publications',
            'thread, published at' => 'Publié le',
            'order' => 'Commande',
            'order, polite' => 'Votre commande',
            'ghost' => 'ghost._plural',
            'exists, order' => true,
            'exists, ghost' => false,
            'thread, toString' => 'Article',
            'thread with a slug, toString' => 'abbey-road',
            'widget, toString' => 'Bloc #',
            // Duration over Translations (the units plainly, the spaces between them folded, the seconds left out).
            'duration, none' => '',
            'duration, a day and a second' => '1 jour 1 seconde',
            'duration, two hours two minutes two seconds' => '2 heures 2 minutes 2 secondes',
            'token, time left' => '2 jours 1 minute',
            'token, throttle' => '10 minutes',
            'thread, not to be published' => null,
            'thread, to be published' => '3 jours 2 heures',
            // Media and Urls: the image route's links, the data as the plain Media makes it.
            'image, link' => '/ux_imageExtension?extension=webp&data=%2Fuploads%2Fabbey-road.JPG%7C-%7Cwebp%7C0+filter%28s%29',
            'image, thumbnail' => '/ux_imageExtension?extension=webp&data=%2Fuploads%2Fabbey-road.JPG%7C120x80%7Cwebp%7C1+filter%28s%29',
            'image, its own extension' => '/ux_imageExtension?extension=jpg&data=%2Fuploads%2Fabbey-road.JPG%7C-%7Cas-is%7C0+filter%28s%29',
            'image, url' => 'https://lesringostars.test/ux_imageExtension?extension=png&data=%2Fuploads%2Fabbey-road.JPG%7Csquare%7Cwebp%7C0+filter%28s%29',
            'crop, link' => '/ux_imageExtension//uploads/abbey-road.JPG|square|webp|0 filter(s).webp',
            'crop, its own extension' => '/ux_imageExtension//uploads/abbey-road.JPG|square|webp|0 filter(s).jpg',
            // Routes and Urls: declared paths, routes of addresses, icons; a route widget's links.
            'semantic, path' => '/articles/{slug}',
            'semantic, route' => 'article',
            'semantic, icons' => null,
            'route widget, path' => '/blog',
            'route widget, route' => 'blog',
            'route widget, icons' => ['fa-solid fa-newspaper'],
            'route widget, link' => '/blog?page=2',
            'route widget, url' => 'https://lesringostars.test/blog',
            // Environment and parameters: User's delays and tester's check, a Token built, a vault.
            'user, active delay' => 300,
            'user, online delay' => 120,
            'user, a tester' => true,
            'user, not a tester' => false,
            'token, throttling' => 60,
            'token, salted with the secret' => true,
            'vault, secured' => 'prod',
            'vault, not secured' => null,
            // BaseTrait and BaseService on the contracts (C9): no container, no constructor.
            'basetrait, a parameter' => 300,
            'basetrait, the parameters' => true,
            'basetrait, the environment' => 'prod',
            'hot parameters, a false setting, a null one, off' => [false, 300, 300],
            'baseservice, debug, development, salt' => [true, true, 'not-so-secret'],
            'baseservice, granted' => [true, false],
            // Sessions and Locales: the visitor signed out (not kicked), someone else kicked, the locales.
            'user, the visitor signed out' => [true, false],
            'user, someone else kicked' => true,
            'user, the page\'s locale' => 'fr-FR',
            'user, its own locale' => 'en-GB',
        ], $run['result']);
    }

    /**
     * omnibase's image rule without the framework (audit "lesringostars face au master plan", R6):
     * tests/Service/media-without-framework.php builds MediaService by hand - a plain router over
     * ImageRoute::ROUTES, the Obfuscator, omnibase's Flysystem over local storages, Imagine - then makes a
     * thumbnail's address, reads it back and makes its file, loading nothing of the framework.
     */
    public function testTheImageRuleWorksWithoutTheFramework(): void
    {
        $script = __DIR__.'/Service/media-without-framework.php';
        [$status, $report] = self::bare([$script]);

        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame([
            'thumbnail, its route' => 'ux_imageExtension',
            'thumbnail, its address' => true,
            'thumbnail, the same address again' => true,
            'thumbnail, read back' => [true, [ThumbnailFilter::class], 0.8],
            'thumbnail, its file' => [100, 50, 'image/png'],
            'thumbnail, kept in the local cache' => true,
            'image, webp' => true,
            'image, extension' => 'png',
        ], $run['result']);
    }

    /**
     * The autoloader of var/bare/vendor, the core installed alone without the framework (docker/bin/bare
     * --install); the case is skipped where it was not made.
     */
    private static function withoutTheFramework(): string
    {
        $autoload = __DIR__.'/../var/bare/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor without the framework here (var/bare/vendor): "docker/bin/bare --install" makes it, the harness does before this suite.');
        }

        return (string) realpath($autoload);
    }

    /**
     * The autoloader of this run, the framework installed: the one that knows this package. Under
     * symfony/phpunit-bridge's simple-phpunit (an application's suite) the first ClassLoader is
     * PHPUnit's own, which knows no Base\ class.
     */
    private static function withTheFramework(): string
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            if (false !== $loader->findFile(BaseBundle::class)) {
                return $vendorDir.'/autoload.php';
            }
        }

        return $autoload;
    }

    /**
     * Runs docker/bin/bare apart, on the autoloader given, or the vendor without the framework.
     *
     * @param list<string> $classes none: the Fondation
     *
     * @return array{int, array<string, mixed>} the exit status, the report
     */
    private static function bare(array $classes, ?string $autoload = null): array
    {
        $autoload ??= self::withoutTheFramework();
        $output = (string) tempnam(sys_get_temp_dir(), 'bare');
        $process = proc_open([\PHP_BINARY, __DIR__.'/../docker/bin/bare', '--json', ...$classes], [1 => ['file', $output, 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIBASE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $out = (string) file_get_contents($output);
        unlink($output);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
