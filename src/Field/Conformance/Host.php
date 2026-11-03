<?php

namespace Base\Field\Conformance;

use Base\Form\Definition\FormDefinition;

/**
 * A host as the field conformance suite drives it (Suite): its name (the
 * one Base\Field\Kinds knows its coverage by), a definition drawn as the
 * host draws it for a page, a submission bound and validated as the host
 * processes it.
 */
interface Host
{
    /** symfony, laravel: the host's name in Base\Field\Kinds. */
    public function name(): string;

    /** The form's markup, as a page of this host prints it. */
    public function render(FormDefinition $form, mixed $data): string;

    /**
     * What is submitted under the form's name, bound to the data and
     * validated.
     *
     * @param array<string, mixed> $submitted
     */
    public function submit(FormDefinition $form, array $submitted, mixed $data): Outcome;
}
