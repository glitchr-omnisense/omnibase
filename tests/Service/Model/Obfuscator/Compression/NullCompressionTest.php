<?php

namespace Tests\Base\Service\Model\Obfuscator\Compression;

use Base\Service\Model\Obfuscator\Compression\NullCompression;
use PHPUnit\Framework\TestCase;

class NullCompressionTest extends TestCase
{
    private NullCompression $compression;

    protected function setUp(): void
    {
        $this->compression = new NullCompression();
    }

    public function testName(): void
    {
        $this->assertSame('null', $this->compression->getName());
        $this->assertTrue($this->compression->supports('null'));
        $this->assertTrue($this->compression->supports(NullCompression::class));
    }

    public function testRoundtrip(): void
    {
        $payload = 'hello world';

        $hash = $this->compression->encode($payload);
        $this->assertNotNull($hash);
        $this->assertSame($payload, $this->compression->decode($hash));
    }

    public function testEncodeIsPlainHex(): void
    {
        // No actual compression happens: encode() is just bin2hex().
        $this->assertSame(bin2hex('hi'), $this->compression->encode('hi'));
    }

    /** Odd-length input can never be valid hex. */
    public function testOddLengthInputDecodesToNull(): void
    {
        $this->assertNull($this->compression->decode('abc'));
    }

    public function testNonHexInputDecodesToNull(): void
    {
        $this->assertNull($this->compression->decode('zzzz'));
    }
}
