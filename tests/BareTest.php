<?php

namespace Tests\Base;

use Base\BaseBundle;
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
     * framework and who asked for it. The users' repository is the framework's by its interfaces
     * (Security's user provider); since step C3b its parent, omnibase's ServiceEntityRepository, is
     * doctrine/orm's EntityRepository and no longer brings DoctrineBundle.
     */
    public function testAClassOfTheFrameworkIsNamed(): void
    {
        [$status, $report] = self::bare([BaseBundle::class, UserRepository::class]);

        self::assertSame(1, $status);
        $bundle = $report['classes'][BaseBundle::class];
        self::assertNull($bundle['error']);
        self::assertSame('Base\\Bundle\\AbstractBaseBundle', $bundle['framework']['Symfony\\Component\\HttpKernel\\Bundle\\Bundle'] ?? null);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $bundle['framework_files']));

        $repository = $report['classes'][UserRepository::class];
        self::assertSame(UserRepository::class, $repository['framework']['Symfony\\Component\\Security\\Core\\User\\UserProviderInterface'] ?? null);
        self::assertNotEmpty(preg_grep('~/symfony/security-core/~', $repository['framework_files']));
        self::assertArrayNotHasKey('Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository', $repository['framework']);
        self::assertSame([], preg_grep('~/doctrine/doctrine-bundle/~', $repository['framework_files']));
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
     * Runs docker/bin/bare apart, on the autoloader of this run.
     *
     * @param list<string> $classes none: the Fondation
     *
     * @return array{int, array<string, mixed>} the exit status, the report
     */
    private static function bare(array $classes): array
    {
        // The autoloader that knows this package: under symfony/phpunit-bridge's simple-phpunit (an
        // application's suite) the first ClassLoader is PHPUnit's own, which knows no Base\ class.
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            if (false !== $loader->findFile(BaseBundle::class)) {
                $autoload = $vendorDir.'/autoload.php';
                break;
            }
        }
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
