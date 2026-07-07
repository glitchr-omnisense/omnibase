<?php

namespace Tests\Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\Compression\GzipCompression;
use PHPUnit\Framework\TestCase;

class GzipCompressionTest extends TestCase
{
    private GzipCompression $compression;

    protected function setUp(): void
    {
        $this->compression = new GzipCompression();
    }

    public function testName(): void
    {
        $this->assertSame('gzip', $this->compression->getName());
        $this->assertTrue($this->compression->supports('gzip'));
        $this->assertTrue($this->compression->supports(GzipCompression::class));
        $this->assertFalse($this->compression->supports('deflate'));
    }

    public function testRoundtrip(): void
    {
        $payload = 'hello world';

        $hash = $this->compression->encode($payload);
        $this->assertNotNull($hash);
        $this->assertSame($payload, $this->compression->decode($hash));
    }

    public function testGarbageInputDecodesToNull(): void
    {
        $this->assertNull($this->compression->decode('###'));
    }
}
