<?php

namespace Tests\Base;

use PHPUnit\Framework\TestCase;

/**
 * Covers the mail address helpers from src/Resources/Functions.php —
 * mailparse()/mailformat() drive the notifier's technical loopback rewrite,
 * so their behavior is contractual for the email safety net.
 */
class MailFunctionsTest extends TestCase
{
    public function testMailparseBareAddress(): void
    {
        $this->assertSame(
            ['john.doe@example.org' => 'john.doe@example.org'],
            mailparse('john.doe@example.org')
        );
    }

    public function testMailparseNamedAddress(): void
    {
        $this->assertSame(
            ['john.doe@example.org' => 'John Doe'],
            mailparse('John Doe <john.doe@example.org>')
        );
    }

    public function testMailparseQuotedName(): void
    {
        $this->assertSame(
            ['jane@example.org' => 'Doe, Jane'],
            mailparse('"Doe, Jane" <jane@example.org>')
        );
    }

    public function testMailparseMultipleAddresses(): void
    {
        $parsed = mailparse('John <john@example.org>, jane@example.org');

        $this->assertSame(['john@example.org', 'jane@example.org'], array_keys($parsed));
        $this->assertSame('John', $parsed['john@example.org']);
    }

    public function testMailformatFromParsedArray(): void
    {
        $this->assertSame(
            'John Doe <john.doe@example.org>',
            mailformat(mailparse('John Doe <john.doe@example.org>'))
        );
    }

    public function testMailformatExplicitNameAndEmail(): void
    {
        $this->assertSame(
            'Jane <jane@example.org>',
            mailformat([], 'Jane', 'jane@example.org')
        );
    }

    public function testMailformatEscapesAtSignInName(): void
    {
        // A display name containing "@" must not be parseable as an address.
        $this->assertSame(
            'john[at]evil.org <real@example.org>',
            mailformat([], 'john@evil.org', 'real@example.org')
        );
    }

    public function testMailformatWithoutEmailReturnsEmptyString(): void
    {
        $this->assertSame('', mailformat([]));
        $this->assertSame('', mailformat([], 'Nobody'));
    }

    public function testRoundTripKeepsAddressStable(): void
    {
        $formatted = mailformat(mailparse('John Doe <john.doe@example.org>'));

        $this->assertSame(
            ['john.doe@example.org' => 'John Doe'],
            mailparse($formatted)
        );
    }
}
