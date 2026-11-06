<?php

namespace Base\Service;

use Base\Entity\Layout\Redirection;
use Base\Repository\Layout\Redirections as RedirectionRows;
use Base\Request\Rule\Redirection as RedirectionRule;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Where an address that is not found leads now (Base\Entity\Layout\Redirection).
 *
 *     $redirections->responseFor($request);   // a RedirectResponse, or null: really not found
 *     $redirections->add('/produit/enseigne-lumineuse/', '/savoir-faire/enseigne');
 */
class Redirections
{
    public function __construct(
        protected readonly RedirectionRows $repository,
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function responseFor(Request $request): ?RedirectResponse
    {
        // The rule is the core's (Base\Request\Rule\Redirection, plan "omnibase agnostic", step F5e, slice 2): every host asks it.
        $decision = (new RedirectionRule($this->repository, $this->logger))->decide($request->isMethodSafe(), $request->getPathInfo(), $request->getQueryString(), $request->getBaseUrl());

        return null === $decision->url ? null : new RedirectResponse($decision->url, $decision->status);
    }

    /** Adds a redirection, or changes the one already written for that source. */
    public function add(string $source, string $target, int $status = Redirection::PERMANENT, bool $flush = true): Redirection
    {
        $source = Redirection::normalize($source);
        $redirection = $this->repository->findOneBy(['source' => $source]) ?? new Redirection($source, $target, $status);
        $redirection->setTarget($target)->setStatus($status);

        $this->repository->persist($redirection, $flush);

        return $redirection;
    }
}
