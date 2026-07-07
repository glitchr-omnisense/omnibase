<?php

namespace Tests\Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\Compression\ZlibCompression;
use PHPUnit\Framework\TestCase;

class ZlibCompressionTest extends TestCase
{
    private ZlibCompression $compression;

    protected function setUp(): void
    {
        $this->compression = new ZlibCompression();
    }

    public function testName(): void
    {
        $this->assertSame('zlib', $this->compression->getName());
        $this->assertTrue($this->compression->supports('zlib'));
        $this->assertTrue($this->compression->supports(ZlibCompression::class));
        $this->assertFalse($this->compression->supports('gzip'));
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
