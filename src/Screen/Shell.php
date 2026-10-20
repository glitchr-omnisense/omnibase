<?php

namespace Base\Screen;

/**
 * A page a front end renders (omnibridge/react's protocol), what an action
 * of a React page hands back (step R10 of the audit "lesringostars face au
 * master plan"): the component, its props, the front end's version, and the
 * shell of the first visit - a template given `root` (the element holding
 * the page), `page` and its own data. A visit within the front end gets the
 * page in JSON, a front end of another version 409 and the address to reload,
 * the first visit the shell, its markup alone.
 */
final readonly class Shell
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $data  the shell's own variables
     */
    public function __construct(
        public string $component,
        public array $props,
        public string $template,
        public array $data = [],
        public ?string $version = null,
    ) {
    }
}
