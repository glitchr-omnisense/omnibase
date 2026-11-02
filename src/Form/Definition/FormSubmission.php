<?php

namespace Base\Form\Definition;

use Omnibridge\Contract\Violation;

/**
 * A form handled by FormProcessor: whether it was submitted, the data with
 * what was sent written into it, the violations (the binder's, the
 * validator's, the token's), the button clicked, whether the data was kept.
 */
final class FormSubmission
{
    /**
     * @param array<string, mixed> $values     the values the form shows again, by field name
     * @param list<Violation>      $violations
     */
    public function __construct(
        public readonly FormDefinition $form,
        public readonly bool $submitted,
        public readonly mixed $data,
        public readonly array $values = [],
        public readonly array $violations = [],
        public readonly ?string $clicked = null,
        public readonly bool $persisted = false,
        public readonly ?string $token = null,
    ) {
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function isValid(): bool
    {
        return $this->submitted && [] === $this->violations;
    }

    /** The action (FormDefinition::addAction()) whose button sent the form, null for none named. */
    public function getClicked(): ?string
    {
        return $this->clicked;
    }

    /**
     * The messages by field name: a violation's path is its field's name (the
     * first segment of a nested path, "" for the form's own when no field has
     * that name).
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->violations as $violation) {
            $field = preg_split('/[.\[]/', $violation->path, 2)[0] ?? '';
            $field = $this->form->has($field) ? $field : '';
            $errors[$field][] = $violation->message;
        }

        return $errors;
    }

    /** The view to render again: the values sent, their errors, a fresh token. */
    public function createView(?string $token = null): FormView
    {
        return FormView::of($this->form, $this->values, $this->getErrors(), $token ?? $this->token, $this->submitted);
    }
}
