<?php

namespace Tests\Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\Compression\DeflateCompression;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

class DeflateCompressionTest extends TestCase
{
    private DeflateCompression $compression;

    protected function setUp(): void
    {
        $this->compression = new DeflateCompression();
    }

    public function testName(): void
    {
        $this->assertSame('deflate', $this->compression->getName());
        $this->assertTrue($this->compression->supports('deflate'));
        $this->assertTrue($this->compression->supports(DeflateCompression::class));
        $this->assertFalse($this->compression->supports('gzip'));
    }

    public function testRoundtrip(): void
    {
        $payload = 'hello world';

        $hash = $this->compression->encode($payload);
        $this->assertNotNull($hash);
        $this->assertSame($payload, $this->compression->decode($hash));
    }

    #[WithoutErrorHandler]
    public function testGarbageInputDecodesToNull(): void
    {
        $this->assertNull($this->compression->decode('###'));
    }
}
