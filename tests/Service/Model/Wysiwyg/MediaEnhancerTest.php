<?php

namespace Tests\Base\Service\Model\Wysiwyg;

use Base\Service\FlysystemInterface;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\Wysiwyg\MediaEnhancer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaEnhancerTest extends TestCase
{
    private MediaServiceInterface $mediaService;
    private FlysystemInterface $flysystem;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->mediaService = $this->createMock(MediaServiceInterface::class);
        $this->flysystem = $this->createMock(FlysystemInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function enhancer(): MediaEnhancer
    {
        return new MediaEnhancer($this->mediaService, $this->flysystem, $this->logger);
    }

    /**
     * Only "<storage>" exists, "<storage>.public" does not — the usual shape.
     */
    private function withStorage(string $storage, bool $remote): void
    {
        $this->flysystem->method('hasStorage')->willReturnCallback(fn(string $name) => $name === $storage);
        $this->flysystem->method('isRemote')->willReturn($remote);
    }

    public function testRemoteStorageIsResolvedThroughTheStorageNotTheLocalFilesystem(): void
    {
        // The regression this guards: get() hands back a storage key on a remote
        // adapter, so gating on file_exists() left every S3-stored image unenhanced.
        $this->withStorage('s3.wysiwyg', true);
        $this->flysystem->method('fileExists')->with('cat.jpg', 's3.wysiwyg')->willReturn(true);
        $this->flysystem->expects($this->never())->method('get');

        $this->mediaService->expects($this->once())
            ->method('image')
            ->with('cat.jpg', ['storage' => 's3.wysiwyg'], [])
            ->willReturn('/images/ab/cd/image.webp');

        $this->assertSame(
            '/images/ab/cd/image.webp',
            $this->enhancer()->enhance('/wysiwyg/cat.jpg', ['storage' => 's3.wysiwyg'])
        );
    }

    public function testLocalStorageStillResolvesToAnAbsoluteFilesystemPath(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'media');

        $this->withStorage('local.wysiwyg', false);
        $this->flysystem->method('fileExists')->with('cat.jpg', 'local.wysiwyg')->willReturn(true);
        $this->flysystem->method('get')->with('cat.jpg', 'local.wysiwyg')->willReturn($file);

        $this->mediaService->expects($this->once())
            ->method('image')
            ->with($file, ['storage' => 'local.wysiwyg'], [])
            ->willReturn('/images/ab/cd/image.webp');

        $this->assertSame(
            '/images/ab/cd/image.webp',
            $this->enhancer()->enhance('/wysiwyg/cat.jpg', ['storage' => 'local.wysiwyg'])
        );

        unlink($file);
    }

    public function testAPathKeepingARenamedMountStillResolves(): void
    {
        // Content written when the mount was "/media/" outlives the rename to
        // "/wysiwyg/": the URL is frozen in the stored EditorJS blocks forever.
        $this->withStorage('s3.wysiwyg', true);
        $this->flysystem->method('fileExists')->willReturnCallback(
            fn(string $path, $operator = null) => $path === 'cat.jpg' && $operator === 's3.wysiwyg'
        );

        $this->mediaService->expects($this->once())
            ->method('image')
            ->with('cat.jpg', ['storage' => 's3.wysiwyg'], [])
            ->willReturn('/images/ab/cd/image.webp');

        $this->assertSame(
            '/images/ab/cd/image.webp',
            $this->enhancer()->enhance('/media/cat.jpg', ['storage' => 's3.wysiwyg'])
        );
    }

    public function testExternalUrlsAreLeftAloneWithoutQueryingAnyStorage(): void
    {
        // Existence checks on a remote adapter are network round-trips: never
        // spend one on a URL that was never ours to serve.
        $this->flysystem->expects($this->never())->method('fileExists');
        $this->flysystem->expects($this->never())->method('hasStorage');
        $this->mediaService->expects($this->never())->method('image');
        $this->logger->expects($this->never())->method('warning');

        $enhancer = $this->enhancer();
        foreach (['https://example.com/cat.jpg', '//cdn.example.com/cat.jpg'] as $url) {
            $this->assertSame($url, $enhancer->enhance($url, ['storage' => 's3.wysiwyg']));
        }
    }

    public function testAnUnresolvableSitePathIsKeptButLogged(): void
    {
        // It is emitted verbatim into the page and served by the webserver, never
        // by MediaController, so it bypasses the no-image fallback: a silent 404.
        $this->withStorage('s3.wysiwyg', true);
        $this->flysystem->method('fileExists')->willReturn(false);
        $this->flysystem->method('get')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('unresolved media path'), $this->arrayHasKey('path'));

        $this->assertSame(
            '/media/gone.jpg',
            $this->enhancer()->enhance('/media/gone.jpg', ['storage' => 's3.wysiwyg'])
        );
    }

    public function testArraysAreEnhancedEntryByEntry(): void
    {
        $this->withStorage('s3.wysiwyg', true);
        $this->flysystem->method('fileExists')->willReturnCallback(fn(string $path) => $path === 'cat.jpg');
        $this->mediaService->method('image')->willReturn('/images/ab/cd/image.webp');

        $this->assertSame(
            ['/images/ab/cd/image.webp', 'https://example.com/dog.jpg'],
            $this->enhancer()->enhance(['/media/cat.jpg', 'https://example.com/dog.jpg'], ['storage' => 's3.wysiwyg'])
        );
    }

    public function testEmptyInputIsReturnedAsIs(): void
    {
        $this->flysystem->expects($this->never())->method('hasStorage');

        $enhancer = $this->enhancer();
        $this->assertNull($enhancer->enhance(null));
        $this->assertSame('', $enhancer->enhance(''));
    }
}
