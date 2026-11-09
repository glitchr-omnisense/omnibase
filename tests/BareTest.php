<?php

namespace Tests\Base;

use Base\BaseBundle;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Enum\UserRole;
use Base\Twig\Extension\FunctionTwigExtension;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * The core outside the framework: docker/bin/bare, run in a PHP process of
 * its own - this one has loaded PHPUnit and the suite - requires Composer's
 * autoloader alone (no kernel, no container, no bundle booted), loads every
 * class of the Foundation (tests/Foundation.php) and reports every class,
 * interface, trait and file PHP loaded on the way. None may be the
 * framework's: the Symfony bundles and bridges, dependency-injection, config,
 * http-kernel, http-foundation, routing, security-*, form, console, API
 * Platform, the third-party bundles. The standalone Symfony components,
 * Doctrine and Twig are allowed (plan "omnibase agnostique", §0, D1, D2, D4).
 *
 * The Foundation is measured where the framework is not installed (plan
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
 * The Foundation only grows: a class leaves it only by a decision written
 * down in the plan and in CHANGELOG.md.
 */
final class BareTest extends TestCase
{
    public function testTheFoundationLoadsWithNoClassNorFileOfTheFramework(): void
    {
        [$status, $report] = self::bare([]);

        $foundation = require __DIR__.'/Foundation.php';
        self::assertSame($foundation, array_keys($report['classes']), 'every class of the Foundation was loaded');
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
        self::assertSame([], $failures, "a class of the Foundation loads the framework:\n".implode("\n", $failures));
        self::assertSame(0, $status);
    }

    public function testTheFoundationIsSortedAndNamesClassesOfTheCore(): void
    {
        $foundation = require __DIR__.'/Foundation.php';
        $sorted = $foundation;
        sort($sorted);

        self::assertSame($sorted, $foundation, 'sorted');
        self::assertSame(array_values(array_unique($foundation)), $foundation, 'each class once');
        foreach ($foundation as $class) {
            self::assertFileExists(__DIR__.'/../src/'.str_replace('\\', '/', substr($class, \strlen('Base\\'))).'.php', $class);
        }
    }

    /**
     * The agnostic core (plan "omnibase agnostic", 2026-11-01, F0): the classes of tests/Agnostic.php
     * load, on the vendor without the framework, with nothing of Doctrine (collections aside), Twig,
     * a host's bridge, the framework, nor a Symfony component outside the kept ones (bare --strict).
     */
    public function testTheAgnosticCoreLoadsWithoutDoctrineNorTwig(): void
    {
        [$status, $report] = self::bare(['--strict']);

        $agnostic = require __DIR__.'/Agnostic.php';
        self::assertSame($agnostic, array_keys($report['classes']), 'every class of the agnostic core was loaded');
        self::assertSame([], $report['baseline']['symbols'], 'the autoloader alone loads no class of the wider set');

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
        self::assertSame([], $failures, "a class of the agnostic core loads Doctrine, Twig or the framework:\n".implode("\n", $failures));
        // Step E1: the entities reach no service (the routing component, property-access, a Symfony bridge, BaseTrait's accessors).
        self::assertSame([], $report['entities'], "an entity reaches a service:\n".implode("\n", $report['entities']));
        self::assertSame(0, $status);
    }

    /** The agnostic core is sorted, each class once, all of them in the Foundation and none an adapter of src/Bridge/. */
    public function testTheAgnosticCoreIsSortedAndWithinTheFoundation(): void
    {
        $agnostic = require __DIR__.'/Agnostic.php';
        $sorted = $agnostic;
        sort($sorted);

        self::assertSame($sorted, $agnostic, 'sorted');
        self::assertSame(array_values(array_unique($agnostic)), $agnostic, 'each class once');
        self::assertSame([], array_values(array_diff($agnostic, require __DIR__.'/Foundation.php')), 'every class of the agnostic core is in the Foundation');
        self::assertSame([], array_values(array_filter($agnostic, static fn (string $class) => str_starts_with($class, 'Base\\Bridge\\'))), 'no adapter of src/Bridge/');
    }

    /**
     * --strict is not blind, and is stricter than the Foundation's guard: omnibase's repository is
     * doctrine/orm's EntityRepository - Doctrine is allowed by the guard (D2), refused by --strict,
     * which names the class and the class that asked for it (omnibridge/doctrine's since step F3,
     * which moved the Doctrine repositories there, the core aliasing their former names); a Twig extension of the core is refused for Twig's
     * AbstractExtension where Twig is installed (this run's vendor: since step F4 the vendor without the
     * framework has no Twig, see testTwigIsOptional()). An enumeration was the first example until
     * step F2a made it a plain class.
     */
    public function testTheStrictCheckNamesDoctrineAndTwig(): void
    {
        [$status, $report] = self::bare([ServiceEntityRepository::class]);
        self::assertSame(0, $status, 'the Foundation\'s guard allows Doctrine');

        [$status, $report] = self::bare(['--strict', ServiceEntityRepository::class, UserRole::class]);
        self::assertSame(1, $status);
        $repository = $report['classes'][ServiceEntityRepository::class];
        self::assertNull($repository['error']);
        // omnibridge/doctrine's since step F3, under its former name: the moved class extends Doctrine's.
        self::assertSame(\Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository::class, $repository['framework']['Doctrine\\ORM\\EntityRepository'] ?? null);
        self::assertNotEmpty(preg_grep('~/doctrine/orm/~', $repository['framework_files']));
        self::assertNotEmpty(preg_grep('~/omnibridge/doctrine/~', $repository['framework_files']), 'the bridge refused by --strict');
        // Since step F2a an enumeration loads nothing of Doctrine.
        self::assertSame([], $report['classes'][UserRole::class]['framework']);
        self::assertSame([], $report['classes'][UserRole::class]['framework_files']);

        [$status, $report] = self::bare(['--strict', FunctionTwigExtension::class], self::withTheFramework());
        self::assertSame(1, $status);
        $extension = $report['classes'][FunctionTwigExtension::class];
        self::assertNull($extension['error']);
        self::assertSame(FunctionTwigExtension::class, $extension['framework']['Twig\\Extension\\AbstractExtension'] ?? null);
        self::assertNotEmpty(preg_grep('~/twig/twig/~', $extension['framework_files']));
    }

    /**
     * Twig is optional in the core (plan "omnibase agnostic", step F4; Marco: the core's Twig
     * extensions are there when Twig is used and take nothing away when it is not). twig/twig is a
     * suggestion, so the vendor without the framework has no Twig: there the classes of src/Twig that
     * extend Twig's (the extensions, the environment, the loader), and only classes of src/Twig, are
     * reported optional - not failures -; the Twig-free ones of src/Twig (the variables, the tag
     * renderers, AdminLinks, in the agnostic core since F0) and every other class of the Foundation
     * load without Twig (testTheFoundationLoadsWithNoClassNorFileOfTheFramework). Where Twig is
     * installed (this run's vendor) none is optional.
     */
    public function testTwigIsOptional(): void
    {
        $autoload = self::withoutTheFramework();
        $installed = require \dirname($autoload).'/composer/installed.php';
        self::assertArrayNotHasKey('twig/twig', $installed['versions'], 'twig/twig is not installed with the core alone');
        self::assertArrayNotHasKey('omnibridge/twig', $installed['versions']);

        [, $report] = self::bare([]);
        $foundation = require __DIR__.'/Foundation.php';
        $twig = array_values(array_filter($foundation, static fn (string $class): bool => str_starts_with($class, 'Base\\Twig\\')));
        $optional = array_keys(array_filter($report['classes'], static fn (array $class): bool => 'twig/twig' === ($class['optional'] ?? null)));
        self::assertContains(\Base\Twig\Extension\FunctionTwigExtension::class, $optional);
        self::assertContains(\Base\Twig\Environment::class, $optional);
        self::assertSame([], array_values(array_diff($optional, $twig)), 'only classes of src/Twig are optional');
        $agnostic = require __DIR__.'/Agnostic.php';
        self::assertSame([], array_values(array_intersect($optional, $agnostic)), 'the Twig-free classes of src/Twig load without Twig');

        [, $report] = self::bare($twig, self::withTheFramework());
        self::assertSame([], array_keys(array_filter($report['classes'], static fn (array $class): bool => isset($class['optional']))), 'with Twig installed, nothing is optional');
    }

    /**
     * The neutral view helpers work where Twig is not installed (plan "omnibase agnostic", step F4):
     * Base\View\Formats, Captions, Dates, Money, Pagination, Links, Enums, Menus, Widgets, Wysiwyg,
     * DeployVersion, run by a script under bare --strict on the vendor without Twig, give what the Twig
     * functions and filters gave, nothing of Twig, Doctrine nor the framework loaded.
     */
    public function testTheViewHelpersWorkWithoutTwig(): void
    {
        $script = __DIR__.'/View/helpers-without-twig.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame(require __DIR__.'/View/helpers-without-twig.expected.php', $run['result']);
    }

    /**
     * omnibase's access policies (plan "omnibase agnostic", step F5e, slice 1: Base\Auth\Policy\*) decide without
     * any framework: the restriction's attributes and exceptions, maintenance and launch, BACKEND, the permission
     * tags, the complaints, what a restricted request meets, the word of welcome - the rules omnibridge/symfony's
     * voters and SecuritySubscriber held, which they and omnibridge/laravel's gate and middleware now ask.
     */
    public function testTheAccessPoliciesDecideWithoutAFramework(): void
    {
        $script = __DIR__.'/Auth/Policy/policies-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame(require __DIR__.'/Auth/Policy/policies-without-framework.expected.php', $run['result']);
    }

    /**
     * omnibase's request rules decide where there is no framework (plan "omnibase agnostic", step F5e, slice 2):
     * Base\Request\Rule\* - the account rules, the page's language, the session's integrity, the back office's
     * figures, the redirections, the maintenance and launch pages, the pages kept for some accounts and the
     * entry point - under bare --strict, their decisions those of tests/Request/rules-without-framework.expected.php.
     */
    public function testTheRequestRulesDecideWithoutAFramework(): void
    {
        $script = __DIR__.'/Request/rules-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame(require __DIR__.'/Request/rules-without-framework.expected.php', $run['result']);
    }

    /**
     * omnibase's console tasks run where there is no framework (plan "omnibase agnostic", step F5e, slice 3):
     * Base\Console\Task\* - the translation keys of the CRUD pages, entities, enumerations, controllers and settings,
     * the sessions killed, cache:clear's lock and counter around the host's clear, demo:reset's refusals, the uploads'
     * listings - and their Base\Console\Report, under bare --strict, their reports those of
     * tests/Console/tasks-without-framework.expected.php.
     */
    public function testTheConsoleTasksRunWithoutAFramework(): void
    {
        $script = __DIR__.'/Console/tasks-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame(require __DIR__.'/Console/tasks-without-framework.expected.php', $run['result']);
    }

    /**
     * omnibase's account pages decide where there is no framework (plan "omnibase agnostic", step F5e, slice 5a):
     * Base\Auth\Account\* - the sign-in and its link, the sign-out, the pages for visitors only, the e-mail's
     * verification and the approval, the account closed and opened again, the passwords, the waiting pages, whose
     * profile, the back office's sign-in -, Base\Demo\DemoSignIn and Base\Request\LocaleSwitch, under bare --strict,
     * their Outcomes, notifications and writes those of tests/Auth/Account/account-without-framework.expected.php.
     */
    public function testTheAccountPagesDecideWithoutAFramework(): void
    {
        $script = __DIR__.'/Auth/Account/account-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame(require __DIR__.'/Auth/Account/account-without-framework.expected.php', $run['result']);
    }

    /**
     * The other pages' decisions (plan "omnibase agnostic", step F5e slice 5b): Base\Auth\Account\{SecondFactor,
     * Devices, MemberSearch}, Base\Thread\{ThreadSearch, ThreadActions}, Base\Notifier\{NotificationCenter,
     * ContactMessage, TestRecipient}, Base\Request\{TimezoneSwitch, ErrorPage, ShortLinkRedirect, AttachmentDownload,
     * SitemapPage}, Base\Screen\Welcome, Base\Field\Validation\Autovalidate and Base\Field\Search\ExchangeRate under
     * bare --strict, their answers, notifications, writes and flashes those of
     * tests/Request/Pages/pages-without-framework.expected.php.
     */
    public function testTheOtherPagesDecideWithoutAFramework(): void
    {
        $script = __DIR__.'/Request/Pages/pages-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame(require __DIR__.'/Request/Pages/pages-without-framework.expected.php', $run['result']);
    }

    /**
     * The core's services are available where there is no framework (plan "omnibase agnostic", step F5a;
     * Marco's answer 3): omnisense/omnibridge's Omnibridge\Container, built from Base\Services::definitions()
     * - the list Symfony's container and Laravel's read - and the host's contracts in plain PHP, resolves
     * the obfuscator, MediaService, the paginator, the translator, Stories and EmailComposer, and they work
     * (an e-mail composed through omnibridge's Views), under bare --strict: nothing of Symfony's container,
     * the framework, Doctrine nor Twig loaded.
     */
    public function testTheServicesAreBuiltByAPlainPhpContainer(): void
    {
        $script = __DIR__.'/Services/container-without-framework.php';

        // Nothing of the framework (Symfony's container, kernel, routing, HttpFoundation...).
        [$status, $report] = self::bare([$script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);

        // Nor Doctrine, Twig nor a host's bridge (--strict), but two packages outside the kept list, each
        // used by a service when it works, not when it loads: symfony/cache, the obfuscator's identifiers'
        // map (Base\Cache\Abstract\AbstractLocalCache's PhpArrayAdapter), and doctrine/lexer, which
        // egulias/email-validator reads when symfony/mime checks an e-mail's address (plan, F6).
        [, $strict] = self::bare(['--strict', $script]);
        $outside = array_keys($strict['classes'][$script]['framework']);
        // Since step F5e slice 4 the list is the core's whole declaration: the facade's optional services are given where
        // they are declared, and on this vendor (omnibridge/doctrine installed, its extra.omnibase.bare) those include the
        // Doctrine-backed ones (the group and widget repositories) and three core subscribers (symfony/event-dispatcher's
        // interface); property-info and type-info, the serializer's extraction. The core alone loads none of Doctrine's
        // (testTheCoreAloneLoadsTheAgnosticCore).
        self::assertSame([], array_values(preg_grep('~^(?:Symfony\\\\Component\\\\(?:Cache|EventDispatcher|PropertyInfo|TypeInfo)\\\\|Doctrine\\\\(?:Common\\\\Lexer|ORM|Persistence|DBAL)\\\\|Omnibridge\\\\Doctrine\\\\)~', $outside, PREG_GREP_INVERT)), 'only symfony/cache, event-dispatcher, property-info, type-info, doctrine/lexer and omnibridge/doctrine\'s outside the kept list');

        self::assertSame([
            'obfuscator' => [\Base\Service\Obfuscator::class, 5, true],
            'media' => [\Base\Service\MediaService::class, true],
            'paginator' => [10, '/home?page=3'],
            'translator' => [\Base\Service\Translator::class, 'Bonjour Anne'],
            'stories' => \Base\Screen\Builder\Stories::class,
            'email' => ['Welcome Anne', '<body><title>Welcome Anne</title><p>Hello Anne.</p></body>', "Welcome Anne\n\nHello Anne.\n\n\n", '"Les Ringo Stars" <postmaster@lesringostars.test>'],
            // Step F5c, item 3: the facade on the contracts, the hydrator on a unit of work and a Description.
            'base service' => [\Base\Service\BaseService::class, true, 'prod', false, 10],
            'entity hydrator' => [\Base\Database\Entity\EntityHydrator::class, 'Le Pont Mirabeau, changed', [], 'Alcools', 3],
        ], $run['result']);
    }

    /**
     * The finder language is read without Doctrine (plan "omnibase agnostic", 2026-11-01, step F1):
     * Base\Database\Repository\Finder\Parser, on an entity described by its fields, parses a finder
     * of the core's under bare --strict, nothing of Doctrine, Twig nor the framework loaded.
     */
    /**
     * The neutral form layer works where no form library is installed (plan "omnibase agnostic", step F5b;
     * Marco's answer 4: every site has forms, the admin builds its own from the Fields): field descriptors in a
     * Base\Form\Definition\FormDefinition, bound, their token checked and validated through omnibridge's
     * contracts by Base\Form\Definition\FormProcessor, their view made - under bare --strict: nothing of
     * Symfony Form, the validator, the framework, Doctrine nor Twig loaded.
     */
    public function testTheFormLayerWorksWithoutAFormLibrary(): void
    {
        $script = __DIR__.'/Form/Definition/form-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame([
            'refused' => [
                '' => ['The CSRF token is invalid. Please try to resubmit the form.'],
                'rating' => ['Please enter an integer.'],
                'content' => ['This value should not be blank.'],
            ],
            'kept' => ['email' => 'ringo@example.com', 'content' => 'Hello', 'rating' => 5, 'notify' => true, 'country' => 'JP'],
            'view' => [
                'email' => 'ringo@example.com',
                'rating' => 'five',
                'content' => ['This value should not be blank.'],
                'country' => 'choice',
                'token' => 'comment[_csrf_token]=token:comment',
            ],
        ], $run['result']);
    }

    public function testTheFinderLanguageIsReadWithoutDoctrine(): void
    {
        $script = __DIR__.'/Database/Repository/Finder/parse-without-framework.php';
        [$status, $report] = self::bare(['--strict', $script]);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);
        self::assertSame([
            'method' => '__findBy',
            'criterions' => [['id:0', 5, ['NotEqualTo']], ['activeAt:0', 300, ['YoungerThan']]],
            'doctrine loaded' => [],
        ], $run['result']);
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
        self::assertSame('Omnibridge\\Symfony\\Omnibase\\Bundle\\AbstractBaseBundle', $bundle['framework']['Symfony\\Component\\HttpKernel\\Bundle\\Bundle'] ?? null);
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
     * The vendor the Foundation is measured on holds nothing of the framework (plan "omnibase
     * agnostique", C7c): none of Symfony's framework packages, no bundle, not omnibridge/symfony,
     * security-core nor scheb/2fa - symfony/console aside, which doctrine/orm requires and no class
     * of the Foundation loads; composer/ca-bundle, certificates, is no bundle -, and the classes
     * measured are this checkout's src/.
     */
    public function testTheFoundationIsMeasuredOnAVendorWithoutTheFramework(): void
    {
        $autoload = self::withoutTheFramework();
        $installed = json_decode((string) file_get_contents(\dirname($autoload).'/composer/installed.json'), true);
        $names = array_column($installed['packages'] ?? $installed, 'name');

        self::assertContains('doctrine/orm', $names);
        self::assertNotContains('twig/twig', $names, 'Twig is a suggestion of the core since step F4');
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

    /**
     * The passkeys where the framework is not installed (plan "omniauth", AU1): Base\Entity\User\Passkey,
     * its repository and WebauthnBundle's user repository load, their Base\Contract\PasskeyRecords and
     * PasskeyUsers being empty interfaces there; and tests/Security/passkey-without-webauthn.php makes a
     * passkey and writes its columns by the core's types without loading a class of webauthn-lib - the
     * entity no longer extends its CredentialRecord.
     */
    public function testThePasskeysLoadWhereTheFrameworkIsNotInstalled(): void
    {
        $classes = [\Base\Entity\User\Passkey::class, \Base\Repository\User\PasskeyRepository::class, \Base\Security\WebauthnUserEntityRepository::class];
        [$status, $report] = self::bare($classes);
        self::assertSame(0, $status);
        foreach ($classes as $class) {
            self::assertNull($report['classes'][$class]['error'], $class);
            self::assertSame([], $report['classes'][$class]['framework'], $class);
            self::assertSame([], $report['classes'][$class]['framework_files'], $class);
        }

        $script = __DIR__.'/Security/passkey-without-webauthn.php';
        [$status, $report] = self::bare([$script]);
        self::assertSame(0, $status);
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([
            'parent' => false,
            'row' => [
                'publicKeyCredentialId' => 'AQL/',
                'credentialPublicKey' => 'pQEC',
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'trustPath' => '{"x5c":["MIIB\\/w=="]}',
            ],
            'read back' => true,
            'webauthn loaded' => [],
        ], $run['result']);
    }

    /**
     * The passkeys' repositories where WebauthnBundle is installed (this run's vendor): they declare its
     * interfaces, through Base\Contract\PasskeyRecords and PasskeyUsers, whoever loads them.
     */
    public function testThePasskeysRepositoriesDeclareTheBundlesInterfacesWhereItIsInstalled(): void
    {
        [, $report] = self::bare([\Base\Repository\User\PasskeyRepository::class, \Base\Security\WebauthnUserEntityRepository::class], self::withTheFramework());

        self::assertSame([
            'Webauthn\\Bundle\\Repository\\CanSaveCredentialRecord' => 'Base\\Contract\\PasskeyRecords',
            'Webauthn\\Bundle\\Repository\\CredentialRecordRepositoryInterface' => 'Base\\Contract\\PasskeyRecords',
        ], array_intersect_key($report['classes'][\Base\Repository\User\PasskeyRepository::class]['framework'], array_flip([
            'Webauthn\\Bundle\\Repository\\CanSaveCredentialRecord',
            'Webauthn\\Bundle\\Repository\\CredentialRecordRepositoryInterface',
        ])));
        self::assertSame(
            'Base\\Contract\\PasskeyUsers',
            $report['classes'][\Base\Security\WebauthnUserEntityRepository::class]['framework']['Webauthn\\Bundle\\Repository\\PublicKeyCredentialUserEntityRepositoryInterface'] ?? null,
        );
        self::assertContains('Webauthn\\Bundle\\Repository\\CredentialRecordRepositoryInterface', class_implements(\Base\Repository\User\PasskeyRepository::class));
        self::assertContains('Webauthn\\Bundle\\Repository\\PublicKeyCredentialUserEntityRepositoryInterface', class_implements(\Base\Security\WebauthnUserEntityRepository::class));
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
        self::assertContains(\Doctrine\ORM\EntityRepository::class, class_parents(\Base\Repository\ThreadRepository::class));
    }

    /**
     * The contracts of the Foundation work where there is no framework (plan "omnibase agnostique",
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

        $foundation = require __DIR__.'/Foundation.php';
        foreach ([\Base\Contract\Runtime::class] as $contract) {
            self::assertContains($contract, $foundation, $contract.' is in the Foundation');
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
            // BaseTrait with no host (F2f): the service said aloud, its find...() form null.
            'basetrait, no translator' => 'MissingContractException',
            'basetrait, no translator, optional' => null,
            'basetrait, no parameters' => 'MissingContractException',
            'basetrait, no parameters, optional' => null,
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
            'baseservice, session, route, link, asset, user' => ['/japon', '/japon', null, 'blog_articles', '/blog_article?slug=le-fuji', '/assets/app.css', null],
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
    /**
     * The core installed alone (plan "omnibase agnostic", step F6: 5.0 is a library): on a vendor made of
     * its require and nothing else - no omnibridge/doctrine, no Doctrine but collections, no Twig, no
     * Symfony component outside the kept list -, the agnostic core loads (bare --strict, tests/Agnostic.php),
     * and the services' list builds and works in plain PHP (symfony/event-dispatcher is there, symfony/mailer's
     * requirement, loaded by nothing of the agnostic core) (the obfuscator's map in memory there,
     * Base\Cache\Pool\MemoryPool, symfony/cache being absent).
     */
    public function testTheCoreAloneLoadsTheAgnosticCore(): void
    {
        $autoload = __DIR__.'/../var/bare-alone/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor of the core alone here (var/bare-alone/vendor): "docker/bin/bare --install --alone" makes it, the harness does before this suite.');
        }
        $installed = json_decode((string) file_get_contents(\dirname($autoload).'/composer/installed.json'), true);
        $names = array_column($installed['packages'] ?? $installed, 'name');
        self::assertSame([], array_values(preg_grep('~^(?:doctrine/(?!collections$|deprecations$|lexer$)|twig/|omnibridge/(?:doctrine|twig|symfony|laravel)$|symfony/(?:validator|cache|process|workflow|http-client|var-dumper|error-handler|asset|expression-language)$)~', $names)), 'the core alone installs nothing it left to the bridges');

        [$status, $report] = self::bare(['--strict'], (string) realpath($autoload));
        $failed = array_filter($report['classes'], static fn (array $class) => $class['framework'] || $class['framework_files'] || null !== $class['error']);
        self::assertSame([], array_keys($failed));
        self::assertSame(0, $status);
        self::assertCount(\count(require __DIR__.'/Agnostic.php'), $report['classes']);

        $script = __DIR__.'/Services/container-without-framework.php';
        [, $report] = self::bare(['--strict', $script], (string) realpath($autoload));
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        // Nothing of Doctrine nor Twig: the core alone has neither; three core subscribers' interface and the serializer's
        // extraction are outside the kept list (step F5e slice 4: the facade is given the services the declaration has).
        self::assertSame([], array_values(preg_grep('~^Symfony\\\\Component\\\\(?:Cache|EventDispatcher|PropertyInfo|TypeInfo)\\\\~', array_keys($run['framework']), PREG_GREP_INVERT)));
        self::assertSame([\Base\Service\Obfuscator::class, 5, true], $run['result']['obfuscator']);
    }

    /**
     * #[Hashify]'s hasher on the core installed alone (F6 slice 2's defect: the core named doctrine/common's ClassUtils,
     * which it does not require): tests/Database/Attribute/hashify-without-doctrine-common.php, run by bare --strict.
     */
    public function testHashifyHashesWithoutDoctrineCommon(): void
    {
        $autoload = __DIR__.'/../var/bare-alone/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor of the core alone here (var/bare-alone/vendor): "docker/bin/bare --alone --install" makes it.');
        }
        $script = __DIR__.'/Database/Attribute/hashify-without-doctrine-common.php';

        [$status, $report] = self::bare(['--strict', $script], (string) realpath($autoload));
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame(0, $status);
        self::assertSame(['doctrine/common' => false, 'hasher' => \Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher::class, 'hashed' => 'secret'], $run['result']);
    }

    /**
     * The core's entity dispatchers where doctrine/persistence is not installed (plan "omnibase agnostic", step B, leftover
     * (a)): tests/EntityDispatcher/dispatchers-without-doctrine.php, run by bare --strict on the core installed alone, calls
     * AbstractEventDispatcher::lifecycle() with omnisense/omnibridge's Lifecycle events: user.register after the account's
     * insert, user.verified after the update that verifies it (the original entity read from the PreUpdate), thread.published
     * after a published thread's insert, each dispatched once through a plain PSR-14 dispatcher and carrying the entity and
     * the unit of work, the listeners' changes flushed after; a dispatcher still overriding onPersist() (typed on
     * doctrine/persistence) refused aloud. Outside the kept list only the property accessor's extraction (property-info,
     * type-info), as for the services' list.
     */
    public function testTheEntityDispatchersRunWithoutDoctrinePersistence(): void
    {
        $autoload = __DIR__.'/../var/bare-alone/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor of the core alone here (var/bare-alone/vendor): "docker/bin/bare --alone --install" makes it.');
        }
        $script = __DIR__.'/EntityDispatcher/dispatchers-without-doctrine.php';

        [, $report] = self::bare(['--strict', $script], (string) realpath($autoload));
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        // bare exits 1 for those two (outside the kept list): what the script loaded is what is asserted.
        self::assertSame([], array_values(preg_grep('~^Symfony\\\\Component\\\\(?:PropertyInfo|TypeInfo)\\\\~', array_keys($run['framework']), PREG_GREP_INVERT)), 'nothing of Doctrine, Twig nor the framework');
        self::assertSame([
            'doctrine/persistence' => false,
            'former hooks' => [[], [], ['onPersist']],
            'user' => [[], ['user.register'], ['user.verified']],
            'user.register carries' => [true, true, true],
            'flushed after the events' => 1,
            'thread' => ['thread.published'],
            'former refused' => true,
        ], $run['result']);
    }

    /**
     * The schema of the core's entities created with no persistence installed (plan "omnibase agnostic", step F7a):
     * tests/Database/Schema/schema-without-persistence.php, run by bare --strict on the core installed alone (no Doctrine
     * but collections), describes the entities (Base\Database\Schema\EntitySchema), creates their tables in SQLite
     * (omnisense/omnibridge's SchemaCreator) and reads them back as the description.
     */
    public function testTheSchemaIsCreatedFromTheDescriptionWithoutAPersistence(): void
    {
        $autoload = __DIR__.'/../var/bare-alone/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor of the core alone here (var/bare-alone/vendor): "docker/bin/bare --alone --install" makes it.');
        }
        $script = __DIR__.'/Database/Schema/schema-without-persistence.php';

        [$status, $report] = self::bare(['--strict', $script], (string) realpath($autoload));
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework'], 'nothing of Doctrine, Twig nor the framework');
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);

        $result = $run['result'];
        self::assertGreaterThan(90, $result['entities']);
        self::assertGreaterThan(100, $result['tables'][0]);
        self::assertSame($result['tables'][0], $result['tables'][1], 'every table created');
        self::assertSame([], $result['differences'], 'the database is the description');
        self::assertTrue($result['user']);
        self::assertSame([true, true], $result['enumerations and sets']);
    }

    /**
     * The whole graph of the core's services is built by Omnibridge\Container alone (plan "omnibase agnostic", step F5e
     * slice 4), on the core installed alone (var/bare-alone: its require, nothing of Symfony's framework, Doctrine, Twig
     * nor omniauth): every definition of Base\Services that applies there is built, with the plain-PHP host of
     * container-without-framework.php and stand-ins for what it has no use for at build time (the repositories, the
     * request's contracts); the abstract classes declared for their children are parents only; each definition left out
     * says what it requires (Twig, omniauth, symfony/validator, the Symfony router...). Nothing of the framework is loaded;
     * under --strict, only what three services implement or build with: symfony/event-dispatcher's subscriber interface
     * (three core subscribers) and property-info/type-info (the serializer's property extraction).
     */
    public function testTheWholeGraphOfTheServicesIsBuiltByAPlainPhpContainer(): void
    {
        $autoload = __DIR__.'/../var/bare-alone/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor of the core alone here (var/bare-alone/vendor): "docker/bin/bare --alone --install" makes it.');
        }
        $script = __DIR__.'/Services/graph-without-framework.php';

        [$status, $report] = self::bare([$script], (string) realpath($autoload));
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame([], $run['framework']);
        self::assertSame([], $run['framework_files']);
        self::assertSame(0, $status);

        $result = $run['result'];
        self::assertSame([], $result['failed'], 'every service that applies is built');
        self::assertSame(\count(\Base\Services::definitions()), $result['declared']);
        self::assertSame($result['declared'], $result['built'] + \count($result['parents']) + $result['left out, with what they require']);
        self::assertGreaterThanOrEqual(90, $result['built']);
        foreach ($result['left'] as $id => $requires) {
            self::assertNotEmpty($requires, $id.' says what it requires');
        }

        // What is discovered (step F5e slice 4c): Omnibridge\Container alone, from Base\Discovery's registries, discovers
        // what Symfony's container discovered among the core's services on lesringostars (its compiled container,
        // tests/Services/discovered-by-symfony.php) - each collecting service built here handed the same services in the
        // same order (the obfuscator's five compressions, the six sharing adapters...), the same enumerations; the admin's
        // links are the null generator where no back office is installed; the entity dispatchers are autoconfigured.
        $discovered = $result['discovered'];
        $symfony = require __DIR__.'/Services/discovered-by-symfony.php';
        self::assertArrayHasKey(\Base\Service\Obfuscator::class, $discovered['collect']);
        self::assertArrayHasKey(\Base\Service\Sharing::class, $discovered['collect']);
        foreach ($discovered['collect'] as $service => $handed) {
            self::assertSame($symfony['collect'][$service], $handed, $service.' is handed what Symfony hands it');
        }
        self::assertCount(5, $discovered['collect'][\Base\Service\Obfuscator::class]);
        // The enumerations by their names, in any order: Base\Discovery::enumerations() lists each folder as the
        // filesystem does (APFS where discovered-by-symfony.php was written; ext4's hashed directories, whose order
        // follows each disk's hash seed, on the CI's runner), and a name is one type wherever it comes in the list.
        $enumerations = $symfony['enumerations'];
        ksort($enumerations);
        ksort($discovered['enumerations']);
        self::assertSame($enumerations, $discovered['enumerations']);
        self::assertSame([\Base\Routing\AdminUrlGeneratorInterface::class => \Base\Routing\NullAdminUrlGenerator::class], $discovered['fallbacks']);
        self::assertContains('Base\EntityDispatcher\Event\UserEventDispatcher', $discovered['autoconfigured']);
        self::assertContains('Base\EntityDispatcher\Event\ThreadEventDispatcher', $discovered['autoconfigured']);

        [, $strict] = self::bare(['--strict', $script], (string) realpath($autoload));
        $outside = array_keys($strict['classes'][$script]['framework']);
        self::assertSame([], array_values(preg_grep('~^Symfony\\\\Component\\\\(?:EventDispatcher|PropertyInfo|TypeInfo)\\\\~', $outside, PREG_GREP_INVERT)), 'only symfony/event-dispatcher, property-info and type-info outside the kept list');
    }

    /**
     * Base\Service\Trading where florianv/swap is not installed (plan "omnibase agnostic", step S): the core alone has
     * neither florianv/swap nor symfony/http-client. Omnibridge\Container, from the core's whole declaration and its
     * registries, declares no Trading; `trading_market` and TradingInterface are NullTrading (the `fallbacks`), on which
     * an amount is formatted in its own currency. tests/Service/TradingWithoutSwapTest: the same in Symfony's container.
     */
    public function testTheRatesServiceIsNullTradingWithoutFlorianvSwap(): void
    {
        $autoload = __DIR__.'/../var/bare-alone/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::markTestSkipped('No vendor of the core alone here (var/bare-alone/vendor): "docker/bin/bare --alone --install" makes it.');
        }
        $script = __DIR__.'/Service/trading-without-swap.php';

        [$status, $report] = self::bare([$script], (string) realpath($autoload));
        $run = $report['classes'][$script];
        self::assertNull($run['error']);
        self::assertSame(0, $status);
        self::assertSame([
            'installed' => ['florianv/swap' => false, 'symfony/http-client' => false],
            'declared' => false,
            'trading_market' => \Base\Service\NullTrading::class,
            'contract' => true,
            'formatted' => true,
            'converted' => 12.5,
        ], $run['result']);
    }

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
            if (false !== $loader->findFile(\Base\Services::class)) {
                return $vendorDir.'/autoload.php';
            }
        }

        return $autoload;
    }

    /**
     * Runs docker/bin/bare apart, on the autoloader given, or the vendor without the framework.
     *
     * @param list<string> $classes none: the Foundation
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
