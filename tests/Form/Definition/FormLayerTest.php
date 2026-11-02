<?php

namespace Tests\Base\Form\Definition;

use Base\Field\BooleanField;
use Base\Field\DateField;
use Base\Field\EmailField;
use Base\Field\IntegerField;
use Base\Field\Kind;
use Base\Field\PasswordField;
use Base\Field\SelectField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormProcessor;
use Base\Form\Definition\FormView;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * The neutral form layer (plan "omnibase agnostic", step F5b): a definition,
 * its binding, its view, its processing, with no form library.
 */
class FormLayerTest extends TestCase
{
    private function comment(): FormDefinition
    {
        return FormDefinition::new('comment')
            ->add(TextField::new('author', 'comment.author')->setRequired(true))
            ->add(EmailField::new('email'))
            ->add(TextareaField::new('content')->addConstraint(new NotEmpty()))
            ->add(BooleanField::new('notify')->setRequired(false))
            ->addAction('submit', 'comment.submit');
    }

    public function testADefinitionKeepsItsFieldsGroupsAndActions(): void
    {
        $form = $this->comment()->add(IntegerField::new('rating'), 'details')->addGroup('details', 'comment.details');

        $this->assertSame(['author', 'email', 'content', 'notify', 'rating'], array_keys($form->getFields()));
        $this->assertSame(Kind::TEXTAREA, $form->get('content')->getKind());
        $this->assertSame(['details' => ['label' => 'comment.details', 'fields' => ['rating']]], $form->getGroups());
        $this->assertSame(['submit'], array_keys($form->getActions()));
        $this->assertSame('comment', $form->getIntent());
        $this->assertSame('POST', $form->getMethod());
    }

    public function testFromFieldsKeepsThoseOfThePage(): void
    {
        $form = FormDefinition::fromFields('crud', [TextField::new('title'), TextField::new('slug')->hideOnForm()], 'edit');

        $this->assertSame(['title'], array_keys($form->getFields()));
    }

    public function testBindingAnArray(): void
    {
        $binding = (new FormBinder())->bind($this->comment(), ['author' => '  Ringo ', 'email' => '', 'content' => 'Hello', 'notify' => '1']);

        $this->assertSame(['author' => 'Ringo', 'email' => null, 'content' => 'Hello', 'notify' => true], $binding->data);
        $this->assertSame([], $binding->violations);
    }

    public function testBindingAnObjectWritesOnlyWhatChanges(): void
    {
        $data = new CommentData();
        $data->author = 'Ringo';
        $binding = (new FormBinder())->bind($this->comment(), ['author' => 'Ringo', 'content' => 'Hi']);

        $this->assertSame(['author' => 'Ringo', 'email' => null, 'content' => 'Hi', 'notify' => false], $binding->data);

        $binding = (new FormBinder())->bind($this->comment(), ['author' => 'Ringo', 'content' => 'Hi'], $data);
        $this->assertSame($data, $binding->data);
        $this->assertSame('Hi', $data->content);
        $this->assertFalse($data->notify, 'a missing checkbox is false');
        $this->assertSame(1, $data->contentWrites);
    }

    public function testValuesThatCannotBeReadAreViolationsWithSymfonysMessages(): void
    {
        $form = FormDefinition::new('event')
            ->add(IntegerField::new('seats'))
            ->add(DateField::new('day'))
            ->add(SelectField::new('room')->setFormTypeOption('choices', ['Small' => 's', 'Large' => 'l']));

        $binding = (new FormBinder())->bind($form, ['seats' => '12a', 'day' => '2026-02-31', 'room' => 'x']);
        $messages = array_map(static fn (Violation $v): string => $v->path.': '.$v->message, $binding->violations);

        $this->assertSame([
            'seats: Please enter an integer.',
            'day: Please enter a valid date.',
            'room: The selected choice is invalid.',
        ], $messages);
        $this->assertSame('12a', $binding->values['seats'], 'shown again as sent');

        $binding = (new FormBinder())->bind($form, ['seats' => '12', 'day' => '2026-02-03', 'room' => 'l']);
        $this->assertSame(12, $binding->data['seats']);
        $this->assertSame('2026-02-03', $binding->data['day']->format('Y-m-d'));
        $this->assertSame('l', $binding->data['room']);
    }

    public function testAPasswordIsNotTrimmedAndADisabledFieldKeepsItsValue(): void
    {
        $form = FormDefinition::new('account')
            ->add(PasswordField::new('password'))
            ->add(TextField::new('author')->setDisabled());
        $data = new CommentData();
        $data->author = 'Ringo';

        (new FormBinder())->bind($form, ['password' => ' secret ', 'author' => 'Paul'], $data);

        $this->assertSame(' secret ', $data->password);
        $this->assertSame('Ringo', $data->author);
    }

    public function testATransformerIsAPlainCallable(): void
    {
        $form = FormDefinition::new('tags')->add(TextField::new('tags')->setCustomOption(FormBinder::OPTION_REVERSE, static fn (?string $raw): array => array_map('trim', explode(',', (string) $raw))));

        $this->assertSame(['tags' => ['a', 'b']], (new FormBinder())->bind($form, ['tags' => 'a, b'])->data);

        $binder = (new FormBinder())->withTransformer(Kind::TEXT, static fn (?string $raw): ?string => null === $raw ? null : strtoupper($raw));
        $this->assertSame('RINGO', $binder->bind($this->comment(), ['author' => 'ringo'])->data['author']);
    }

    public function testTheViewIsAPlainTreeNamedAsSymfonysVars(): void
    {
        $form = $this->comment()->add(SelectField::new('room')->setFormTypeOption('choices', ['Small' => 's', 'Large' => 'l']));
        $view = FormView::of($form, ['author' => 'Ringo', 'notify' => true, 'room' => 'l'], ['content' => ['Empty.'], '' => ['Token.']], 'tok');

        $this->assertSame('comment', $view->vars['full_name']);
        $this->assertSame(['Token.'], $view->vars['errors']);
        $this->assertFalse($view->vars['valid']);
        $this->assertSame('comment[author]', $view['author']->vars['full_name']);
        $this->assertSame('comment_author', $view['author']->vars['id']);
        $this->assertSame('comment.author', $view['author']->vars['label']);
        $this->assertTrue($view['author']->vars['required']);
        $this->assertSame('Ringo', $view['author']->vars['value']);
        $this->assertSame('email', $view['email']->vars['type']);
        $this->assertSame('textarea', $view['content']->vars['type']);
        $this->assertSame(['Empty.'], $view['content']->vars['errors']);
        $this->assertSame('checkbox', $view['notify']->vars['type']);
        $this->assertTrue($view['notify']->vars['checked']);
        $this->assertSame([['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']], $view['room']->vars['choices']);
        $this->assertTrue($view['room']->isSelected('l'));
        $this->assertSame('submit', $view['submit']->vars['type']);
        $this->assertSame('tok', $view['_csrf_token']->vars['value']);
        $this->assertSame('comment[_csrf_token]', $view['_csrf_token']->vars['full_name']);

        $props = json_decode(json_encode($view), true);
        $this->assertSame('comment', $props['name']);
        $this->assertSame(['author', 'email', 'content', 'notify', 'room', 'submit', '_csrf_token'], array_column($props['children'], 'name'));
    }

    public function testProcessingBindsChecksTheTokenValidatesAndKeeps(): void
    {
        $tokens = new class implements Tokens {
            public function csrf(string $intent): string
            {
                return 'token-'.$intent;
            }

            public function isValid(string $intent, ?string $token): bool
            {
                return 'token-'.$intent === $token;
            }
        };
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())->method('persist');
        $unitOfWork->expects($this->once())->method('flush');
        $processor = new FormProcessor(new NotEmptyValidation(), $tokens, $unitOfWork);

        $this->assertFalse($processor->handle($this->comment(), null, new CommentData())->isSubmitted());
        $this->assertSame('token-comment', $processor->createView($this->comment())['_csrf_token']->vars['value']);

        $refused = $processor->handle($this->comment(), ['author' => 'Ringo', 'content' => '', '_csrf_token' => 'forged'], new CommentData(), persist: true);
        $this->assertFalse($refused->isValid());
        $this->assertSame(['' => [FormProcessor::CSRF_MESSAGE], 'content' => ['This value should not be blank.']], $refused->getErrors());
        $this->assertSame(['This value should not be blank.'], $refused->createView()['content']->vars['errors']);
        $this->assertFalse($refused->persisted);

        $kept = $processor->handle($this->comment(), ['author' => 'Ringo', 'content' => 'Hi', '_csrf_token' => 'token-comment', 'submit' => ''], new CommentData(), persist: true);
        $this->assertTrue($kept->isValid());
        $this->assertTrue($kept->persisted);
        $this->assertSame('submit', $kept->getClicked());
    }
}

/** @internal */
final class CommentData
{
    public ?string $author = null;
    public ?string $email = null;
    public ?string $password = null;
    public bool $notify = false;
    public int $contentWrites = 0;
    private ?string $content = null;

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        ++$this->contentWrites;
        $this->content = $content;
    }

    public function __get(string $name): mixed
    {
        return 'content' === $name ? $this->content : null;
    }
}

/** @internal */
final class NotEmpty
{
}

/** @internal */
final class NotEmptyValidation implements Validation
{
    public function validate(mixed $value, array $constraints = [], ?array $groups = null): array
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof NotEmpty && (null === $value || '' === $value)) {
                return [new Violation('', 'This value should not be blank.', 'blank')];
            }
        }

        return [];
    }
}
