<?php

namespace Base\Request\Rule;

use Base\Entity\Layout\Redirection as Row;
use Base\Repository\Layout\Redirections;
use Base\Request\Decision;
use Psr\Log\LoggerInterface;

/**
 * A page not found, looked up in the site's redirections (plan "omnibase agnostic", step F5e, slice 2;
 * formerly Base\Service\Redirections::responseFor(), which asks it now): the old addresses of a site taken
 * over lead to its new pages, with the redirection's status (301 or 302), its use counted.
 *
 * Asked only once nothing answered (no route, or no record): a page the site answers is never redirected.
 * A form sent to an old address is not replayed elsewhere (a safe method only); matched on its path alone,
 * the visitor's query follows (a campaign's utm_*); a redirection onto itself is none; no table yet (a site
 * that has not run its migration) is no redirection.
 */
final class Redirection
{
    public function __construct(private readonly Redirections $repository, private readonly ?LoggerInterface $logger = null)
    {
    }

    public function decide(bool $safe, string $path, ?string $query = null, string $base = ''): Decision
    {
        if (!$safe) {
            return Decision::continue();
        }
        $address = Row::normalize($path.(null !== $query ? '?'.$query : ''));

        try {
            $redirection = $this->repository->forAddress($address);
        } catch (\Throwable $e) {
            $this->logger?->debug('Redirections unavailable: '.$e->getMessage());

            return Decision::continue();
        }

        $target = $redirection?->resolve($address) ?? $redirection?->resolve(strtok($address, '?'));
        if (null === $redirection || null === $target) {
            return Decision::continue();
        }
        if (Row::normalize($target) === $address && !preg_match('#^https?://#i', $target)) {
            return Decision::continue();
        }
        if (null !== $query && !str_contains($redirection->getSource(), '?') && !str_contains($target, '?')) {
            $target .= '?'.$query;
        }
        if (!preg_match('#^https?://#i', $target)) {
            $target = $base.$target;
        }

        try {
            $this->repository->hit($redirection);
        } catch (\Throwable $e) {
            $this->logger?->debug('Redirection not counted: '.$e->getMessage());
        }

        return Decision::redirectTo($target, $redirection->getStatus());
    }
}
