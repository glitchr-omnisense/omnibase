<?php

namespace Base\Contract;

/**
 * What the application's routes are, beyond making their addresses (Urls
 * does that): the path a route is declared with, the route an address leads
 * to, a route's icons (plan "omnibase agnostique", C4b). Semantic and
 * Widget\Route ask it instead of BaseTrait's router and IconProvider.
 *
 * The Fondation's own contract, reached by Runtime::routes(). Under Symfony,
 * Base\Bridge\Symfony\RouterRoutes: omnibase's advanced_router (its route
 * collection, its matcher) and the IconProvider (the #[Iconize] of a route's
 * controller).
 */
interface Routes
{
    /** The path the route is declared with ("/articles/{slug}"), null for no such route. */
    public function pattern(string $name): ?string;

    /**
     * The name of the route an address leads to (a localized route's with its
     * language, "article.en", when it is not the default one), null when none
     * does.
     */
    public function match(string $url): ?string;

    /**
     * The icons of a route (its controller's #[Iconize], in the page's
     * language when it has several), null for none.
     *
     * @return list<string>|null
     */
    public function icons(string $name): ?array;
}
