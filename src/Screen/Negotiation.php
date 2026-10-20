<?php

namespace Base\Screen;

use Omnibridge\React\Page;
use Omnibridge\React\Protocol;

/**
 * How an action's result is answered, whichever framework hosts it (step R10
 * of the audit "lesringostars face au master plan"; formerly the body of
 * Base\Bridge\Symfony\ScreenResponder, which is now Symfony's adapter of it,
 * as omnibridge/laravel's Screen\Responder is Laravel's). Framework-free: the
 * host gives what the client asked (a Visit) and a way to render a template,
 * and makes its response of the Answer.
 *
 * - A View (a screen of omnibase, plan 0 rule 13) with the frame of the
 *   request: within a front end (omnibridge/react's X-Omnibridge, when it is
 *   installed) the page in JSON - the View's component, the frame's and the
 *   screen's props, the address, the version -, 409 and the address to reload
 *   for a front end of another version; without omnibridge/react, a request
 *   asking JSON alone gets the props; otherwise the template with `frame` and
 *   `screen` (and a site's variables of before, `$legacy`). Every answer varies
 *   on what chose it; one carrying a visitor is private to them and varies on
 *   Cookie; a Confidential screen is stored by no cache at all.
 * - A Template: its markup, or for a client preferring JSON the data, when the
 *   template says it may (both varying on Accept).
 * - Json: the data, its templates rendered, encoded as Symfony's JsonResponse
 *   encodes them.
 * - A Shell: omnibridge/react's protocol - the shell on a first visit, the
 *   page in JSON within the front end, 409 for another version.
 * - A File: the file, as it is stored.
 *
 * A screen's props never take a key of the frame's (Frame::RESERVED): the
 * frame's would win and the screen's be lost without a word, so such a screen
 * is refused, in every answer.
 */
final class Negotiation
{
    /** A template rendered as a page of the site: the host adds what its pages carry. */
    public const PAGE = 'page';

    /** A part of an answer (a value of a JSON answer): in the page's context, nothing added once rendered. */
    public const PART = 'part';

    /** A page whole, its own markup alone (Template::$alone, a Shell). */
    public const ALONE = 'alone';

    /** How Symfony's JsonResponse encodes (its DEFAULT_ENCODING_OPTIONS): the JSON of either host, byte for byte. */
    public const JSON = \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT;

    /**
     * @param \Closure(string $template, array<string, mixed> $context, string $as): string $render
     *                                                                                        the host's Twig; $as one of PAGE, PART, ALONE
     * @param bool|null $omnibridge whether omnibridge/react's protocol is spoken for a View; null: when it is installed
     */
    public function __construct(
        private readonly \Closure $render,
        private readonly ?ContextProvider $context = null,
        private readonly ?string $version = null,
        private readonly ?bool $omnibridge = null,
    ) {
    }

    /** @param array<string, mixed> $legacy a View's variables of before, for a site's own template */
    public function answer(View|Template|Json|Shell|File $result, Visit $visit, array $legacy = [], ?Frame $frame = null): Answer
    {
        return match (true) {
            $result instanceof View => $this->view($result, $visit, $legacy, $frame),
            $result instanceof Template => $this->template($result, $visit),
            $result instanceof Json => new Answer($result->status, self::encode($this->rendered($result->data)), [], $result->cache, json: true),
            $result instanceof Shell => $this->shell($result, $visit),
            $result instanceof File => new Answer(200, '', null !== $result->type ? ['Content-Type' => $result->type] : [], $result->cache, file: $result->path),
        };
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

    /** Data in JSON, as Symfony's JsonResponse writes them. */
    public static function encode(mixed $data): string
    {
        return json_encode($data, self::JSON | \JSON_THROW_ON_ERROR);
    }

    /** Whether omnibridge/react's protocol is spoken. */
    public function omnibridge(): bool
    {
        return $this->omnibridge ?? class_exists(Protocol::class);
    }

    /** @param array<string, mixed> $legacy */
    private function view(View $view, Visit $visit, array $legacy, ?Frame $frame): Answer
    {
        self::guard($view->screen);
        $frame ??= ($this->context ?? throw new \LogicException('A View is answered with the frame of the request: give Negotiation a ContextProvider.'))->frame();

        if ($this->omnibridge() && Protocol::wantsJson($visit->frontEnd)) {
            $page = new Page($view->component ?? $view->template, $this->props($frame, $view), $visit->uri, $this->version);
            $answer = Protocol::answer($page, $visit->frontEnd, $visit->version, static fn (string $root): string => $root);

            return new Answer(200 === $answer['status'] ? $view->status : $answer['status'], $answer['body'], $answer['headers'], $this->private(new Cache(), $frame, $view->screen));
        }

        if (!$this->omnibridge() && $visit->asksJsonAlone()) {
            // Not a JsonResponse: the props as the screen gave them (Symfony's FlashBagSubscriber folds the
            // session's messages into a JsonResponse, and takes them).
            $body = json_encode($this->props($frame, $view), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

            return new Answer($view->status, $body, ['Content-Type' => 'application/json'], $this->private(new Cache(vary: ['Accept']), $frame, $view->screen));
        }

        $body = ($this->render)($view->template, ['frame' => $frame, 'screen' => $view->screen] + $legacy, self::PAGE);
        $cache = new Cache(vary: [$this->omnibridge() ? Protocol::HEADER : 'Accept']);

        return new Answer($view->status, $body, [], $view->screen instanceof Confidential ? $this->private($cache, $frame, $view->screen) : $cache);
    }

    private function template(Template $template, Visit $visit): Answer
    {
        if ($template->json && $visit->prefersJson()) {
            return new Answer($template->status, self::encode($this->rendered($template->data)), [], $template->cache->with(['Accept']), json: true);
        }
        $body = ($this->render)($template->template, $template->data, $template->alone ? self::ALONE : self::PAGE);

        return new Answer($template->status, $body, [], $template->json ? $template->cache->with(['Accept']) : $template->cache);
    }

    private function shell(Shell $shell, Visit $visit): Answer
    {
        if (!class_exists(Protocol::class)) {
            throw new \LogicException('A Shell is answered by omnibridge/react\'s protocol: require omnibridge/react.');
        }
        $page = new Page($shell->component, $shell->props, $visit->uri, $shell->version);
        $answer = Protocol::answer($page, $visit->frontEnd, $visit->version,
            fn (string $root): string => ($this->render)($shell->template, ['root' => $root, 'page' => $page] + $shell->data, self::ALONE));

        return new Answer($answer['status'], $answer['body'], $answer['headers']);
    }

    /** The data with their templates rendered (Template: its markup; Fragments: theirs, joined), at any depth. */
    private function rendered(mixed $data): mixed
    {
        if ($data instanceof Template) {
            return ($this->render)($data->template, $data->data, self::PART);
        }
        if ($data instanceof Fragments) {
            return implode('', array_map(fn (Template $template): string => $this->rendered($template), $data->templates));
        }
        if (\is_array($data)) {
            return array_map($this->rendered(...), $data);
        }

        return $data;
    }

    /** A JSON answer carrying a visitor is private to them; a Confidential screen is stored by no cache. */
    private function private(Cache $cache, Frame $frame, Screen $screen): Cache
    {
        $private = null !== $frame->viewer || $screen instanceof Confidential;

        return $private ? $cache->with(['Cookie'], private: true, noStore: $screen instanceof Confidential) : $cache;
    }
}
