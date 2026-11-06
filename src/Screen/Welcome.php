<?php

namespace Base\Screen;

/**
 * The page a new site shows at its root until it has one of its own, written once for every host (plan "omnibase
 * agnostic", step F5e, slice 5b; formerly MainController::Index()'s): templates/welcome.html.php, plain PHP (no view
 * engine), told the host framework's version, the project's folder and omnibase's version; answered 404, as a page
 * the site has not written.
 */
final class Welcome
{
    public const STATUS = 404;
    public const TEMPLATE = __DIR__.'/../../templates/welcome.html.php';

    public static function html(string $version, string $projectDir, string $baseVersion): string
    {
        $docVersion = substr($version, 0, 3);
        ob_start();
        try {
            (static function (string $version, string $projectDir, string $docVersion, string $baseVersion): void {
                include Welcome::TEMPLATE;
            })($version, $projectDir, $docVersion, $baseVersion);
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }
}
