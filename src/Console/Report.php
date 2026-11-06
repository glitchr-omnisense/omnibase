<?php

namespace Base\Console;

use Psr\Log\AbstractLogger;

/**
 * What a console task said and how it ended (plan "omnibase agnostic", step F5e, slice 3: Base\Console\Task\*),
 * for every host to print: omnibridge/symfony's commands and omnibridge/laravel's artisan commands print the same
 * Report, neither writes anything of its own.
 *
 * A PSR-3 logger where a logger is enough (`$report->warning(...)`: a block of its level), plus the console's own
 * pieces: text written as it is (with or without its line's end, at a verbosity), a success block, a table. The
 * text keeps the console's markup (`<info>`, `<warning>`, `<ln>`, `<red>`, `<magenta>`... the styles of STYLES,
 * which both hosts declare); text() gives it plain, as `--no-ansi` prints it.
 *
 * Each entry is handed to the sink given (the host's printer) as soon as it is said, so a long task's output comes
 * as it goes and between what the host prints itself; entries() keeps them all.
 */
final class Report extends AbstractLogger
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    /** The verbosities, Symfony Console's values (OutputInterface::VERBOSITY_*), which artisan shares. */
    public const QUIET = 16;
    public const NORMAL = 32;
    public const VERBOSE = 64;
    public const VERY_VERBOSE = 128;
    public const DEBUG = 256;

    public const TEXT = 'text';
    public const SUCCESS_BLOCK = 'success';
    public const TABLE = 'table';

    /**
     * The styles the tasks' markup names: name => [foreground, background, options]. omnibase's commands declared
     * them on the output; the hosts declare these.
     *
     * @var array<string, array{0: ?string, 1: ?string, 2: list<string>}>
     */
    public const STYLES = [
        'info' => ['green', null, []], 'bold,info' => ['green', null, ['bold']], 'info,bold' => ['green', null, ['bold']],
        'info,bkg' => ['black', 'green', []], 'bkg,info' => ['black', 'green', []],
        'warning' => ['yellow', null, []], 'warning,bold' => ['yellow', null, ['bold']], 'bold,warning' => ['yellow', null, ['bold']],
        'warning,bkg' => ['black', 'yellow', []], 'bkg,warning' => ['black', 'yellow', []],
        'red' => ['red', null, []], 'red,bold' => ['red', null, ['bold']], 'bold,red' => ['red', null, ['bold']],
        'red,bkg' => [null, 'red', []], 'bkg,red' => [null, 'red', []],
        'ln' => ['cyan', null, []], 'ln,bold' => ['cyan', null, ['bold']], 'bold,ln' => ['cyan', null, ['bold']],
        'ln,bkg' => [null, 'cyan', []], 'bkg,ln' => [null, 'cyan', []],
        'magenta' => ['magenta', null, []], 'magenta,bold' => ['magenta', null, ['bold']], 'bold,magenta' => ['magenta', null, ['bold']],
        'magenta,bkg' => [null, 'magenta', []], 'bkg,magenta' => [null, 'magenta', []],
    ];

    /** @var list<array{kind: string, text: string, newline: bool, verbosity: int, headers?: list<string>, rows?: list<list<string>>}> */
    private array $entries = [];

    private int $status = self::SUCCESS;

    /** @param ?\Closure(array{kind: string, text: string, newline: bool, verbosity: int, headers?: list<string>, rows?: list<list<string>>}): void $sink */
    public function __construct(private readonly ?\Closure $sink = null)
    {
    }

    /** Text as it is, its line ended or not. */
    public function write(string $text, bool $newline = false, int $verbosity = self::NORMAL): self
    {
        return $this->add(['kind' => self::TEXT, 'text' => $text, 'newline' => $newline, 'verbosity' => $verbosity]);
    }

    /** A line of text. */
    public function line(string $text = '', int $verbosity = self::NORMAL): self
    {
        return $this->write($text, true, $verbosity);
    }

    public function success(string|\Stringable $message): self
    {
        return $this->add(['kind' => self::SUCCESS_BLOCK, 'text' => (string) $message, 'newline' => true, 'verbosity' => self::NORMAL]);
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): self
    {
        return $this->add(['kind' => self::TABLE, 'text' => '', 'newline' => true, 'verbosity' => self::NORMAL, 'headers' => $headers, 'rows' => $rows]);
    }

    /** A block of the level given (PSR-3), {placeholders} replaced from the context. */
    public function log($level, $message, array $context = []): void
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (null === $value || \is_scalar($value) || $value instanceof \Stringable) {
                $replace['{'.$key.'}'] = (string) $value;
            }
        }

        $this->add(['kind' => (string) $level, 'text' => strtr((string) $message, $replace), 'newline' => true, 'verbosity' => self::NORMAL]);
    }

    /** The task ends with this status (not 0: failed). */
    public function fail(int $status = self::FAILURE): self
    {
        $this->status = $status;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return list<array{kind: string, text: string, newline: bool, verbosity: int, headers?: list<string>, rows?: list<list<string>>}> */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * What the report says up to a verbosity, plain: the markup taken out as `--no-ansi` prints it, a block as
     * "[LEVEL] text", a table's cells joined by " | ". What the hosts print is compared through it.
     */
    public function text(int $verbosity = self::NORMAL): string
    {
        $text = '';
        foreach ($this->entries as $entry) {
            if ($entry['verbosity'] > $verbosity) {
                continue;
            }

            $text .= match ($entry['kind']) {
                self::TEXT => self::plain($entry['text']).($entry['newline'] ? "\n" : ''),
                self::TABLE => implode('', array_map(static fn (array $row): string => self::plain(implode(' | ', $row))."\n", [$entry['headers'] ?? [], ...$entry['rows'] ?? []])),
                default => '['.strtoupper($entry['kind']).'] '.self::plain($entry['text'])."\n",
            };
        }

        return $text;
    }

    /** The markup of STYLES (and the formatter's own: error, comment, question, fg=...;bg=...) taken out. */
    public static function plain(string $text): string
    {
        return (string) preg_replace('~</?(?:[a-z][a-z0-9,_=;-]*)?>~i', '', $text);
    }

    /** @param array{kind: string, text: string, newline: bool, verbosity: int, headers?: list<string>, rows?: list<list<string>>} $entry */
    private function add(array $entry): self
    {
        $this->entries[] = $entry;
        if (null !== $this->sink) {
            ($this->sink)($entry);
        }

        return $this;
    }
}
