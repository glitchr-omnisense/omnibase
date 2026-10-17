<?php

namespace Base\Screen;

/**
 * What a screen needs of the request it answers (plan "omnibase agnostique",
 * C5): the frame around the page, the visitor signed in, the messages left for
 * the page. Under Symfony Base\Bridge\Symfony\SymfonyScreenContext; a site
 * decorates it to add its own frame fields; another framework fills it the
 * same.
 *
 * Not in it, on purpose: the forms a page carries (Symfony by nature, D3), the
 * assets and the injections of the layout (the host's layout stays the
 * host's), menus (a site's links go in the frame's extra).
 */
interface ContextProvider
{
    /** The frame of the page being answered; $extra the site's own fields. */
    public function frame(array $extra = []): Frame;

    /** The visitor signed in, null for nobody. */
    public function viewer(): ?Viewer;

    /**
     * The messages left for the next page under that type, read - and so
     * taken - now.
     *
     * @return list<string>
     */
    public function flashes(string $type): array;
}
