<?php

namespace Base\View;

/**
 * A value that changes on every real deploy and stays stable otherwise (plan "omnibase agnostic",
 * step F4: DeployVersionExtension's deploy_version(), the same for Blade and a React page) - meant
 * to be fed into a client-side cache key (@glitchr/transparent's
 * Transparent.ready({cache_version: deploy_version()})) so a browser tab that cached a page before
 * a deploy does not keep replaying it.
 *
 * It reads the application's own git HEAD directly (no shell-out), which is right whether or not
 * the application bumps a version on release: its deploy resets the production directory to a
 * commit, so HEAD changes on a real deploy and never otherwise. It also folds in the git HEADs of
 * vendor/{glitchr,omnisense,omnibase}/*, which are live checkouts iterated on in place on these
 * projects: a fix committed there changes nothing of the application's HEAD, and open tabs kept
 * replaying the HTML of before it. Missing or non-git folders (a normal Composer install) give
 * nothing; with no HEAD at all, the version is "dev".
 */
final class DeployVersion
{
    private ?string $version = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function version(): string
    {
        if (null !== $this->version) {
            return $this->version;
        }

        $heads = [self::readGitHead($this->projectDir)];
        // glitchr/*, omnisense/* (this package and the families' cores) and omnibase/* (its
        // bundles): the vendors whose checkouts are iterated on in place.
        foreach (['glitchr', 'omnisense', 'omnibase'] as $vendor) {
            foreach (glob($this->projectDir.'/vendor/'.$vendor.'/*', GLOB_ONLYDIR) ?: [] as $packageDir) {
                $heads[] = self::readGitHead($packageDir);
            }
        }

        $heads = array_filter($heads, static fn (?string $head): bool => null !== $head);
        if ([] === $heads) {
            return $this->version = 'dev';
        }

        return $this->version = substr(md5(implode('|', $heads)), 0, 8);
    }

    private static function readGitHead(string $dir): ?string
    {
        $headFile = $dir.'/.git/HEAD';
        if (!is_file($headFile)) {
            return null;
        }

        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $refFile = $dir.'/.git/'.substr($head, 5);
            $head = is_file($refFile) ? trim((string) file_get_contents($refFile)) : $head;
        }

        return '' !== $head ? $head : null;
    }
}
