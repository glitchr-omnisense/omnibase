<?php

namespace Base\Bridge\Symfony;

use Base\Screen\ContextProvider;
use Base\Screen\Frame;
use Base\Screen\View;
use Omnibridge\React\Page;
use Omnibridge\React\Protocol;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A screen answered under Symfony (plan "omnibase agnostique", C5): what a
 * controller hands back - a template, a view model (Base\Screen\View) - and
 * the frame of the request (the ContextProvider's).
 *
 * - A visit within a front end (omnibridge/react's protocol: the request
 *   header X-Omnibridge, when omnibridge/react is installed): the page in
 *   JSON - the View's component, the frame's and the screen's props
 *   together, the address, the front end's version (base.screens.version) -;
 *   a front end of another version gets 409 and the address to reload.
 * - Without omnibridge/react, a request asking JSON alone (Accept:
 *   application/json, no text/html): the props in JSON. React does not rely
 *   on it.
 * - Otherwise the HTML: Twig renders the View's template with `frame` and
 *   `screen` (and, for the templates a site overrides, the variables the page
 *   had before - `$legacy`, deprecated, gone in 4.0).
 *
 * Every answer varies on what chooses between them (X-Omnibridge, Accept); a
 * JSON answer carrying a visitor (the frame's viewer) is private to them and
 * varies on the session's cookie: no cache serves one person's name to
 * another.
 */
class ScreenResponder
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ContextProvider $context,
        private readonly ?string $version = null,
        /** Whether omnibridge/react's protocol is spoken; null: when omnibridge/react is installed. */
        private readonly ?bool $omnibridge = null,
    ) {
    }

    /** @param array<string, mixed> $legacy the variables of before, for a site's own template */
    public function respond(Request $request, View $view, array $legacy = [], ?Frame $frame = null): Response
    {
        $frame ??= $this->context->frame();

        if ($this->omnibridge() && Protocol::wantsJson($request->headers->get(Protocol::HEADER))) {
            $page = new Page($view->component ?? $view->template, $this->props($frame, $view), $request->getRequestUri(), $this->version);
            $answer = Protocol::answer($page, $request->headers->get(Protocol::HEADER), $request->headers->get(Protocol::VERSION), static fn (string $root): string => $root);
            $response = new Response($answer['body'], 200 === $answer['status'] ? $view->status : $answer['status'], $answer['headers']);

            return $this->private($response, $frame);
        }

        if (!$this->omnibridge() && self::asksJsonAlone($request)) {
            // A Response, not a JsonResponse: the props as the screen gave them (FlashBagSubscriber folds the
            // session's messages into a JsonResponse, and takes them).
            $response = new Response(json_encode($this->props($frame, $view), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), $view->status, ['Content-Type' => 'application/json']);
            $response->setVary(['Accept'], false);

            return $this->private($response, $frame);
        }

        $response = new Response($this->twig->render($view->template, ['frame' => $frame, 'screen' => $view->screen] + $legacy), $view->status);
        $response->setVary([$this->omnibridge() ? Protocol::HEADER : 'Accept'], false);

        return $response;
    }

    /** @return array<string, mixed> the frame's props and the screen's, as a front end reads them */
    public function props(Frame $frame, View $view): array
    {
        return $frame->toArray() + $view->screen->toArray();
    }

    private function omnibridge(): bool
    {
        return $this->omnibridge ?? class_exists(Protocol::class);
    }

    private function private(Response $response, Frame $frame): Response
    {
        if (null !== $frame->viewer) {
            $response->setPrivate();
            $response->setVary(['Cookie'], false);
        }

        return $response;
    }

    private static function asksJsonAlone(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }
}
