<?php

namespace Base\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A value that changes on every real deploy and stays stable otherwise -
 * meant to be fed into a client-side cache key (e.g. @glitchr/transparent's
 * Transparent.ready({cache_version: deploy_version()})) so a browser tab
 * that cached a page before a deploy doesn't keep replaying it forever.
 *
 * Reads the app's own git HEAD directly (no shell-out) rather than a
 * Composer/env-var version string, since that's correct regardless of
 * whether the app bumps any version number on release - this app's own
 * deploy model resets the production directory to a specific commit via
 * `git fetch && git reset --hard`, so HEAD always changes on a real
 * deploy and never otherwise.
 *
 * ALSO folds in vendor/glitchr/*'s own git HEADs, not just the app's -
 * on this project, those aren't standard immutable Composer installs,
 * they're live git checkouts iterated on directly (see e.g. the
 * base-bundle-admin/base-bundle dev workflow), so a change committed
 * there changes nothing about the APP's own HEAD at all. Without this,
 * every vendor-level fix left every already-open admin tab replaying
 * the pre-fix HTML/CSS/JS indefinitely - reported live, repeatedly, as
 * "no handler" for a control that had genuinely already shipped.
 * Missing/non-git vendor dirs (a normal production Composer install,
 * source-archive not git checkout) just contribute nothing, same as
 * the app dir falling back to 'dev' when it isn't a git checkout either.
 */
final class DeployVersionExtension extends AbstractExtension
{
    private ?string $version = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('deploy_version', $this->getVersion(...)),
        ];
    }

    public function getVersion(): string
    {
        if (null !== $this->version) {
            return $this->version;
        }

        $heads = [$this->readGitHead($this->projectDir)];

        foreach (glob($this->projectDir . '/vendor/glitchr/*', GLOB_ONLYDIR) ?: [] as $packageDir) {
            $heads[] = $this->readGitHead($packageDir);
        }

        $heads = array_filter($heads, static fn (?string $head): bool => null !== $head);
        if ([] === $heads) {
            return $this->version = 'dev';
        }

        return $this->version = substr(md5(implode('|', $heads)), 0, 8);
    }

    private function readGitHead(string $dir): ?string
    {
        $headFile = $dir . '/.git/HEAD';
        if (!is_file($headFile)) {
            return null;
        }

        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $refFile = $dir . '/.git/' . substr($head, 5);
            $head = is_file($refFile) ? trim((string) file_get_contents($refFile)) : $head;
        }

        return '' !== $head ? $head : null;
    }
}
