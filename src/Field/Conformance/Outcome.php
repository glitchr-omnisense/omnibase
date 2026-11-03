<?php

namespace Base\Field\Conformance;

/** A submission's outcome on a host: the data as bound, the errors by field name ("" for the form's own). */
final readonly class Outcome
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public mixed $data, public array $errors)
    {
    }
}
