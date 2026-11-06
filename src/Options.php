<?php

namespace Base;

use Omnibridge\Options\Processor;

/**
 * The core's options as data (plan "omnibase agnostic", step F5e slice 4b): the schema of `base:` - each
 * option's type, default, what it is for, its deprecation, its nesting - and the environment variables a service
 * reads that need a value where the environment has none. One declaration, read by every host, so that no option
 * is declared twice:
 *
 * - omnibridge/symfony compiles the schema into BaseConfiguration's TreeBuilder (config:dump-reference base,
 *   debug:config base, the container's base.* parameters) and sets each env(NAME) parameter;
 * - omnibridge/laravel validates the application's config/base.php with process() and takes the defaults it
 *   leaves out from it, its placeholders resolved by the application (%kernel.project_dir%: its base path);
 * - a plain-PHP host gives Omnibridge\Container parameters(): every base.* parameter, as Symfony's extension sets
 *   them, and environment()'s defaults.
 *
 * The schema is src/Resources/options.php, in Omnibridge\Options\Processor's node format; Processor and the
 * Symfony tree answer alike (the same values, the same refusals: tests/Options/OptionsHostsTest).
 */
final class Options
{
    /** The root of the options: `base:`, the parameters `base.*`. */
    public const ROOT = 'base';

    /** @var array{options: array<string, array<string, mixed>>, environment: array<string, array{group: string, default: string}>}|null */
    private static ?array $declaration = null;

    /**
     * The options' schema: the root's children, in Omnibridge\Options\Processor's node format.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schema(): array
    {
        return self::declaration()['options'];
    }

    /**
     * The environment variables the services read through %env()% that need a value where the environment has none
     * (Symfony's env(NAME) parameters), by name; a group's only (Base\Services::GROUPS) when one is given.
     *
     * @return array<string, string>
     */
    public static function environment(?string $group = null): array
    {
        $defaults = [];
        foreach (self::declaration()['environment'] as $name => $variable) {
            if (null === $group || $group === $variable['group']) {
                $defaults[$name] = $variable['default'];
            }
        }

        return $defaults;
    }

    /**
     * Configurations of `base:` merged in their order, checked against the schema and completed with its defaults,
     * the placeholders of the defaults left for the host ('%kernel.project_dir%', '%env(APP_ENV)%').
     *
     * @param array<array-key, mixed>|null ...$configs
     *
     * @return array<string, mixed>
     *
     * @throws \Omnibridge\Options\InvalidOption an option the schema refuses
     */
    public static function process(?array ...$configs): array
    {
        return Processor::process(self::schema(), self::ROOT, array_values($configs));
    }

    /**
     * What a configuration that says nothing gives.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return self::process();
    }

    /**
     * The base.* parameters, as Symfony's extension sets them from the processed configuration: each option by its
     * full name ('base.user.identifier'), an array also whole ('base.user') and item by item ('base.demo.admin_roles.0').
     *
     * @param array<array-key, mixed>|null ...$configs
     *
     * @return array<string, mixed>
     */
    public static function parameters(?array ...$configs): array
    {
        $parameters = [];
        $flatten = static function (array $values, string $prefix) use (&$flatten, &$parameters): void {
            foreach ($values as $key => $value) {
                $parameters[$prefix.'.'.$key] = $value;
                if (\is_array($value)) {
                    $flatten($value, $prefix.'.'.$key);
                }
            }
        };
        $flatten(self::process(...$configs), self::ROOT);

        return $parameters;
    }

    /**
     * environment()'s defaults as the parameters a container reads them by: 'env(NAME)' => default.
     *
     * @return array<string, string>
     */
    public static function environmentParameters(): array
    {
        $parameters = [];
        foreach (self::environment() as $name => $default) {
            $parameters['env('.$name.')'] = $default;
        }

        return $parameters;
    }

    /** @return array{options: array<string, array<string, mixed>>, environment: array<string, array{group: string, default: string}>} */
    private static function declaration(): array
    {
        return self::$declaration ??= require __DIR__.'/Resources/options.php';
    }
}
