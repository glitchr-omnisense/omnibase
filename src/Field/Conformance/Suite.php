<?php

namespace Base\Field\Conformance;

use Base\Field\Kinds;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Form\Coverage;

/**
 * The field conformance suite (plan "omnibase agnostic", step F5d), the same
 * on every host: a host's coverage declares every kind (Kinds::faults()),
 * and each kind it implements draws its field, shows the data's value, binds
 * what is sent to the same value, refuses what it cannot read. A kind it
 * declares unsupported is refused aloud when drawn (a host's exception),
 * never a plain input.
 *
 * A host's test runs it over Samples::all() (one case per kind):
 *
 *     foreach (Samples::all() as $kind => $sample) {
 *         self::assertSame([], Suite::check($host, $sample), $kind);
 *     }
 *
 * The samples' expected values are Symfony's, the host whose types the
 * behaviours were taken from.
 */
final class Suite
{
    public const FORM = 'conformance';

    public const FIELD = 'value';

    private function __construct()
    {
    }

    public static function definition(Sample $sample): FormDefinition
    {
        $field = $sample->field();
        $field->setProperty($sample->name);
        $form = FormDefinition::new(self::FORM)->setCsrf(false)->add($field);

        return null !== $sample->dataClass ? $form->setDataClass($sample->dataClass) : $form;
    }

    /** The data a sample starts from: a new record of its class, or the form's array. */
    public static function data(Sample $sample): mixed
    {
        return null !== $sample->dataClass ? new ($sample->dataClass)() : [$sample->name => $sample->data];
    }

    /** @return list<string> what is wrong with the host's coverage */
    public static function coverage(Host $host): array
    {
        return Kinds::faults($host->name());
    }

    /** @return list<string> what the host does wrong with this kind, [] when it conforms */
    public static function check(Host $host, Sample $sample): array
    {
        $declaration = Kinds::declaration($sample->kind, $host->name());
        if (null === $declaration) {
            return [sprintf('%s does not declare the kind "%s".', $host->name(), $sample->kind)];
        }

        if (Coverage::isUnsupported($declaration)) {
            try {
                $host->render(self::definition($sample), null);
            } catch (\Throwable) {
                return [];
            }

            return [sprintf('%s declares "%s" unsupported (%s) but draws it: an unsupported kind is refused aloud.', $host->name(), $sample->kind, Coverage::reason($declaration))];
        }
        if (!$sample->exercised) {
            return [];
        }
        $data = self::data($sample);
        $name = $sample->name;

        $faults = [];
        try {
            $html = $host->render(self::definition($sample), $data);
        } catch (\Throwable $e) {
            return [sprintf('"%s" is not drawn: %s: %s', $sample->kind, $e::class, $e->getMessage())];
        }
        if ($sample->drawn && !preg_match('/\sname="'.preg_quote(self::FORM.'['.$name.']', '/').'(?:\[[^"]*\])?"/', $html)) {
            $faults[] = sprintf('"%s": no input named %s[%s] in %s', $sample->kind, self::FORM, $name, self::excerpt($html));
        }
        if (Sample::NONE !== $sample->shown) {
            foreach ((array) $sample->shown as $shown) {
                if (!str_contains($html, htmlspecialchars((string) $shown, \ENT_QUOTES))) {
                    $faults[] = sprintf('"%s": the value %s is not shown in %s', $sample->kind, json_encode($shown, \JSON_UNESCAPED_UNICODE), self::excerpt($html));
                }
            }
        }

        if ($sample->submits()) {
            try {
                $outcome = $host->submit(self::definition($sample), [$name => $sample->submitted], $data);
                if ([] !== array_filter($outcome->errors)) {
                    $faults[] = sprintf('"%s": %s refused: %s', $sample->kind, json_encode($sample->submitted, \JSON_UNESCAPED_UNICODE), json_encode($outcome->errors, \JSON_UNESCAPED_UNICODE));
                }
                $bound = self::normal(\is_array($outcome->data) ? ($outcome->data[$name] ?? null) : null);
                if ($bound !== self::normal($sample->bound)) {
                    $faults[] = sprintf('"%s": %s is bound as %s, not %s', $sample->kind, json_encode($sample->submitted, \JSON_UNESCAPED_UNICODE), json_encode($bound, \JSON_UNESCAPED_UNICODE), json_encode(self::normal($sample->bound), \JSON_UNESCAPED_UNICODE));
                }
            } catch (\Throwable $e) {
                $faults[] = sprintf('"%s": the submission fails: %s: %s', $sample->kind, $e::class, $e->getMessage());
            }
        }
        if (Sample::NONE !== $sample->invalid) {
            try {
                $outcome = $host->submit(self::definition($sample), [$name => $sample->invalid], self::data($sample));
                if ([] === array_filter($outcome->errors)) {
                    $faults[] = sprintf('"%s": %s is accepted', $sample->kind, json_encode($sample->invalid, \JSON_UNESCAPED_UNICODE));
                }
            } catch (\Throwable $e) {
                $faults[] = sprintf('"%s": the invalid submission fails instead of being refused: %s: %s', $sample->kind, $e::class, $e->getMessage());
            }
        }

        return $faults;
    }

    /** A bound value as the suite compares it: a date written out, an enum's value, a record's id, a float as it reads. */
    public static function normal(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            is_iterable($value) => array_map(self::normal(...), \is_array($value) ? $value : iterator_to_array($value, false)),
            \is_object($value) && method_exists($value, 'getId') => $value->getId(),
            \is_float($value) && floor($value) == $value && abs($value) < 1e15 => (int) $value,
            default => $value,
        };
    }

    private static function excerpt(string $html): string
    {
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;

        return \strlen($html) > 600 ? substr($html, 0, 600).'...' : $html;
    }
}
