<?php

namespace Tests\Base\Bundle;

use Base\BaseBundle;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * The aliases are a fallback: App\Enum\X is Base\Enum\X only when the
 * application has no App\Enum\X of its own. The pool's list was declared with
 * class_exists($alias, false) - "not loaded yet" taken for "does not exist" -,
 * and an application's own enumeration, not loaded at boot, was replaced by
 * the core's: genealogist's App\Enum\UserRole (ROLE_PRO, ROLE_STAFF) was
 * Base\Enum\UserRole, and its roles column refused ROLE_PRO.
 */
final class AppClassOverAliasTest extends TestCase
{
    private ?string $dir = null;
    private ?ClassLoader $loader = null;

    protected function tearDown(): void
    {
        $this->loader?->unregister();
        if ($this->dir) {
            array_map('unlink', glob($this->dir.'/*.php') ?: []);
            @rmdir($this->dir);
        }
    }

    /** An application class, autoloadable and not loaded yet. */
    private function appClass(string $short, string $extends): string
    {
        $this->dir = sys_get_temp_dir().'/app-enum-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir.'/'.$short.'.php', "<?php\n\nnamespace App\\Enum;\n\nclass $short extends \\$extends\n{\n    public const OWN = 'ROLE_OWN';\n}\n");
        $this->loader = new ClassLoader($this->dir);
        $this->loader->addPsr4('App\\Enum\\', $this->dir);
        $this->loader->register(true);

        return 'App\\Enum\\'.$short;
    }

    public function testAnAliasNeverReplacesAClassTheApplicationDefines(): void
    {
        $class = $this->appClass('HarnessOwnRole'.bin2hex(random_bytes(3)), 'Base\\Enum\\UserRole');
        self::assertFalse(class_exists($class, false), 'not loaded yet');

        $declared = BaseBundle::declareAlias('Base\\Enum\\UserRole', $class);

        self::assertFalse($declared, 'no alias declared');
        self::assertSame($class, (new \ReflectionClass($class))->getName(), 'the application\'s class');
        self::assertArrayHasKey('OWN', (new \ReflectionClass($class))->getConstants());
    }

    public function testAClassNobodyDefinesIsTheAlias(): void
    {
        $alias = 'App\\Enum\\NobodysEnum'.bin2hex(random_bytes(3));

        self::assertTrue(BaseBundle::declareAlias('Base\\Enum\\UserRole', $alias));
        self::assertSame('Base\\Enum\\UserRole', (new \ReflectionClass($alias))->getName());
    }

    /**
     * A kernel without debug declares the pool's aliases without the search
     * (BaseBundle::declareAliases()): the pool lists only names no class
     * answered to when it was written, and is written again by every
     * cache:clear. Without the search the alias is declared as it is asked,
     * which is why it is never asked so with debug.
     */
    public function testWithoutTheLookupThePoolsAliasIsDeclaredAsAsked(): void
    {
        $alias = 'App\\Enum\\PoolsEnum'.bin2hex(random_bytes(3));

        self::assertTrue(BaseBundle::declareAlias('Base\\Enum\\UserRole', $alias, false));
        self::assertSame('Base\\Enum\\UserRole', (new \ReflectionClass($alias))->getName());
        self::assertTrue(BaseBundle::declareAlias('Base\\Enum\\UserRole', $alias, false), 'declared already, to that class');
    }

    public function testTheLookupStaysTheDefault(): void
    {
        $class = $this->appClass('HarnessDefaultRole'.bin2hex(random_bytes(3)), 'Base\\Enum\\UserRole');

        self::assertFalse(BaseBundle::declareAlias('Base\\Enum\\UserRole', $class));
        self::assertArrayHasKey('OWN', (new \ReflectionClass($class))->getConstants());
    }
}
