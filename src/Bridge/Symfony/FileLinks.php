<?php

namespace Base\Bridge\Symfony;

use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The links of the error pages and the profiler open the file on the
 * developer's machine when the site runs in a container: APP_PATH (the
 * project's path on the host, which a site's Makefile writes in .env.local)
 * against kernel.project_dir (its path in the container).
 *
 * Symfony's own means, no patch: FileLinkFormatter reads a mapping after the
 * format, "vscode://file/%f:%l&/srv/app/>/Users/me/site/" (framework.ide,
 * SYMFONY_IDE). FileLinkFormatter is @final, so omnibase no longer extends it
 * (Base\Inspector\FileLinkFormatter, gone in 4.0) nor patches the error
 * handler to use a renderer of its own (Base\Inspector\HtmlErrorRenderer and
 * glitchr/base-plugin's hooks on symfony/error-handler, gone too):
 *
 * - debug.file_link_formatter is decorated (config/services/debug.php) with
 *   Symfony's FileLinkFormatter on the mapped format - the error pages
 *   (error_handler.error_renderer.html, which receives it) and the profiler
 *   read it;
 * - SYMFONY_IDE is exported when the bundle is constructed, before the
 *   container is built: the renderer Symfony's ErrorHandler builds itself,
 *   for an error that happens before the kernel handles a request (a
 *   container that does not compile), reads it.
 *
 * A format that maps already (it has "&...>") or a site with no APP_PATH is
 * left as it is. With no format at all, VS Code's.
 */
final class FileLinks
{
    public const DEFAULT_FORMAT = 'vscode://file/%f:%l';

    /**
     * The link format with the container-to-host mapping, or $ide unchanged
     * (null for none) when there is nothing to map.
     */
    public static function format(?string $ide, string $projectDir, ?string $hostDir): ?string
    {
        $ide = null === $ide || '' === $ide ? null : $ide;
        $hostDir = null === $hostDir ? '' : rtrim($hostDir, '/');
        $projectDir = rtrim($projectDir, '/');

        if ('' === $hostDir || '' === $projectDir || $hostDir === $projectDir) {
            return $ide;
        }

        $format = ErrorRendererInterface::IDE_LINK_FORMATS[$ide ?? ''] ?? $ide ?? self::DEFAULT_FORMAT;
        if (preg_match('/&[^>]+>/', $format)) {
            return $ide;
        }

        return $format.'&'.$projectDir.'/>'.$hostDir.'/';
    }

    /**
     * The decoration of debug.file_link_formatter: Symfony's formatter on the
     * mapped format (with the profiler's address for a file without a format
     * of its own), or the decorated one when there is nothing to map.
     */
    public static function formatter(?FileLinkFormatter $inner, ?string $ide, string $projectDir, ?string $hostDir, ?RequestStack $requestStack = null): ?FileLinkFormatter
    {
        $format = self::format($ide, $projectDir, $hostDir);
        if (null === $format || $format === $ide) {
            return $inner;
        }

        return new FileLinkFormatter($format, $requestStack, $projectDir, '/_profiler/open?file=%f&line=%l#line%l');
    }

    /**
     * SYMFONY_IDE set to the mapped format when a site has APP_PATH and no
     * SYMFONY_IDE of its own, for the renderers built outside the container.
     */
    public static function export(?string $projectDir = null): void
    {
        $existing = $_SERVER['SYMFONY_IDE'] ?? $_ENV['SYMFONY_IDE'] ?? null;
        if (null !== $existing && '' !== $existing) {
            return;
        }

        $projectDir ??= self::projectDir();
        $hostDir = $_SERVER['APP_PATH'] ?? $_ENV['APP_PATH'] ?? null;
        if (null === $projectDir || !is_string($hostDir)) {
            return;
        }

        $format = self::format(null, $projectDir, $hostDir);
        if (null !== $format) {
            $_SERVER['SYMFONY_IDE'] = $_ENV['SYMFONY_IDE'] = $format;
        }
    }

    /** The application's directory, from Composer's runtime (the root package). */
    private static function projectDir(): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }

        $path = \Composer\InstalledVersions::getRootPackage()['install_path'] ?? null;

        return is_string($path) ? (realpath($path) ?: null) : null;
    }
}
