<?php

namespace Base\Screen;

/**
 * Templates rendered one after the other into one string, a value of a JSON
 * answer (step R10 of the audit "lesringostars face au master plan"): a batch
 * of comments, the HTML a page's script inserts.
 */
final readonly class Fragments
{
    /** @var list<Template> */
    public array $templates;

    public function __construct(Template ...$templates)
    {
        $this->templates = array_values($templates);
    }
}
