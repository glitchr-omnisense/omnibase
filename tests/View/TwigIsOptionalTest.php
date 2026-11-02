<?php

namespace Tests\Base\View;

use PHPUnit\Framework\TestCase;

/**
 * Twig is optional in the core (plan "omnibase agnostic", step F4; Marco: the core's Twig
 * extensions are there when Twig is used and take nothing away when it is not). The guard:
 *
 * - twig/twig, its extras and omnibridge/twig are suggestions of the core, no longer requirements;
 * - no class outside src/Twig names a class of src/Twig (Base\Twig\...) in its code - its use
 *   lines, its qualified names; comments and strings aside -, save the Symfony host's classes of
 *   the Twig layer listed below, which leave for omnibridge/symfony at step F5c and are registered
 *   with the Twig layer only;
 * - the service files register nothing of src/Twig without Twig: config/services/twig.php returns
 *   first when Twig is not installed, the other files name Base\Twig\ only inside a
 *   `class_exists(\Twig\Environment::class)` block.
 *
 * That the core loads without Twig is BareTest's (testTwigIsOptional(), on the vendor without the
 * framework, where twig/twig is not installed), and that its neutral helpers work there too
 * (testTheViewHelpersWorkWithoutTwig()).
 */
final class TwigIsOptionalTest extends TestCase
{
    /**
     * The Symfony host's classes of the Twig layer outside src/Twig: the kernel subscriber that adds
     * the renderers' tags to a page (its INJECT attribute is read by applications), the warmer of the
     * Webpack renderer's entry points, the form extension that loads a field's Webpack entry. They
     * moved to omnibridge/symfony (the extension at F5b, the other two at F5c); config/services/twig.php
     * registers them by their former names. Empty: the list only shrinks.
     *
     * @var list<string>
     */
    private const TWIG_HOST = [];

    private const PACKAGES = ['twig/twig', 'twig/cache-extra', 'twig/cssinliner-extra', 'twig/inky-extra', 'twig/intl-extra', 'omnibridge/twig'];

    public function testTwigIsSuggestedNotRequired(): void
    {
        $composer = json_decode((string) file_get_contents(self::root().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (self::PACKAGES as $package) {
            self::assertArrayNotHasKey($package, $composer['require'], $package.' is not required');
            self::assertArrayHasKey($package, $composer['suggest'], $package.' is suggested');
        }
    }

    public function testNoClassOutsideSrcTwigNamesTheTwigLayer(): void
    {
        $root = self::root();
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), \strlen($root) + 1);
            if (!str_ends_with($path, '.php') || str_starts_with($path, 'src/Twig/')) {
                continue;
            }
            $names = self::twigLayerNames((string) file_get_contents($file->getPathname()));
            if ($names && !\in_array($path, self::TWIG_HOST, true)) {
                $offenders[] = $path.': '.implode(', ', $names);
            }
        }
        sort($offenders);

        self::assertSame([], $offenders, "a class outside src/Twig depends on the Twig layer:\n".implode("\n", $offenders));
    }

    public function testTheTwigHostListOnlyNamesClassesThatStillNeedIt(): void
    {
        self::assertLessThanOrEqual(2, \count(self::TWIG_HOST), 'the list only shrinks');
        foreach (self::TWIG_HOST as $path) {
            self::assertFileExists(self::root().'/'.$path);
            self::assertNotSame([], self::twigLayerNames((string) file_get_contents(self::root().'/'.$path)), $path.' no longer names the Twig layer: take it out of the list');
        }
    }

    public function testTheServicesRegisterTheTwigLayerOnlyWithTwig(): void
    {
        $twig = (string) file_get_contents(self::root().'/config/services/twig.php');
        $body = substr($twig, (int) strpos($twig, 'return static function'));
        self::assertMatchesRegularExpression('~^return static function[^{]*\{\s*if \(!class_exists\(\\\\Twig\\\\Environment::class\)\) \{\s*return;\s*\}~', $body, 'config/services/twig.php returns first without Twig');

        $files = array_merge(glob(self::root().'/config/services/*.php') ?: [], glob(self::root().'/services*.php') ?: []);
        $outside = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '/config/services/twig.php')) {
                continue;
            }
            $depth = 0;
            $guarded = [];
            foreach (explode("\n", (string) file_get_contents($file)) as $n => $line) {
                if (preg_match('~^\s*if \(class_exists\(\\\\Twig\\\\Environment::class\)\) \{~', $line)) {
                    $guarded[] = $depth;
                }
                $code = preg_replace('~//.*$~', '', $line);
                if (str_contains($code, 'Base\\Twig\\') && !$guarded) {
                    $outside[] = basename($file).':'.($n + 1).': '.trim($line);
                }
                $depth += substr_count($code, '{') - substr_count($code, '}');
                while ($guarded && $depth <= end($guarded)) {
                    array_pop($guarded);
                }
            }
        }

        self::assertSame([], $outside, "a service of the Twig layer is registered without Twig:\n".implode("\n", $outside));
    }

    /**
     * The Base\Twig\ names a file's code uses: its use lines and its qualified names, not its
     * comments nor its strings.
     *
     * @return list<string>
     */
    private static function twigLayerNames(string $code): array
    {
        $names = [];
        $namespace = '';
        $tokens = token_get_all($code);
        foreach ($tokens as $i => $token) {
            if (!\is_array($token)) {
                continue;
            }
            if (T_NAMESPACE === $token[0]) {
                for ($j = $i + 1; isset($tokens[$j]) && ';' !== $tokens[$j] && '{' !== $tokens[$j]; ++$j) {
                    if (\is_array($tokens[$j]) && \in_array($tokens[$j][0], [T_NAME_QUALIFIED, T_STRING], true)) {
                        $namespace = $tokens[$j][1];
                    }
                }
            }
            if (\in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                $name = ltrim($token[1], '\\');
                if (T_NAME_RELATIVE === $token[0]) {
                    $name = $namespace.'\\'.substr($name, \strlen('namespace\\'));
                }
                if (str_starts_with($name, 'Base\\Twig\\')) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
