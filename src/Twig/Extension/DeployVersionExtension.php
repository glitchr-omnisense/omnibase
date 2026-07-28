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

        $headFile = $this->projectDir . '/.git/HEAD';
        if (!is_file($headFile)) {
            return $this->version = 'dev';
        }

        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $refFile = $this->projectDir . '/.git/' . substr($head, 5);
            $head = is_file($refFile) ? trim((string) file_get_contents($refFile)) : $head;
        }

        return $this->version = ('' !== $head ? substr($head, 0, 8) : 'dev');
    }
}
