<?php

namespace Base\Field\Conformance;

use Base\Field\FieldDescriptor;

/**
 * One kind as the conformance suite tries it: a field of that kind named
 * `value`, the data it starts from, what the page shows of it, what a
 * visitor sends (under the field's name, as that host's markup sends it),
 * the value the data then holds, and a value the field refuses.
 *
 * $drawn: the markup names the field (an input, a select, a textarea whose
 * name is the form's name and the field's, or starts with it); $shown, when
 * given, is found in it as a value. A sample with $bound = self::RENDER_ONLY
 * is drawn and not submitted: what it holds needs a host's records or files.
 * A sample with a $dataClass is drawn over a new record of that class (the
 * core's own entities, on every host) under the property $name: the kinds
 * whose host reads the record's mapping (an upload, an embedded form).
 * A sample not $exercised is declared only: the core has no record its kind
 * holds (an attribute's adapter); an application's suite tries it on its own.
 */
final readonly class Sample
{
    public const RENDER_ONLY = "\0render-only";

    public const NONE = "\0none";

    /**
     * @param \Closure(): FieldDescriptor $field
     */
    public function __construct(
        public string $kind,
        public \Closure $field,
        public mixed $data = null,
        public mixed $shown = self::NONE,
        public mixed $submitted = null,
        public mixed $bound = self::RENDER_ONLY,
        public mixed $invalid = self::NONE,
        public bool $drawn = true,
        public ?string $dataClass = null,
        public string $name = Suite::FIELD,
        public bool $exercised = true,
    ) {
    }

    public function field(): FieldDescriptor
    {
        return ($this->field)();
    }

    public function submits(): bool
    {
        return self::RENDER_ONLY !== $this->bound;
    }
}
