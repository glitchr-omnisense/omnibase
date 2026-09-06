<?php

namespace Base\Mailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A hold-everything switch for outgoing mail, meant for the minutes after a
 * hotfix when you are not yet sure the site is sending the right things to
 * the right people.
 *
 * While it is armed nothing leaves: each message is written to disk instead,
 * where it can be read, deleted, or released once you have decided. That is
 * the difference from simply stopping the worker - a stopped worker holds
 * mail you cannot see, and a purge then means emptying the whole queue rather
 * than the three messages that were wrong.
 *
 * Deliberately file-backed rather than a database table or a setting:
 * this has to work in exactly the situations where you distrust the
 * application - a bad migration, a half-applied hotfix, a broken cache - so
 * it must not need the schema to be sound, and it must survive a
 * cache:clear. var/ persists across deploys here; var/cache does not.
 */
class MailTrap
{
    private const FLAG = 'trap.on';

    /**
     * In-process escape hatch for release(), NOT a second way to turn the
     * trap off.
     *
     * Sending a held message goes through the transport, and the transport
     * dispatches MessageEvent - so a release while the trap is armed was
     * caught by the trap again: a new capture, a new id, nothing delivered,
     * and the command cheerfully reporting "1 message sent". Found by
     * watching the mailbox rather than the exit code.
     *
     * Deliberately not persisted: it lasts for the one command that sets it,
     * so a crash mid-release cannot leave a trap that silently lets
     * everything through afterwards.
     */
    private bool $bypassed = false;

    public function __construct(private readonly string $directory)
    {
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * @template T
     *
     * @param callable(): T $send
     *
     * @return T
     */
    public function bypass(callable $send): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $send();
        } finally {
            $this->bypassed = $previous;
        }
    }

    public function isArmed(): bool
    {
        return \is_file($this->path(self::FLAG));
    }

    /**
     * @return array{since: string, reason: ?string}|null
     */
    public function armedDetails(): ?array
    {
        if (!$this->isArmed()) {
            return null;
        }

        $raw = @\file_get_contents($this->path(self::FLAG));
        $data = \is_string($raw) ? \json_decode($raw, true) : null;

        return \is_array($data) ? $data + ['since' => '?', 'reason' => null] : ['since' => '?', 'reason' => null];
    }

    public function arm(?string $reason = null): void
    {
        $this->ensureDirectory();
        \file_put_contents($this->path(self::FLAG), \json_encode([
            'since' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'reason' => $reason,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    public function disarm(): void
    {
        @\unlink($this->path(self::FLAG));
    }

    /**
     * Writes one message aside instead of sending it.
     *
     * The serialized message is kept alongside a readable summary: the
     * summary is what an operator scans under pressure, the serialized form
     * is what release() needs to send the real thing rather than a
     * reconstruction of it.
     */
    public function capture(RawMessage $message, Envelope $envelope): string
    {
        $this->ensureDirectory();

        $id = \date('Ymd-His') . '-' . \substr(\bin2hex(\random_bytes(4)), 0, 8);

        \file_put_contents($this->path($id . '.json'), \json_encode([
            'id' => $id,
            'capturedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'sender' => $envelope->getSender()->getAddress(),
            'to' => \array_map(static fn ($a) => $a->getAddress(), $envelope->getRecipients()),
            // A released-then-recaptured message arrives as a RawMessage, which
            // has no getSubject() - the header is read back out of the MIME so
            // the listing stays useful either way.
            'subject' => $message instanceof Email ? $message->getSubject() : self::subjectOf($message),
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

        // The message goes to disk as the RFC822 bytes it would have been put
        // on the wire as, and the envelope as plain addresses in the JSON
        // beside it. An earlier version serialized the two objects instead and
        // could not read them back - Mailer hands over a DelayedEnvelope, not
        // an Envelope, so unserialize() returned __PHP_Incomplete_Class and
        // release() died on the type. Chasing that with a longer
        // allowed_classes list would have been a list to keep in step with
        // Symfony forever; raw MIME has no such coupling, is what every
        // transport ultimately wants, and removes the object-injection
        // question entirely.
        \file_put_contents($this->path($id . '.eml'), $message->toString());

        return $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $out = [];
        foreach (\glob($this->path('*.json')) ?: [] as $file) {
            $data = \json_decode((string) \file_get_contents($file), true);
            if (\is_array($data)) {
                $out[] = $data;
            }
        }

        \usort($out, static fn (array $a, array $b) => ($a['capturedAt'] ?? '') <=> ($b['capturedAt'] ?? ''));

        return $out;
    }

    /**
     * The held message, rebuilt as a transport can send it.
     *
     * @return array{0: RawMessage, 1: Envelope}|null
     */
    public function read(string $id): ?array
    {
        $body = $this->path($id . '.eml');
        $meta = $this->path($id . '.json');
        if (!\is_file($body) || !\is_file($meta)) {
            return null;
        }

        $data = \json_decode((string) \file_get_contents($meta), true);
        if (!\is_array($data) || !isset($data['sender']) || !\is_array($data['to'] ?? null)) {
            return null;
        }

        return [
            new RawMessage((string) \file_get_contents($body)),
            new Envelope(
                new Address((string) $data['sender']),
                \array_map(static fn (string $a) => new Address($a), $data['to']),
            ),
        ];
    }

    public function forget(string $id): bool
    {
        $found = false;
        foreach (['.json', '.eml'] as $ext) {
            $file = $this->path($id . $ext);
            if (\is_file($file)) {
                @\unlink($file);
                $found = true;
            }
        }

        return $found;
    }

    private static function subjectOf(RawMessage $message): ?string
    {
        $head = \explode("\r\n\r\n", $message->toString(), 2)[0] ?? '';

        return \preg_match('/^Subject:\s*(.+)$/mi', $head, $m) ? \trim($m[1]) : null;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    private function path(string $name): string
    {
        return \rtrim($this->directory, '/') . '/' . $name;
    }

    private function ensureDirectory(): void
    {
        if (!\is_dir($this->directory)) {
            @\mkdir($this->directory, 0775, true);
        }
    }
}
