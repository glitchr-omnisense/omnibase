<?php

namespace Tests\Base\Service\Model;

use Omnibridge\Contract\Environment;
use Base\Service\Model\DevelopmentEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * BaseService::isDevelopment()'s rule over the Environment contract (plan
 * "omnibase agnostique", C9): debug on, or an environment named dev, local,
 * dev_* or local_*.
 */
final class DevelopmentEnvironmentTest extends TestCase
{
    /** @return iterable<string, array{string, bool, bool}> */
    public static function environments(): iterable
    {
        yield 'dev' => ['dev', false, true];
        yield 'dev_*' => ['dev_demo', false, true];
        yield 'local' => ['local', false, true];
        yield 'local_*' => ['local_beta', false, true];
        yield 'prod' => ['prod', false, false];
        yield 'prod in debug' => ['prod', true, true];
        yield 'test' => ['test', false, false];
        yield 'a name that only starts like dev' => ['devops', false, false];
    }

    /** @dataProvider environments */
    #[\PHPUnit\Framework\Attributes\DataProvider('environments')]
    public function testTheRule(string $name, bool $debug, bool $development): void
    {
        $environment = new class($name, $debug) implements Environment {
            public function __construct(private string $name, private bool $debug)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function isDebug(): bool
            {
                return $this->debug;
            }

            public function secret(): string
            {
                return '';
            }
        };

        self::assertSame($development, DevelopmentEnvironment::is($environment));
        self::assertSame($development, DevelopmentEnvironment::named($name, $debug));
    }
}
