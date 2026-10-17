<?php

namespace Base\Screen;

/**
 * What a controller hands back (plan 0, rule 13): the template that draws the
 * screen, the screen itself, the name of the component a front end draws it
 * with (omnibridge/react's Page), and the status of the answer. The view
 * bridge renders it: Twig with `frame` and `screen`, React with the frame's
 * and the screen's props together.
 */
final readonly class View
{
    public function __construct(
        public string $template,
        public Screen $screen,
        public ?string $component = null,
        public int $status = 200,
    ) {
    }
}
