<?php

namespace Tests\Base\Form\Definition;

use Base\Field\Kind;
use Base\Form\Definition\AccountForms;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormProcessor;
use Base\Service\FormGuard;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint\Unique;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * What the sites' forms need of a definition (plan "omnibase agnostic", step F5b, group 5): the
 * token's name, a fixed value, computed choices, rewriting what was sent, a value typed twice, the
 * guard's fields and checks, the record's uniqueness, a button that asks first.
 */
class FormGroupFiveTest extends TestCase
{
    public function testTheTokenIsSentUnderTheNameOfOmnibasesForms(): void
    {
        self::assertSame('_csrf_token', FormDefinition::TOKEN_FIELD);
    }

    public function testAFieldsFixedValueIsShownWhateverTheData(): void
    {
        $form = FormDefinition::new('f')->add(FormDefinition::field('all', Kind::CHECKBOX)->setVirtual(true)->setFormTypeOption('data', true));

        self::assertTrue((new FormProcessor())->createView($form)['all']->vars['checked']);
    }

    public function testChoicesMayBeAskedOnlyWhenRead(): void
    {
        $asked = 0;
        $records = [new Record(7, 'Japon'), new Record(9, 'USA')];
        $form = FormDefinition::new('f')->add(FormDefinition::field('places', Kind::ASSOCIATION)->setFormTypeOptions([
            'multiple' => true, 'expanded' => true,
            'choices' => static function () use (&$asked, $records): array {
                ++$asked;

                return $records;
            },
        ]));
        self::assertSame(0, $asked);

        $view = (new FormProcessor())->createView($form);
        self::assertSame([['value' => '7', 'label' => 'Japon'], ['value' => '9', 'label' => 'USA']], $view['places']->vars['choices']);

        $binding = (new FormBinder())->bind($form, ['places' => ['9']]);
        self::assertSame([$records[1]], $binding->data['places']);
        self::assertSame(['places'], array_map(static fn (Violation $violation): string => $violation->path, (new FormBinder())->bind($form, ['places' => ['8']])->violations));
    }

    public function testWhatWasSentIsRewrittenBeforeItIsRead(): void
    {
        $form = FormDefinition::new('f')->add(FormDefinition::field('tags', Kind::CHOICE)->setFormTypeOptions(['multiple' => true, 'choices' => ['a' => 'a', 'b' => 'b']]))
            ->add(FormDefinition::field('all', Kind::CHECKBOX)->setVirtual(true))
            ->addRewrite(static fn (array $sent): array => empty($sent['all']) ? $sent : ['tags' => ['a', 'b']] + $sent);

        self::assertSame(['a', 'b'], (new FormBinder())->bind($form, ['all' => '1'])->data['tags']);
    }

    public function testAValueTypedTwice(): void
    {
        $form = AccountForms::signUp('sign_up')->setDataClass(null);
        $binder = new FormBinder();

        self::assertSame('same', $binder->bind($form, ['plainPassword' => ['first' => 'same', 'second' => 'same']])->data['plainPassword']);
        $refused = $binder->bind($form, ['plainPassword' => ['first' => 'one', 'second' => 'two']]);
        self::assertSame('plainPassword.first', $refused->violations[0]->path);
        self::assertSame('The values do not match.', $refused->violations[0]->message);

        $view = (new FormProcessor())->createView($form)['plainPassword'];
        self::assertSame(['first', 'second'], array_keys($view->children));
        self::assertSame('sign_up[plainPassword][second]', $view['second']->vars['full_name']);
        self::assertSame('password', $view['first']->vars['type']);
        self::assertSame('new-password', $view['first']->vars['attr']['autocomplete']);
    }

    public function testAnActionsConfirmationReachesTheView(): void
    {
        $form = FormDefinition::new('f')->addAction('submit', null, Kind::BUTTON, [], ['confirmation' => true, 'type' => 'submit']);

        $view = (new FormProcessor())->createView($form)['submit'];
        self::assertTrue($view->vars['confirmation']);
        self::assertSame('submit', $view->vars['type']);
        self::assertTrue($view->vars['button']);
    }

    private function guarded(): FormDefinition
    {
        return FormDefinition::new('f')->setGuard(['reputation' => false])->add(FormDefinition::field('email', Kind::EMAIL))->setCsrf(false);
    }

    public function testTheGuardAddsItsFieldsToTheView(): void
    {
        $view = (new FormProcessor(guard: new FormGuard('secret', ['min_delay' => 3])))->createView($this->guarded());

        self::assertTrue(isset($view[FormGuard::TRAP_FIELD], $view[FormGuard::STAMP_FIELD]));
        self::assertSame('hidden', $view[FormGuard::STAMP_FIELD]->vars['type']);
        self::assertSame('base-trap', $view[FormGuard::TRAP_FIELD]->vars['row_attr']['class']);
    }

    public function testTheGuardRefusesATrapFilledAFormTooFastAForgedStamp(): void
    {
        $guard = new FormGuard('secret', ['min_delay' => 3]);
        $processor = new FormProcessor(guard: $guard);
        $reasons = fn (array $sent): array => array_map(static fn (Violation $violation): string => $violation->code, $processor->handle($this->guarded(), $sent)->violations);

        self::assertSame([FormGuard::TRAPPED], $reasons([FormGuard::TRAP_FIELD => 'x', FormGuard::STAMP_FIELD => $guard->stamp(time() - 60)]));
        self::assertSame([FormGuard::TOO_FAST], $reasons([FormGuard::STAMP_FIELD => $guard->stamp()]));
        self::assertSame([FormGuard::STALE], $reasons([FormGuard::STAMP_FIELD => 'forged']));
        self::assertSame([], $reasons([FormGuard::STAMP_FIELD => $guard->stamp(time() - 60)]));
    }

    public function testTheRecordsUniquenessIsCheckedOnTheFormsValues(): void
    {
        $asked = [];
        $validation = new Validator(static function (string $class, array $criteria, ?object $record) use (&$asked): bool {
            $asked[] = [$class, $criteria];

            return 'taken' === reset($criteria);
        });
        $form = FormDefinition::new('f')->setCsrf(false)->setValidationGroups(['new'])->setValidationEntity(UniqueHolder::class, ['username'])
            ->add(FormDefinition::field('email', Kind::EMAIL))
            ->add(FormDefinition::field('username', Kind::TEXT));
        $processor = new FormProcessor($validation);

        self::assertTrue($processor->handle($form, ['email' => 'free', 'username' => 'free'])->isValid());
        self::assertSame([UniqueHolder::class, ['email' => 'free']], $asked[0]);

        $refused = $processor->handle($form, ['email' => 'taken', 'username' => 'taken']);
        self::assertSame(['', 'username'], array_keys($refused->getErrors()));
        self::assertTrue($refused->isTaken('email'));
        self::assertTrue($refused->isTaken('username'));

        self::assertTrue($processor->handle($form, ['email' => 'free', 'username' => 'free'], groups: ['other'])->isValid(), 'not in the groups validated');
    }

    public function testATokenUnderItsName(): void
    {
        $tokens = new class implements Tokens {
            public function csrf(string $intent): string
            {
                return 'made:'.$intent;
            }

            public function isValid(string $intent, ?string $token): bool
            {
                return 'made:'.$intent === $token;
            }
        };
        $form = FormDefinition::new('f')->add(FormDefinition::field('email', Kind::EMAIL));
        $processor = new FormProcessor(tokens: $tokens);

        self::assertSame('f[_csrf_token]', $processor->createView($form)['_csrf_token']->vars['full_name']);
        self::assertTrue($processor->handle($form, ['_csrf_token' => 'made:f'])->isValid());
        self::assertFalse($processor->handle($form, ['_token' => 'made:f'])->isValid());
    }
}

final class Record
{
    public function __construct(private readonly int $id, private readonly string $name)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

#[Unique(fields: ['email'], entityClass: UniqueHolder::class, groups: ['new'])]
#[Unique(fields: ['username'], entityClass: UniqueHolder::class, groups: ['new'])]
final class UniqueHolder
{
}
