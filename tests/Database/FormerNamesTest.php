<?php

namespace Tests\Base\Database;

use Base\Database\FormerNames;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * The core's Doctrine classes moved to omnibridge/doctrine (plan "omnibase agnostic", step
 * F3): each keeps its former name, the moved class itself (src/Resources/former-names.php),
 * whichever of the two names is asked first.
 */
final class FormerNamesTest extends TestCase
{
    public function testEachFormerNameIsTheMovedClass(): void
    {
        $moved = FormerNames::moved();
        self::assertCount(262, $moved); // 142 to omnibridge/doctrine (F3), 78 to omnibridge/symfony (F5b), 5 more (F5c), 4 more (F5c item 3: 3 to omnibridge/symfony, 1 to omnibridge/doctrine), 33 more (F5c item 3, Console)

        $wrong = [];
        foreach ($moved as $former => $new) {
            if (!class_exists($former) && !interface_exists($former) && !trait_exists($former) || (new \ReflectionClass($former))->getName() !== $new) {
                $wrong[] = $former;
            }
        }

        self::assertSame([], $wrong);
    }

    public function testNoFileOfTheCoreDeclaresAMovedClass(): void
    {
        $src = \dirname(__DIR__, 2).'/src/';
        $left = [];
        foreach (FormerNames::moved() as $former => $new) {
            if (is_file($src.str_replace('\\', '/', substr($former, \strlen('Base\\'))).'.php')) {
                $left[] = $former;
            }
            self::assertMatchesRegularExpression('/^Omnibridge\\\\(Doctrine|Symfony)\\\\Omnibase\\\\/', $new);
        }

        self::assertSame([], $left);
        self::assertSame('Base\\Repository\\UserRepository', FormerNames::former('Omnibridge\\Doctrine\\Omnibase\\Repository\\UserRepository'));
        self::assertSame('Base\\Entity\\User', FormerNames::name('Base\\Entity\\User'));
    }

    /**
     * In a process of its own, so that neither name is loaded yet: the moved class loaded by its new
     * name (as Symfony's container loads it), its former name is declared at once - PHP checks an
     * instanceof or a type written with it without autoloading it -; and the other way round.
     */
    public function testWhicheverNameIsLoadedFirstTheOtherIsDeclared(): void
    {
        $script = <<<'PHP'
            require $argv[1];
            $answer = [];
            $answer['new first'] = class_exists('Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataCompletor') && class_exists('Base\Database\Mapping\ClassMetadataCompletor', false);
            $answer['former first'] = class_exists('Base\Bridge\Doctrine\Registry') && class_exists('Omnibridge\Doctrine\Omnibase\Registry', false);
            $answer['as a parent'] = class_exists('Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository') && class_exists('Base\Database\Repository\ServiceEntityRepository', false);
            $answer['typed'] = (static fn (\Base\Database\Mapping\NamingStrategy $s) => true)(new \Omnibridge\Doctrine\Omnibase\Database\Mapping\NamingStrategy());
            echo json_encode($answer);
            PHP;

        // The application's autoloader, the one that loads the core (PHPUnit may come with a Composer of its own).
        $autoload = null;
        foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if (false !== $loader->findFile(\Base\BaseBundle::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        self::assertNotNull($autoload);
        $output = shell_exec(escapeshellarg(\PHP_BINARY).' -r '.escapeshellarg($script).' '.escapeshellarg($autoload).' 2>&1');

        self::assertSame(['new first' => true, 'former first' => true, 'as a parent' => true, 'typed' => true], json_decode((string) $output, true), (string) $output);
    }

    public function testTheEntityOfAMovedRepositoryIsTheCoresEntity(): void
    {
        self::assertSame('Base\\Entity\\Thread\\Tag', \Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository::getFqcnEntityName());
        self::assertSame('Base\\Entity\\User', \Base\Repository\UserRepository::getFqcnEntityName());
    }

    /** An entity's repositoryClass may name a repository interface of the core: the repository bound to it answers. */
    public function testARepositoryInterfaceIsTheRepositoryBoundToIt(): void
    {
        $factory = \Omnibridge\Doctrine\Omnibase\RepositoryFactory::class;

        self::assertSame(\Base\Repository\Thread\TagRepository::class, $factory::implementation(\Base\Repository\Thread\Tags::class));
        self::assertSame(\Base\Repository\Thread\TagRepository::class, $factory::implementation(\Base\Repository\Thread\TagRepository::class));
        self::assertContains($factory::implementation(\Base\Repository\Users::class), ['App\\Repository\\UserRepository', 'Base\\Repository\\UserRepository']);
    }
}
