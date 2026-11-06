<?php

namespace Tests\Base\Options;

use Base\Options;
use Omnibridge\Options\InvalidOption;
use Omnibridge\Symfony\Omnibase\DependencyInjection\BaseConfiguration;
use Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\BaseConfiguration as FormerConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\Exception as ConfigException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The options are one declaration (Base\Options, plan "omnibase agnostic", step F5e slice 4b), read by two
 * validators: Symfony's Config tree that omnibridge/symfony compiles from it (BaseConfiguration), and
 * Omnibridge\Options\Processor, which omnibridge/laravel validates config/base.php with and a plain-PHP host its
 * configuration. Each configuration of tests/Options/cases.php gives the same result on both - the same values,
 * merged and completed alike, or the same refusal, word for word - and the same deprecations.
 */
final class OptionsHostsTest extends TestCase
{
    /** @return iterable<string, array{list<array<array-key, mixed>|null>}> */
    public static function configurations(): iterable
    {
        foreach (require __DIR__.'/cases.php' as $name => $configs) {
            yield $name => [$configs];
        }
    }

    /**
     * @param list<array<array-key, mixed>|null> $configs
     *
     * @dataProvider configurations
     */
    #[DataProvider('configurations')]
    public function testSymfonyAndLaravelValidateAlike(array $configs): void
    {
        if (!class_exists(BaseConfiguration::class)) {
            self::markTestSkipped('Requires omnibridge/symfony.');
        }
        $symfony = self::outcome(static fn (): array => (new Processor())->processConfiguration(new BaseConfiguration(), $configs));
        $neutral = self::outcome(static fn (): array => Options::process(...$configs));

        self::assertSame($symfony, $neutral);
    }

    public function testTheCasesRefuseWhatTheyShould(): void
    {
        $refused = 0;
        $past = false;
        foreach (require __DIR__.'/cases.php' as $name => $configs) {
            $past = $past || 'unknown root' === $name;
            $result = self::outcome(static fn (): array => Options::process(...$configs));
            self::assertSame($past, isset($result['refused']), $name);
            $refused += (int) $past;
        }
        self::assertGreaterThan(20, $refused);
    }

    /** The tree compiled from the declaration is the one omnibridge/symfony wrote by hand until slice 4b. */
    public function testTheDeclarationIsTheFormerTree(): void
    {
        if (!class_exists(FormerConfiguration::class)) {
            self::markTestSkipped('Requires omnibridge/symfony.');
        }
        $dumper = new YamlReferenceDumper();

        self::assertSame($dumper->dump(new FormerConfiguration()), $dumper->dump(new BaseConfiguration()));
    }

    /**
     * The result of a run: the configuration, or the refusal's message; the deprecations raised either way.
     *
     * @return array{config?: array<string, mixed>, refused?: string, deprecations: list<string>}
     */
    private static function outcome(\Closure $process): array
    {
        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $result = ['config' => $process()];
        } catch (InvalidOption|ConfigException $e) {
            $result = ['refused' => $e->getMessage()];
        } finally {
            restore_error_handler();
        }

        return $result + ['deprecations' => $deprecations];
    }
}
