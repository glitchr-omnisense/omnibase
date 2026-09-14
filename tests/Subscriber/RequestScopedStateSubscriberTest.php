<?php

namespace Tests\Base\Subscriber;

use Base\Database\Attribute\Uploader;
use Base\Service\Localizer;
use Base\Subscriber\LocalizerSubscriber;
use Base\Subscriber\RequestScopedStateSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Request-scoped statics must not leak across requests in a process that
 * outlives one (FrankenPHP worker mode, messenger consumers).
 */
class RequestScopedStateSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        Uploader::forgetTemporaryFiles();
        Localizer::forgetRequestState();
    }

    protected function tearDown(): void
    {
        Uploader::forgetTemporaryFiles();
        Localizer::forgetRequestState();
    }

    private function dirtyState(): string
    {
        Localizer::markAsLate();
        $file = Uploader::materializeTemporaryFile('adapter:uuid-leak', fn () => 'bytes');

        $this->assertTrue(Localizer::isLate());
        $this->assertFileExists($file->getPathname());

        return $file->getPathname();
    }

    private function temporaryFileCount(): int
    {
        return count((new ReflectionClass(Uploader::class))->getStaticPropertyValue('tmpHashTable'));
    }

    private function event(int $type): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), Request::create('/'), $type);
    }

    public function testAMainRequestStartsFromCleanState(): void
    {
        $path = $this->dirtyState();

        (new RequestScopedStateSubscriber())->onKernelRequest($this->event(HttpKernelInterface::MAIN_REQUEST));

        $this->assertFalse(Localizer::isLate());
        $this->assertSame(0, $this->temporaryFileCount());
        $this->assertFileDoesNotExist($path);
    }

    public function testASubRequestLeavesTheRunningRequestAlone(): void
    {
        $path = $this->dirtyState();

        (new RequestScopedStateSubscriber())->onKernelRequest($this->event(HttpKernelInterface::SUB_REQUEST));

        $this->assertTrue(Localizer::isLate());
        $this->assertSame(1, $this->temporaryFileCount());
        $this->assertFileExists($path, 'a sub-request must not delete files the main request still uses');
    }

    public function testKernelResetClearsTheSameState(): void
    {
        $path = $this->dirtyState();

        $subscriber = new RequestScopedStateSubscriber();
        $this->assertInstanceOf(ResetInterface::class, $subscriber);
        $subscriber->reset();

        $this->assertFalse(Localizer::isLate());
        $this->assertFileDoesNotExist($path);
    }

    public function testItRunsBeforeTheLocalizerMarksTheRequestAsLate(): void
    {
        [, $ownPriority] = RequestScopedStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST];
        [, $localizerPriority] = LocalizerSubscriber::getSubscribedEvents()[KernelEvents::REQUEST];

        $this->assertGreaterThan($localizerPriority, $ownPriority);
    }
}
