<?php

namespace Base\Form\Definition;

use Omnibridge\Contract\Violation;

/**
 * What FormBinder::bind() gives: the data with the submitted values written,
 * the values the form shows again, and the violations of the fields whose
 * value could not be read.
 */
final class Binding
{
    /**
     * @param array<string, mixed> $values     by field name
     * @param list<Violation>      $violations
     */
    public function __construct(
        public readonly mixed $data,
        public readonly array $values,
        public readonly array $violations = [],
    ) {
    }
}
