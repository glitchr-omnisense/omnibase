<?php

namespace Tests\Base\Resources;

use PHPUnit\Framework\TestCase;

/**
 * Every function of Resources/Functions.php is declared only when its name is
 * free (function_exists()), so the file loads beside a framework that defines
 * some of them first - Laravel's head(), last() and class_basename() (its
 * Collections and Support helpers.php, copied below as Laravel 12 writes
 * them), or PHP's own mb_ucfirst(), mb_lcfirst(), array_any(). omnibase's own
 * code then still gets omnibase's results through begin(), array_end() and
 * class_name(); Laravel's code keeps Laravel's. Each order is run in a PHP
 * process of its own: this one has the file loaded already.
 */
final class FunctionsGuardTest extends TestCase
{
    private const LARAVEL = <<<'PHP'
        if (! function_exists('head')) { function head($array) { return empty($array) ? false : $array[array_key_first($array)]; } }
        if (! function_exists('last')) { function last($array) { return empty($array) ? false : $array[array_key_last($array)]; } }
        if (! function_exists('class_basename')) { function class_basename($class) { $class = is_object($class) ? get_class($class) : $class; return basename(str_replace('\\', '/', $class)); } }
        PHP;

    private const CHECKS = <<<'PHP'
        set_error_handler(static function (int $level, string $message): never { throw new \ErrorException($message, 0, $level); });
        $list = ['a', 'b', 'c']; $empty = [];
        echo json_encode([
            'head' => (new ReflectionFunction('head'))->getFileName(),
            'begin([])' => begin($empty),
            'begin' => begin($list),
            'array_end' => array_end($list),
            'class_name' => class_name(['Base\Entity\Thread\Tag', 'App\Entity\User::getId', null]),
            'class_basename(one)' => class_basename('Base\Entity\Thread\Tag'),
            'head([])' => head($empty),
            'last' => last(explode(',', 'x,y,z')),
            'basename_namespace' => basename_namespace('Base\Entity\Thread\Tag'),
            'explode_attributes' => explode_attributes(' ', 'a=1 b=2'),
            'second' => second($list),
            'functions' => count(array_filter(get_defined_functions()['user'], static fn ($f) => (new ReflectionFunction($f))->getFileName() === FUNCTIONS)),
        ]);
        PHP;

    private static function inProcess(string $before, string $after): array
    {
        $functions = realpath(__DIR__.'/../../src/Resources/Functions.php');
        $code = 'const FUNCTIONS = '.var_export($functions, true).';'.$before.' require FUNCTIONS; '.$after.self::CHECKS;
        $process = proc_open([\PHP_BINARY, '-d', 'display_errors=stderr', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, $err.$out);
        $report = json_decode($out, true);
        self::assertIsArray($report, $err.$out);

        return $report;
    }

    public function testLaravelsHelpersFirst(): void
    {
        $report = self::inProcess(self::LARAVEL, '');

        // Laravel's are the ones (declared by the code given to php -r), with Laravel's results...
        self::assertStringNotContainsString('Functions.php', (string) $report['head']);
        self::assertFalse($report['head([])']);
        self::assertSame('z', $report['last']);
        self::assertSame('Tag', $report['class_basename(one)']);
        // ...and omnibase's code still has omnibase's.
        self::assertNull($report['begin([])']);
        self::assertSame('a', $report['begin']);
        self::assertSame('c', $report['array_end']);
        self::assertSame(['Tag', 'User', null], $report['class_name']);
        self::assertSame('Tag', $report['basename_namespace']);
        self::assertSame(['a' => '1', 'b' => '2'], $report['explode_attributes']);
        self::assertSame('b', $report['second']);
        // Every other function of the file is declared.
        self::assertGreaterThan(280, $report['functions']);
    }

    public function testOmnibasesFirst(): void
    {
        // Laravel's helpers.php after: its guards keep omnibase's, and a function's result given to last() raises nothing.
        $report = self::inProcess('', self::LARAVEL);

        self::assertStringEndsWith('Functions.php', (string) $report['head']);
        self::assertNull($report['head([])']);
        self::assertSame('z', $report['last']);
        self::assertSame('Tag', $report['class_basename(one)']);
        self::assertSame(['Tag', 'User', null], $report['class_name']);
        self::assertGreaterThan(290, $report['functions']);
    }

    public function testEveryFunctionIsGuarded(): void
    {
        $tokens = token_get_all((string) file_get_contents(__DIR__.'/../../src/Resources/Functions.php'));
        $depth = 0;
        $unguarded = [];
        foreach ($tokens as $i => $token) {
            $text = \is_array($token) ? $token[1] : $token;
            if ('{' === $text || (\is_array($token) && \in_array($token[0], [\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                ++$depth;
            } elseif ('}' === $text) {
                --$depth;
            } elseif (\is_array($token) && \T_FUNCTION === $token[0] && 1 === $depth) {
                $unguarded[] = $tokens[$i + 2][1] ?? '?';
            }
        }
        // Inside `namespace {`, a function at depth 1 is declared unconditionally.
        self::assertSame([], $unguarded);
    }
}
