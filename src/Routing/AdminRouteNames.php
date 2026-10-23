<?php

namespace Base\Routing;

use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * How omnibase/admin names a CRUD controller's routes, framework-free: the
 * controller's namespace after Crud\ (or its short name), each segment
 * kebab-cased and pluralised, a repeated segment collapsed, joined with "/"
 * (Crud\Article\ArticleCrudController: "articles"; Crud\Article\
 * CommentReplyCrudController: "articles/comment-replies"), and the route
 * "admin_crud_" + that slug with "-" and "/" made "_" + "_" + the action
 * (admin_crud_articles_comments_edit).
 *
 * omnibase/admin's AdminRouteRegistry names its routes by this rule
 * (slugify()); a host without omnibase/admin's Symfony bundle - the Laravel
 * twin of a site whose back office stays Symfony's - names a record's edit
 * page by it too (forEntity(): the controller the admin's convention gives an
 * entity, App\Entity\Article\Comment => ...\Crud\Article\CommentCrudController),
 * through AdminLinks (audit "lesringostars face au master plan", R24).
 */
final class AdminRouteNames
{
    public const ROUTE_PREFIX = 'admin_crud_';

    /** A controller's slug: "articles/comment-replies". */
    public static function slugify(string $controllerFqcn): string
    {
        $name = preg_replace('/CrudController$/', '', $controllerFqcn);
        $pos = strpos($name, 'Crud\\');
        $name = false !== $pos ? substr($name, $pos + 5) : substr($name, (int) strrpos($name, '\\') + 1);

        $segments = [];
        foreach (explode('\\', $name) as $segment) {
            if ('' === $segment) {
                continue;
            }

            $kebab = strtolower(preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $segment));
            $plural = self::pluralize($kebab);

            // collapse "articles/articles" => "articles"
            if ($plural === end($segments)) {
                continue;
            }

            $segments[] = $plural;
        }

        return implode('/', $segments);
    }

    /**
     * The last word of a kebab-cased segment pluralised ("comment-reply" =>
     * "comment-replies"), the qualifier words left alone; EnglishInflector's
     * first guess, the word as it is when it gives none.
     */
    public static function pluralize(string $kebab): string
    {
        $parts = explode('-', $kebab);
        $last = array_pop($parts);

        $candidates = (new EnglishInflector())->pluralize($last);
        $plural = $candidates[0] ?? $last;

        $parts[] = $plural;

        return implode('-', $parts);
    }

    /** A slug's route for an action: admin_crud_articles_comments_edit. */
    public static function route(string $slug, string $action = 'index'): string
    {
        return self::ROUTE_PREFIX . str_replace(['-', '/'], '_', $slug) . '_' . $action;
    }

    /**
     * The route of an entity's page for an action, by the admin's convention
     * (its CRUD controller named after it: the namespace after Entity\, under
     * Crud\); null for a class outside an Entity\ namespace. Doctrine's proxy
     * is its entity.
     */
    public static function forEntity(string $entityFqcn, string $action = 'index'): ?string
    {
        if (false !== ($pos = strrpos($entityFqcn, '\\__CG__\\'))) {
            $entityFqcn = substr($entityFqcn, $pos + 8);
        }
        $pos = strpos($entityFqcn, '\\Entity\\');
        if (false === $pos) {
            return null;
        }

        return self::route(self::slugify('Crud\\' . substr($entityFqcn, $pos + 8) . 'CrudController'), $action);
    }
}
