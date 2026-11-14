<?php

namespace Tests\Base\Database;

use Base\Database\FormerNames;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * The core's classes moved to omnibridge/doctrine and omnibridge/symfony (plan "omnibase agnostic", steps F3, F5b,
 * F5c, F6) answered to their former names until 5.x (src/Resources/former-names.php aliased them). Since 6.0 no class
 * answers to a former name: FormerNames is the record of the renames (former => new), which UPGRADE-6.0.md lists and
 * MetadataSnapshot reads.
 */
final class FormerNamesTest extends TestCase
{
    public function testTheRecordOfTheRenames(): void
    {
        $moved = FormerNames::moved();
        self::assertCount(377, $moved); // 52 (F6: the bundle, its DI and the Symfony controllers), 142 to omnibridge/doctrine (F3), 78 to omnibridge/symfony (F5b), 5 (F5c), 4 (F5c item 3), 33 (F5c item 3, Console), 38 (F5c item 3: subscribers, routing, warmers, collectors, responses), 13 (F5c item 3: Security), 12 (F5c item 3: Bridge\Symfony)

        $src = \dirname(__DIR__, 2).'/src/';
        $left = [];
        foreach ($moved as $former => $new) {
            self::assertStringStartsWith('Base\\', $former);
            self::assertMatchesRegularExpression('/^Omnibridge\\\\(Doctrine|Symfony)\\\\Omnibase\\\\/', $new);
            if (is_file($src.str_replace('\\', '/', substr($former, \strlen('Base\\'))).'.php')) {
                $left[] = $former;
            }
        }
        self::assertSame([], $left, 'no file of the core declares a moved class');

        self::assertSame('Base\\Repository\\UserRepository', FormerNames::former('Omnibridge\\Doctrine\\Omnibase\\Repository\\UserRepository'));
        self::assertSame('Base\\Repository\\UserRepository', FormerNames::name('\\Omnibridge\\Doctrine\\Omnibase\\Repository\\UserRepository'));
        self::assertSame('Base\\Entity\\User', FormerNames::name('Base\\Entity\\User'));
        self::assertFalse(is_file($src.'Resources/former-names.php'), 'the aliasing autoloader is gone');
    }

    /**
     * In a process of its own, on the application's autoloader: a former name loads nothing, before or after its
     * moved class is loaded by its new name; the new name answers.
     */
    public function testNoClassAnswersToAFormerName(): void
    {
        $script = <<<'PHP'
            require $argv[1];
            $exists = static fn (string $class): bool => class_exists($class) || interface_exists($class) || trait_exists($class);
            $answer = [];
            $answer['former, first'] = $exists('Base\Bridge\Doctrine\Registry');
            $answer['new'] = $exists('Omnibridge\Doctrine\Omnibase\Registry');
            $answer['former, after the new'] = $exists('Base\Bridge\Doctrine\Registry');
            $answer['a repository, former'] = $exists('Base\Repository\Thread\TagRepository');
            $answer['a repository, new'] = $exists('Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository');
            $answer['a form type, former'] = $exists('Base\Field\Type\SelectType');
            echo json_encode($answer);
            PHP;

        // The application's autoloader, the one that loads the core (PHPUnit may come with a Composer of its own).
        $autoload = null;
        foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if (false !== $loader->findFile(\Base\Services::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        self::assertNotNull($autoload);
        if (!class_exists(\Omnibridge\Doctrine\Omnibase\Registry::class)) {
            self::markTestSkipped('omnibridge/doctrine is not installed.');
        }
        $output = shell_exec(escapeshellarg(\PHP_BINARY).' -r '.escapeshellarg($script).' '.escapeshellarg($autoload).' 2>&1');

        self::assertSame([
            'former, first' => false,
            'new' => true,
            'former, after the new' => false,
            'a repository, former' => false,
            'a repository, new' => true,
            'a form type, former' => false,
        ], json_decode((string) $output, true), (string) $output);
    }

    public function testTheEntityOfAMovedRepositoryIsTheCoresEntity(): void
    {
        self::assertSame('Base\\Entity\\Thread\\Tag', \Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository::getFqcnEntityName());
        self::assertSame('Base\\Entity\\User', \Omnibridge\Doctrine\Omnibase\Repository\UserRepository::getFqcnEntityName());
    }

    /** An entity's repositoryClass may name a repository interface of the core: the repository bound to it answers. */
    public function testARepositoryInterfaceIsTheRepositoryBoundToIt(): void
    {
        $factory = \Omnibridge\Doctrine\Omnibase\RepositoryFactory::class;

        self::assertSame(\Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository::class, $factory::implementation(\Base\Repository\Thread\Tags::class));
        self::assertSame(\Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository::class, $factory::implementation(\Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository::class));
        self::assertContains($factory::implementation(\Base\Repository\Users::class), ['App\\Repository\\UserRepository', 'Omnibridge\\Doctrine\\Omnibase\\Repository\\UserRepository']);
    }
}
