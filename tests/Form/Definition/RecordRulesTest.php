<?php

namespace Tests\Base\Form\Definition;

use Base\Form\Definition\RecordRules;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint\Unique;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * A record a valid form has written into, held to its class's rules before it is kept (plan
 * "omnibase agnostic", step F5c: a profile's name another account has answered 500 and signed the
 * session out): its uniqueness asked with the record itself not counted as taken, at the field.
 */
class RecordRulesTest extends TestCase
{
    public function testANameAnotherRecordHasIsRefusedUnderItsField(): void
    {
        $other = new Profile('Alice');
        $records = [$other];
        $validation = new Validator(static function (string $class, array $criteria, ?object $record) use (&$records): bool {
            foreach ($records as $found) {
                if ($found !== $record && $found->username === $criteria['username']) {
                    return true;
                }
            }

            return false;
        });
        $mine = new Profile('sign.up');
        $records[] = $mine;

        self::assertSame([], RecordRules::violations($validation, $mine, ['edit']), 'its own name is not taken');

        $mine->username = 'Alice';
        $violations = RecordRules::violations($validation, $mine, ['edit']);
        self::assertCount(1, $violations);
        self::assertSame('username', $violations[0]->path);
        self::assertSame(Unique::CODE, $violations[0]->code);

        self::assertSame([], RecordRules::violations($validation, $mine, ['other']), 'not in the groups validated');
    }

    public function testWhatTheHostAlreadySaidIsNotAskedAgain(): void
    {
        $asked = 0;
        $validation = new class($asked) implements Validation {
            public function __construct(private int &$asked)
            {
            }

            public function validate(mixed $value, array $constraints = [], ?array $groups = null): array
            {
                ++$this->asked;

                // A host whose validator reads the record's uniqueness itself (Symfony's, omnibase's UniqueEntity).
                return [] === $constraints ? [new Violation('username', '"Alice" is already used.')] : [];
            }
        };

        $violations = RecordRules::violations($validation, new Profile('Alice'), ['edit']);
        self::assertSame(['"Alice" is already used.'], array_map(static fn (Violation $violation): string => $violation->message, $violations));
        self::assertSame(1, $asked);
    }
}

#[Unique(fields: ['username'], groups: ['new', 'edit'])]
final class Profile
{
    public function __construct(public string $username)
    {
    }
}
