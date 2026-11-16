<?php

namespace Tests\Base\Service;

use Base\Contract\Runtime;
use Base\Service\Flysystem;
use Base\Service\Locator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The application's folders are never guessed from where the core's files are. A core installed as a path or symlinked
 * package (the ensemble, a workspace) lives outside the application: Flysystem's dirname(__FILE__, 6) gave "/" there,
 * so every public address (Uploader::getPublic(): an avatar, Editor.js' disk) was null. And the code's folder a host
 * names (Runtime::projectDir()) is not always the one serving the files (Flysystem::getPublicDir()).
 */
final class ProjectDirTest extends TestCase
{
    /**
     * The code's folder the host names (omnibase.project_dir: a twin's is the Symfony site's code) is not the folder its
     * files are served from: the application installing the core, Composer's root package, whose public/ the web
     * server serves - wherever the core is (here also behind a symlinked vendor/ of another folder).
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheCodesFolderIsNotTheServedOne(): void
    {
        $code = sys_get_temp_dir().'/omnibase-code-'.bin2hex(random_bytes(4));
        $core = \dirname((string) (new \ReflectionClass(Flysystem::class))->getFileName(), 3);
        mkdir($code.'/vendor/omnisense', 0777, true);
        symlink($core, $code.'/vendor/omnisense/omnibase');
        $host = realpath((string) \Composer\InstalledVersions::getRootPackage()['install_path']);
        try {
            // What the host gives at boot: the code's folder (BaseBundle: kernel.project_dir; omnibridge/laravel:
            // omnibase.project_dir, else base_path()).
            Locator::setProjectDir($code);

            self::assertSame($code, Runtime::projectDir(), 'the code\'s folder, the host\'s option');
            self::assertNotSame($code, $host);
            self::assertSame($host, Flysystem::getProjectDir(), 'the application serving the files');
            self::assertSame($host.'/public', Flysystem::getPublicDir(), 'the public folder getPublic() reads');
            self::assertSame($host, project_dir());
        } finally {
            unlink($code.'/vendor/omnisense/omnibase');
            rmdir($code.'/vendor/omnisense');
            rmdir($code.'/vendor');
            rmdir($code);
        }
    }

    /**
     * Before a host gave one: Composer's root package (the application installing the core), not a folder counted up
     * from the core's files.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWithoutAHostComposersRootPackage(): void
    {
        (new \ReflectionProperty(Locator::class, 'projectDir'))->setValue(null, null);
        $root = realpath((string) \Composer\InstalledVersions::getRootPackage()['install_path']);

        self::assertSame($root, Runtime::projectDir(), 'the code\'s folder too');
        self::assertSame($root.'/public', Flysystem::getPublicDir());
    }
}
