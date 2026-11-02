<?php

namespace Tests\Base\Form\Definition;

use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormProcessor;
use Omnibridge\Contract\Translations;
use Omnibridge\Validation\Constraint\Unique;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * What lesringostars' account forms needed of the neutral layer to be served by another host than
 * Symfony (plan "omnibase agnostic", step F5c): an empty string given as empty data, a record's
 * uniqueness declared without its class, the binder's refusals in the page's language, a refusal
 * added once the form holds.
 */
class FormHostsTest extends TestCase
{
    public function testAnEmptyStringGivenAsEmptyDataStaysAString(): void
    {
        $form = FormDefinition::new('f')
            ->add(FormDefinition::field('username', Kind::TEXT)->setEmptyData(''))
            ->add(FormDefinition::field('nickname', Kind::TEXT));
        $binder = new FormBinder();

        // Symfony's TextType keeps it so; a host that sends an empty field as null (Laravel) gets it too.
        self::assertSame('', $binder->bind($form, ['username' => ''])->data['username']);
        self::assertSame('', $binder->bind($form, ['username' => null])->data['username']);
        self::assertNull($binder->bind($form, ['nickname' => ''])->data['nickname']);
    }

    public function testAUniquenessDeclaredWithoutItsClassIsLookedUpInTheFormsRecordClass(): void
    {
        $asked = [];
        $validation = new Validator(static function (string $class, array $criteria) use (&$asked): bool {
            $asked[] = $class;

            return 'taken' === reset($criteria);
        });
        $form = FormDefinition::new('f')->setCsrf(false)->setValidationEntity(ClasslessUnique::class)
            ->add(FormDefinition::field('email', Kind::EMAIL));
        $processor = new FormProcessor($validation);

        self::assertTrue($processor->handle($form, ['email' => 'free'])->isValid());
        self::assertSame([ClasslessUnique::class], $asked);
        self::assertTrue($processor->handle($form, ['email' => 'taken'])->isTaken('email'));
    }

    public function testWhatTheBinderCouldNotReadIsSaidInThePagesLanguage(): void
    {
        $translations = new class implements Translations {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return 'validators' === $domain && 'The values do not match.' === $id ? 'Les valeurs ne correspondent pas.' : $id;
            }

            public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
            {
                return true;
            }
        };
        $form = FormDefinition::new('f')->setCsrf(false)->add(FormDefinition::field('plainPassword', Kind::REPEATED)->setFormTypeOptions(['type' => Kind::PASSWORD]));

        $refused = (new FormProcessor(translations: $translations))->handle($form, ['plainPassword' => ['first' => 'one', 'second' => 'two']]);
        self::assertSame(['Les valeurs ne correspondent pas.'], $refused->getErrors()['plainPassword']);
    }

    public function testARefusalAddedOnceTheFormHolds(): void
    {
        $form = FormDefinition::new('f')->setCsrf(false)->add(FormDefinition::field('content', Kind::TEXTAREA));
        $held = (new FormProcessor())->handle($form, ['content' => 'Hello']);
        self::assertTrue($held->isValid());

        $refused = $held->withError('Spam.');
        self::assertFalse($refused->isValid());
        self::assertTrue($held->isValid(), 'a new submission, the first one as it was');
        self::assertSame(['' => ['Spam.']], $refused->getErrors());
        self::assertSame(['Spam.'], $refused->createView()->vars['errors']);
        self::assertSame(['content' => ['Too short.']], $held->withError('Too short.', 'content')->getErrors());
    }
}

#[Unique(fields: ['email'])]
final class ClasslessUnique
{
}
