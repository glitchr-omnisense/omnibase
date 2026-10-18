<?php

namespace Tests\Base\Bridge\Composer;

use Base\Bundle\AbstractBaseBundle;
use Base\Service\Model\ClassAliases;
use Omnibridge\Composer\Stub\StubContext;
use Omnibridge\Composer\Stub\StubInterface;
use PHPUnit\Framework\TestCase;

/**
 * The App\ stubs the core declares (composer.json, extra.omnibridge.stubs),
 * run as omnibridge/composer runs them: each stub is the alias the runtime
 * declares - App\<Name>\X for Base\<Name>\X, ClassAliases' convention - and
 * nothing else; deterministic; written where no autoloader reads (master
 * plan §5: stubs match the runtime and never become runtime definitions).
 */
final class StubsTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return StubInterface[] */
    private static function providers(): array
    {
        if (!interface_exists(StubInterface::class)) {
            self::markTestSkipped('omnibridge/composer is not installed.');
        }

        $composer = json_decode((string) file_get_contents(self::root().'/composer.json'), true);

        return array_map(static fn (string $class) => new $class(), $composer['extra']['omnibridge']['stubs']);
    }

    /** @return array<string, array<string, string>> provider name => file => content */
    private static function generate(string $projectDir): array
    {
        $stubs = [];
        foreach (self::providers() as $provider) {
            $context = new StubContext($projectDir, $projectDir.'/vendor', self::root(), 'omniscient/omnibase');
            $stubs[$provider->getName()] = iterator_to_array($provider->generate($context));
        }

        return $stubs;
    }

    public function testEveryStubIsTheAliasTheRuntimeDeclares(): void
    {
        $stubs = self::generate(sys_get_temp_dir().'/omnibase-no-application');

        self::assertSame(['Entity', 'Enum', 'Form', 'Notifier', 'Repository'], array_keys($stubs));
        foreach ($stubs as $name => $files) {
            self::assertContains($name, ClassAliases::NAMESPACES);
            self::assertNotEmpty($files, $name);

            foreach ($files as $file => $content) {
                self::assertMatchesRegularExpression('/^namespace (App\\\\[\\\\\w]+);$/m', $content);
                preg_match('/^namespace (App\\\\[\\\\\w]+);$/m', $content, $namespace);
                preg_match('/^    class (\w+) extends \\\\(Base\\\\[\\\\\w]+) \{\}$/m', $content, $class);
                self::assertCount(3, $class, $file);

                $app = $namespace[1].'\\'.$class[1];
                self::assertSame($class[2], ClassAliases::base($app), $file);
                self::assertFileExists(self::root().'/src/'.$name.'/'.$file);
                self::assertStringContainsString("if (!class_exists('\\".$app."'))", $content);
            }
        }
    }

    public function testAnApplicationClassGetsNoStub(): void
    {
        $project = sys_get_temp_dir().'/omnibase-stubs-'.bin2hex(random_bytes(4));
        mkdir($project.'/src/Entity/User', 0777, true);
        file_put_contents($project.'/src/Entity/User.php', "<?php\nnamespace App\\Entity;\nclass User extends \\Base\\Entity\\User {}\n");

        try {
            $with = self::generate($project)['Entity'];
        } finally {
            exec('rm -rf '.escapeshellarg($project));
        }
        $without = self::generate(sys_get_temp_dir().'/omnibase-no-application')['Entity'];

        self::assertArrayHasKey('User.php', $without);
        self::assertArrayNotHasKey('User.php', $with);
        self::assertCount(count($without) - 1, $with);
    }

    public function testDeterministic(): void
    {
        self::assertSame(self::generate('/a/project'), self::generate('/a/project'));
        foreach (self::generate('/a/project') as $files) {
            foreach ($files as $content) {
                self::assertStringNotContainsString(self::root(), $content);
            }
        }
    }

    public function testNeverARuntimeDefinition(): void
    {
        $composer = json_decode((string) file_get_contents(self::root().'/composer.json'), true);

        self::assertStringNotContainsString('stubs', json_encode($composer['autoload']));
        self::assertTrue(AbstractBaseBundle::isStub('/srv/app/vendor/composer/omnibridge/stubs/Entity/User.php'));
        self::assertFalse(AbstractBaseBundle::isStub('/srv/app/src/Entity/User.php'));
    }
}
