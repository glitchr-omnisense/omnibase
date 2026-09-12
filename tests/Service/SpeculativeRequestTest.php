<?php

namespace Tests\Base\Service;

use Base\Service\SpeculativeRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SpeculativeRequestTest extends TestCase
{
    /** @dataProvider speculative */
    public function testSpeculativeHeadersAreRecognised(string $header, string $value): void
    {
        $request = Request::create('/anything');
        $request->headers->set($header, $value);

        $this->assertTrue(SpeculativeRequest::is($request), "$header: $value");
    }

    public static function speculative(): iterable
    {
        yield 'fetch standard'   => ['Sec-Purpose', 'prefetch'];
        yield 'prerender'        => ['Sec-Purpose', 'prefetch;prerender'];
        yield 'chrome legacy'    => ['Purpose', 'prefetch'];
        yield 'firefox'          => ['X-Moz', 'prefetch'];
        yield 'safari top sites' => ['X-Purpose', 'preview'];
        yield 'case'             => ['Purpose', 'PREFETCH'];
    }

    public function testAnOrdinaryRequestIsNot(): void
    {
        $this->assertFalse(SpeculativeRequest::is(Request::create('/anything')));

        $request = Request::create('/anything');
        $request->headers->set('Purpose', 'navigation');
        $this->assertFalse(SpeculativeRequest::is($request));
    }
}
