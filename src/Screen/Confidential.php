<?php

namespace Base\Screen;

/**
 * A screen carrying secrets of its viewer's session (plan "omnibase
 * agnostique", C5): the tokens of its forms (Base\Screen\Account\Action), the
 * token of its requests. Whoever answers it keeps it out of every cache -
 * under Symfony, ScreenResponder gives `Cache-Control: private, no-store` and
 * `Vary: Cookie`, the HTML as the JSON.
 */
interface Confidential extends Screen
{
}
