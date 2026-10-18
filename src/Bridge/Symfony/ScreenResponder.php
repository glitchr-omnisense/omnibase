<?php

namespace Base\Bridge\Symfony;

use Base\Screen\Confidential;
use Base\Screen\ContextProvider;
use Base\Screen\Frame;
use Base\Screen\Screen;
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
 * another. A screen carrying its viewer's secrets (Base\Screen\Confidential:
 * its forms' tokens) is stored by no cache at all: `private, no-store` and
 * `Vary: Cookie`, the HTML as the JSON.
 *
 * A screen's props never take a key of the frame's (Frame::RESERVED: locale,
 * site, links, viewer, theme): the frame's would win and the screen's be lost
 * without a word, so such a screen is refused, in every answer.
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
        self::guard($view->screen);
        $frame ??= $this->context->frame();

        if ($this->omnibridge() && Protocol::wantsJson($request->headers->get(Protocol::HEADER))) {
            $page = new Page($view->component ?? $view->template, $this->props($frame, $view), $request->getRequestUri(), $this->version);
            $answer = Protocol::answer($page, $request->headers->get(Protocol::HEADER), $request->headers->get(Protocol::VERSION), static fn (string $root): string => $root);
            $response = new Response($answer['body'], 200 === $answer['status'] ? $view->status : $answer['status'], $answer['headers']);

            return $this->private($response, $frame, $view->screen);
        }

        if (!$this->omnibridge() && self::asksJsonAlone($request)) {
            // A Response, not a JsonResponse: the props as the screen gave them (FlashBagSubscriber folds the
            // session's messages into a JsonResponse, and takes them).
            $response = new Response(json_encode($this->props($frame, $view), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), $view->status, ['Content-Type' => 'application/json']);
            $response->setVary(['Accept'], false);

            return $this->private($response, $frame, $view->screen);
        }

        $response = new Response($this->twig->render($view->template, ['frame' => $frame, 'screen' => $view->screen] + $legacy), $view->status);
        $response->setVary([$this->omnibridge() ? Protocol::HEADER : 'Accept'], false);

        return $view->screen instanceof Confidential ? $this->private($response, $frame, $view->screen) : $response;
    }

    /**
     * The variables a page had before its view model, given only to a template
     * a site overrides (its templates/ folder, templates/bundles/...): one
     * read from $own (the core's templates by default) reads its screen alone,
     * and the variables - their queries - are not made at all.
     *
     * @param \Closure(): array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public function legacy(string $template, \Closure $variables, ?string $own = null): array
    {
        $own ??= \dirname(__DIR__, 3).'/templates';
        $own = realpath($own) ?: $own;
        $path = (string) $this->twig->getLoader()->getSourceContext($template)->getPath();

        return str_starts_with(realpath($path) ?: $path, rtrim($own, '/').'/') ? [] : $variables();
    }

    /** @return array<string, mixed> the frame's props and the screen's, as a front end reads them */
    public function props(Frame $frame, View $view): array
    {
        self::guard($view->screen);

        return $frame->toArray() + $view->screen->toArray();
    }

    /** A screen whose props would take a key of the frame's is refused: its own would be lost in the JSON. */
    public static function guard(Screen $screen): void
    {
        if ($taken = array_intersect(array_keys($screen->toArray()), Frame::RESERVED)) {
            throw new \InvalidArgumentException(sprintf('A screen\'s props may not take the frame\'s keys: "%s" (%s).', implode('", "', $taken), $screen::class));
        }
    }

    private function omnibridge(): bool
    {
        return $this->omnibridge ?? class_exists(Protocol::class);
    }

    private function private(Response $response, Frame $frame, Screen $screen): Response
    {
        if (null !== $frame->viewer || $screen instanceof Confidential) {
            $response->setPrivate();
            $response->setVary(['Cookie'], false);
        }
        if ($screen instanceof Confidential) {
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }

    private static function asksJsonAlone(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }
}
