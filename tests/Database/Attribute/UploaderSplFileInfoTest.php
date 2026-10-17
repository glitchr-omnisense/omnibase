<?php

namespace Tests\Base\Database\Attribute;

use Base\Database\Attribute\Uploader;
use Base\Entity\Layout\Widget\Page;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The uploader takes any \SplFileInfo (plan "omnibase agnostique", step C3d):
 * Symfony's File and UploadedFile are ones, and so is a plain \SplFileInfo,
 * which an application without HttpFoundation hands it. What it gives back is
 * Symfony's File where HttpFoundation is installed - an entity's getter typed
 * ?File keeps its class - and the class is named by a string: Uploader loads
 * nothing of the framework. Its size limit and MIME types are read as
 * Symfony's UploadedFile and File read them.
 */
final class UploaderSplFileInfoTest extends KernelTestCase
{
    /** A 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private ?string $path = null;

    protected function tearDown(): void
    {
        if (null !== $this->path && is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    private function png(): string
    {
        $this->path = sys_get_temp_dir().'/uploader-'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($this->path, base64_decode(self::PNG));

        return $this->path;
    }

    public function testAFileIsSymfonysWhereHttpFoundationIsInstalled(): void
    {
        $file = Uploader::file($this->png());

        self::assertInstanceOf(File::class, $file);
        self::assertSame($this->path, $file->getPathname());
        self::assertSame(File::class, Uploader::SYMFONY_FILE);
    }

    public function testTheLimitAndTheMimeTypeAreSymfonys(): void
    {
        self::assertSame(UploadedFile::getMaxFilesize(), Uploader::getUploadMaxFilesize());

        $path = $this->png();
        self::assertSame('image/png', Uploader::getFileMimeType(new \SplFileInfo($path)));
        self::assertSame((new File($path))->getMimeType(), Uploader::getFileMimeType(new File($path)));
    }

    public function testUploaderNamesNoClassOfHttpFoundation(): void
    {
        $source = (string) file_get_contents((string) (new \ReflectionClass(Uploader::class))->getFileName());

        self::assertDoesNotMatchRegularExpression('~^use Symfony\\\\Component\\\\HttpFoundation~m', $source);
        self::assertDoesNotMatchRegularExpression('~instanceof File\\b|new File\\(~', $source);
    }

    public function testAPlainSplFileInfoIsUploadedAndComesBackAsAFile(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();

        $page = new Page('C3d upload', 'c3d-upload-'.bin2hex(random_bytes(3)));
        $page->setThumbnail(new \SplFileInfo($this->png()));
        $em->persist($page);
        $em->flush();

        try {
            $em->refresh($page);
            $stored = (new \ReflectionProperty($page, 'thumbnail'))->getValue($page);
            self::assertIsString($stored, 'the column holds the upload\'s name, not the file');
            self::assertMatchesRegularExpression('~^[0-9a-f-]{36}~', $stored);

            $file = $page->getThumbnailFile();
            self::assertInstanceOf(File::class, $file);
            self::assertSame(base64_decode(self::PNG), file_get_contents($file->getPathname()));
        } finally {
            $em->remove($page);
            $em->flush();
        }
    }
}
