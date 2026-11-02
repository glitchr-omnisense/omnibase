<?php

namespace Base\Form\Definition;

use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\AttributeConstraints;
use Omnibridge\Validation\Constraint\Unique;

/**
 * A record a valid form has just written into, held to its class's rules before it is kept - its
 * uniqueness above all: a profile's new name that another account has (plan "omnibase agnostic",
 * step F5c: the profile's editor answered 500 on the database's refusal, and signed the session
 * out). The host's Validation says what it reads (Symfony's validator reads omnibase's
 * UniqueEntity itself); a class-level uniqueness it said nothing about is asked as omnisense/
 * omnibridge's Unique, the record itself not counted as taken.
 */
final class RecordRules
{
    private function __construct()
    {
    }

    /**
     * @param list<string>|null $groups
     *
     * @return list<Violation>
     */
    public static function violations(Validation $validation, object $record, ?array $groups = null): array
    {
        FormProcessor::readers();
        $violations = $validation->validate($record, [], $groups);
        $said = [];
        foreach ($violations as $violation) {
            $said[$violation->path] = true;
        }

        foreach (AttributeConstraints::ofClass($record::class) as $constraint) {
            if (!$constraint instanceof Unique || !$constraint->appliesTo($groups)) {
                continue;
            }
            if (isset($said[$constraint->errorPath ?? $constraint->fields[0] ?? ''])) {
                continue;
            }
            foreach ($validation->validate($record, [$constraint], $groups) as $violation) {
                if (Unique::CODE === $violation->code) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }
}
