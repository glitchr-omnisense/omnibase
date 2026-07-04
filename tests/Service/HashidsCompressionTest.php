<?php

namespace Tests\Base\Service;

use Base\Service\Model\Obfuscator\Compression\HashidsCompression;
use PHPUnit\Framework\TestCase;

class HashidsCompressionTest extends TestCase
{
    private HashidsCompression $compression;

    protected function setUp(): void
    {
        $this->compression = new HashidsCompression('test-secret');
    }

    public function testName(): void
    {
        $this->assertSame('hashids', $this->compression->getName());
        $this->assertTrue($this->compression->supports('hashids'));
        $this->assertTrue($this->compression->supports(HashidsCompression::class));
    }

    public function testRoundtrip(): void
    {
        $payload = 'hello world';

        $hash = $this->compression->encode($payload);
        $this->assertNotNull($hash);
        $this->assertTrue($this->compression->isValid($hash));

        $this->assertSame($payload, $this->compression->decode($hash));
    }

    public function testHashDependsOnSecret(): void
    {
        $other = new HashidsCompression('another-secret');

        $hash = $this->compression->encode('payload');
        $this->assertNotSame($hash, $other->encode('payload'));
        $this->assertNotSame('payload', $other->decode($hash));
    }

    public function testInvalidHashIsRejected(): void
    {
        $this->assertFalse($this->compression->isValid('not valid !'));
        $this->assertNull($this->compression->decode('###'));
    }
}
