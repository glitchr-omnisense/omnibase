<?php

namespace Base\Form\Definition;

use Base\Field\Kind;
use Base\Service\FormGuard;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Validation\AttributeConstraints;
use Omnibridge\Validation\Constraint\NotBlank;
use Omnibridge\Validation\Constraint\Unique;

/**
 * A form's processing, neutral: bind what was sent (FormBinder), check the
 * token (Omnibridge\Contract\Tokens), validate (Omnibridge\Contract\Validation:
 * the data against what its class declares, each field's value against the
 * field's constraints), then keep the data (Omnibridge\Persistence\UnitOfWork)
 * when asked and valid. Each piece is optional: without Validation nothing is
 * validated but what the binder could not read, without Tokens no token is
 * made nor checked, without a unit of work nothing is kept. A form that asks
 * for it (FormDefinition::setGuard()) goes through Base\Service\FormGuard when
 * one is given: its trap and stamp are added to the view, what they say comes
 * first; $translations says the guard's and the token's refusals.
 *
 *     $submission = $processor->handle($form, $request->request->all($form->getName()), $comment, persist: true);
 *     if ($submission->isValid()) { ... redirect ... }
 *     return render('comment.html.twig', ['form' => $submission->createView()]);
 *
 * The Symfony form of a definition keeps Symfony's own processing
 * (omnibridge/symfony); this one is the other hosts' (Laravel, plain PHP).
 */
final class FormProcessor
{
    /** Symfony's message for a missing or wrong token (its csrf_message). */
    public const CSRF_MESSAGE = 'The CSRF token is invalid. Please try to resubmit the form.';

    private readonly FormBinder $binder;

    public function __construct(
        private readonly ?Validation $validation = null,
        private readonly ?Tokens $tokens = null,
        private readonly ?UnitOfWork $unitOfWork = null,
        ?FormBinder $binder = null,
        private readonly ?FormGuard $guard = null,
        private readonly ?Translations $translations = null,
    ) {
        $this->binder = $binder ?? new FormBinder($unitOfWork);
        self::readers();
    }

    /** omnibase's own rules, read as the kept constraints wherever a class declares them. */
    public static function readers(): void
    {
        AttributeConstraints::register('Base\Validator\Constraints\UniqueEntity', static fn (array $arguments): Unique => AttributeConstraints::unique($arguments, self::uniqueMessage($arguments)));
        // UniqueEntity without Doctrine (step E1): Symfony's UniqueEntity's arguments and default message.
        AttributeConstraints::register('Base\Validator\Constraints\Unique', static fn (array $arguments): Unique => AttributeConstraints::unique($arguments));
        AttributeConstraints::register('Base\Validator\Constraints\NotBlank', AttributeConstraints::keep(NotBlank::class, ['message', 'allowNull', 'groups']));
    }

    /** @param array<int|string, mixed> $arguments */
    private static function uniqueMessage(array $arguments): string
    {
        return '@validators.unique_entity';
    }

    /**
     * The form's view before any submission: the data's values, a token.
     */
    public function createView(FormDefinition $form, mixed $data = null): FormView
    {
        $form = $this->guarded($form);

        return FormView::of($form, $this->binder->read($form, $data), [], $this->token($form), false, $data);
    }

    /**
     * Handles a request's part for the form: null (or nothing under the
     * form's name) is a form not submitted.
     *
     * @param array<string, mixed>|null $submitted the values under the form's name, files merged in
     * @param list<string>|null         $groups    validation groups, the form's when null
     * @param string|null               $address   the visitor's address, for the guard's lists and counts
     */
    public function handle(FormDefinition $form, ?array $submitted, mixed $data = null, bool $persist = false, ?array $groups = null, ?string $address = null): FormSubmission
    {
        $form = $this->guarded($form);
        $groups ??= $form->getValidationGroups();
        $token = $this->token($form);
        if (null === $submitted) {
            return new FormSubmission($form, false, $data, $this->binder->read($form, $data), [], null, false, $token);
        }

        $violations = [];
        if ($form->hasCsrf() && null !== $this->tokens) {
            $sent = $submitted[FormDefinition::TOKEN_FIELD] ?? null;
            if (!$this->tokens->isValid($form->getIntent(), \is_string($sent) ? $sent : null)) {
                $violations[] = new Violation('', $this->translations?->trans(self::CSRF_MESSAGE, [], 'validators') ?? self::CSRF_MESSAGE, 'csrf');
            }
        }

        $clicked = null;
        foreach (array_keys($form->getActions()) as $action) {
            if (\array_key_exists($action, $submitted)) {
                $clicked = $action;
                break;
            }
        }

        $binding = $this->binder->bind($form, $submitted, $data);
        // What the binder could not read says it in the page's language, as a Symfony form's invalid_message
        // (the validators domain: "The values do not match." for a value typed twice).
        foreach ($binding->violations as $violation) {
            $violations[] = null !== $this->translations ? new Violation($violation->path, $this->translations->trans($violation->message, $violation->parameters, 'validators'), $violation->code, $violation->parameters) : $violation;
        }
        array_push($violations, ...$this->inspect($form, $submitted, $binding, $address));
        if (null !== $this->validation) {
            array_push($violations, ...$this->validate($form, $binding, $groups));
            array_push($violations, ...$this->entity($form, $binding, $groups));
        }
        if (false !== $form->getGuard() && null !== $this->guard) {
            [] === $violations ? $this->guard->succeeded($form->getName(), $address) : $this->guard->failed($form->getName(), $address);
        }

        $persisted = false;
        if ($persist && [] === $violations && \is_object($binding->data) && null !== $this->unitOfWork) {
            $this->unitOfWork->persist($binding->data);
            $this->unitOfWork->flush();
            $persisted = true;
        }

        return new FormSubmission($form, true, $binding->data, $binding->values, $violations, $clicked, $persisted, $token);
    }

    /**
     * The validator's violations: the data's own (an object against what its
     * class declares), then each field's constraints on the field's value;
     * none for a field the binder could not read (its own violation stands).
     *
     * @param list<string>|null $groups
     *
     * @return list<Violation>
     */
    private function validate(FormDefinition $form, Binding $binding, ?array $groups): array
    {
        $unread = [];
        foreach ($binding->violations as $violation) {
            $unread[$violation->path] = true;
        }

        $violations = [];
        if (\is_object($binding->data)) {
            foreach ($this->validation->validate($binding->data, [], $groups) as $violation) {
                $field = preg_split('/[.\[]/', $violation->path, 2)[0] ?? '';
                if (isset($unread[$field])) {
                    continue;
                }
                // The data's rule on a field that gives its errors to the form (Symfony's error_bubbling).
                $violations[] = $form->has($field) && true === $form->get($field)->getOption('error_bubbling') ? new Violation('', $violation->message, $violation->code, $violation->parameters) : $violation;
            }
        }

        foreach ($form->getFields() as $name => $field) {
            $constraints = $field->getConstraints();
            if ([] === $constraints || isset($unread[$name]) || true === $field->isDisabled()) {
                continue;
            }
            $value = \is_array($binding->data) || true === $field->isVirtual() || false === $field->getOption('mapped')
                ? (\is_array($binding->data) ? ($binding->data[$name] ?? $field->getValue()) : $field->getValue())
                : $this->read($binding->data, $field->getOption('property_path') ?? $name);
            foreach ($this->validation->validate($value, $constraints, $groups) as $violation) {
                // A field that gives its errors to the form (Symfony's error_bubbling).
                $path = true === $field->getOption('error_bubbling') ? '' : ('' === $violation->path ? $name : $name.'.'.$violation->path);
                $violations[] = new Violation($path, $violation->message, $violation->code, $violation->parameters);
            }
        }

        return $violations;
    }

    /** The form with what its guard adds: the trap, and the stamp of the time it is shown. */
    private function guarded(FormDefinition $form): FormDefinition
    {
        $guard = $form->getGuard();
        if (false === $guard || null === $this->guard) {
            return $form;
        }

        $form = clone $form;
        if ($guard['trap'] ?? true) {
            $form->add(FormDefinition::field(FormGuard::TRAP_FIELD, Kind::TEXT)->setVirtual(true)->setRequired(false)->setFormTypeOptions([
                'label' => false, 'attr' => ['tabindex' => '-1', 'autocomplete' => 'off'],
                'row_attr' => ['class' => 'base-trap', 'aria-hidden' => 'true', 'style' => 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden'],
            ]));
        }

        return $form->add(FormDefinition::field(FormGuard::STAMP_FIELD, Kind::HIDDEN)->setVirtual(true)->setFormTypeOption('data', $this->guard->stamp()));
    }

    /**
     * What the guard says of what was sent, before the validation.
     *
     * @param array<string, mixed> $submitted
     *
     * @return list<Violation>
     */
    private function inspect(FormDefinition $form, array $submitted, Binding $binding, ?string $address): array
    {
        $guard = $form->getGuard();
        if (false === $guard || null === $this->guard) {
            return [];
        }
        $values = [];
        foreach ([FormGuard::TRAP_FIELD, FormGuard::STAMP_FIELD] as $field) {
            if ($form->has($field)) {
                $values[$field] = \is_string($submitted[$field] ?? null) ? $submitted[$field] : '';
            }
        }
        $emailField = (string) ($guard['email'] ?? 'email');
        $found = $this->guard->inspectValues($values, $address, $guard['min_delay'] ?? null, $this->text($binding, $emailField), $this->text($binding, (string) ($guard['name'] ?? 'name')), (bool) ($guard['reputation'] ?? true));
        if (null === $found) {
            return [];
        }
        $key = '@forms.guard.'.$found[0];
        $message = $this->translations?->trans($key);

        return [new Violation(FormGuard::DISPOSABLE === $found[0] && $form->has($emailField) ? $emailField : '', null !== $message && '' !== $message ? $message : $key, $found[0])];
    }

    private function text(Binding $binding, string $field): ?string
    {
        $value = \is_array($binding->data) ? ($binding->data[$field] ?? null) : $this->read($binding->data, $field);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * The class-level rules of the record the form makes (its uniqueness), on
     * the values the form holds: Symfony's option validation_entity. Their
     * refusal is the form's own, unless the definition puts it under its field.
     *
     * @param list<string>|null $groups
     *
     * @return list<Violation>
     */
    private function entity(FormDefinition $form, Binding $binding, ?array $groups): array
    {
        $class = $form->getValidationEntity();
        if (null === $class) {
            return [];
        }
        $groups ??= [\Omnibridge\Validation\Constraint\Constraint::DEFAULT_GROUP, (new \ReflectionClass($class))->getShortName()];

        $violations = [];
        foreach (AttributeConstraints::ofClass($class) as $constraint) {
            // A record's uniqueness looked up among the records of its class: the form's values are no record.
            if ($constraint instanceof Unique && null === $constraint->entityClass) {
                $constraint = new Unique($constraint->fields, $class, $constraint->errorPath, $constraint->message, $constraint->groups);
            }
            $values = [];
            foreach ($constraint->fields ?? [] as $field) {
                $values[$field] = \is_array($binding->data) ? ($binding->data[$field] ?? null) : $this->read($binding->data, $field);
            }
            foreach ($this->validation->validate($values, [$constraint], $groups) as $violation) {
                $field = \in_array($violation->path, $form->getEntityErrorFields(), true) ? $violation->path : '';
                $violations[] = new Violation($field, $violation->message, $violation->code, ['{{ field }}' => $violation->path] + $violation->parameters);
            }
        }

        return $violations;
    }

    private function read(mixed $data, string $path): mixed
    {
        try {
            return \is_object($data) ? Properties::get($data, $path) : null;
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    private function token(FormDefinition $form): ?string
    {
        return $form->hasCsrf() && null !== $this->tokens ? $this->tokens->csrf($form->getIntent()) : null;
    }
}
