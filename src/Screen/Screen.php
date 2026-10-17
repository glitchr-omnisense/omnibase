<?php

namespace Base\Screen;

/**
 * A view model (plan 0, rule 13; plan "omnibase agnostique", C5): what a
 * screen shows, as plain data a view bridge renders - Twig reads the object's
 * public properties, a front end (React) reads toArray() as its props. No
 * entity, no service, no class of a framework inside: scalars, ISO 8601
 * moments, lists and other screens.
 */
interface Screen extends \JsonSerializable
{
    /** @return array<string, mixed> the props, nested arrays all the way down */
    public function toArray(): array;
}
