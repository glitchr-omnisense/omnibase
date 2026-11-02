<?php

namespace Base\Form\Definition;

use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A form's processing, neutral: bind what was sent (FormBinder), check the
 * token (Omnibridge\Contract\Tokens), validate (Omnibridge\Contract\Validation:
 * the data against what its class declares, each field's value against the
 * field's constraints), then keep the data (Omnibridge\Persistence\UnitOfWork)
 * when asked and valid. Each piece is optional: without Validation nothing is
 * validated but what the binder could not read, without Tokens no token is
 * made nor checked, without a unit of work nothing is kept.
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
    ) {
        $this->binder = $binder ?? new FormBinder($unitOfWork);
    }

    /**
     * The form's view before any submission: the data's values, a token.
     */
    public function createView(FormDefinition $form, mixed $data = null): FormView
    {
        return FormView::of($form, $this->binder->read($form, $data), [], $this->token($form));
    }

    /**
     * Handles a request's part for the form: null (or nothing under the
     * form's name) is a form not submitted.
     *
     * @param array<string, mixed>|null $submitted the values under the form's name, files merged in
     * @param list<string>|null         $groups    validation groups, the host's default when null
     */
    public function handle(FormDefinition $form, ?array $submitted, mixed $data = null, bool $persist = false, ?array $groups = null): FormSubmission
    {
        $token = $this->token($form);
        if (null === $submitted) {
            return new FormSubmission($form, false, $data, $this->binder->read($form, $data), [], null, false, $token);
        }

        $violations = [];
        if ($form->hasCsrf() && null !== $this->tokens) {
            $sent = $submitted[FormDefinition::TOKEN_FIELD] ?? null;
            if (!$this->tokens->isValid($form->getIntent(), \is_string($sent) ? $sent : null)) {
                $violations[] = new Violation('', self::CSRF_MESSAGE, 'csrf');
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
        array_push($violations, ...$binding->violations);
        if (null !== $this->validation) {
            array_push($violations, ...$this->validate($form, $binding, $groups));
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
                if (!isset($unread[$field])) {
                    $violations[] = $violation;
                }
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
                $path = '' === $violation->path ? $name : $name.'.'.$violation->path;
                $violations[] = new Violation($path, $violation->message, $violation->code, $violation->parameters);
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
