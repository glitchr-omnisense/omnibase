<?php

namespace Base\Screen;

/**
 * What a controller or an action hands a screen's result to, on any host: the result of the screen (a View, a
 * Template, a Json, a Shell, a File, a Redirect; null for an empty answer), the legacy variables of a template and
 * the Frame it is shown in, answered as the host's response (Symfony's Response, Laravel's). Each host's bridge
 * implements it and binds it under this name; a controller types this interface, never the bridge's class.
 */
interface Responder
{
    /**
     * @param object                                       $request the host's request
     * @param array<string, mixed>                         $legacy  a template's variables beside the screen's props
     *
     * @return object the host's response
     */
    public function respond(object $request, View|Template|Json|Shell|File|Redirect|null $result, array $legacy = [], ?Frame $frame = null): object;
}
