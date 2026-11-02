<?php

namespace Tests\Base\Form\Definition;

use Base\Field\Kind;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormProcessor;
use Omnibridge\Validation\Constraint\Count;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * A rule of the form's data on a field that gives its errors to the form (Symfony's error_bubbling)
 * is the form's error, as a rule of the field's own (plan "omnibase agnostic", step F5c: a newsletter
 * with no destination ticked said nothing, its model's Count standing under a list the page prints
 * no error of).
 */
class ErrorBubblingTest extends TestCase
{
    public function testADataRuleOnABubblingFieldIsTheForms(): void
    {
        $form = static fn (bool $bubbling): FormDefinition => FormDefinition::new('f')->setCsrf(false)->setDataClass(Choices::class)
            ->add(FormDefinition::field('tags', Kind::CHOICE)->setFormTypeOptions(['multiple' => true, 'choices' => ['a' => 'a'], 'error_bubbling' => $bubbling]));
        $processor = new FormProcessor(new Validator());

        self::assertSame(['tags' => ['One at least.']], $processor->handle($form(false), [])->getErrors());
        self::assertSame(['' => ['One at least.']], $processor->handle($form(true), [])->getErrors());
        self::assertTrue($processor->handle($form(true), ['tags' => ['a']])->isValid());
    }
}

final class Choices
{
    /** @var list<string> */
    #[Count(min: 1, minMessage: 'One at least.')]
    public array $tags = [];
}
