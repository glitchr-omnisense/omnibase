<?php

namespace Base\Thread;

use Base\Database\Repository\Repositories;
use Base\Enum\ThreadState;
use Base\Enum\UserRole;
use Base\Repository\Threads;
use Base\Repository\ThreadTranslations;
use Omnibridge\Contract\Access;

/**
 * The threads' search, written once for every host (plan "omnibase agnostic", step F5e, slice 5b; formerly
 * ThreadSearchController::Main()): the generic term stands for the title, the excerpt and the content where they
 * are not given apart; the published threads only, every state for an administrator; under the parent asked; the
 * threads of the class highest in the hierarchy first. The model keeps the raw terms: the page highlights them.
 */
final class ThreadSearch
{
    public const TEMPLATE = 'client/thread/search.html.twig';

    public function __construct(
        private readonly Access $access,
        private readonly ThreadTranslations $translations,
        private readonly Repositories $repositories,
    ) {
    }

    /**
     * The model completed (a copy) and the threads found; no model, nothing looked for.
     *
     * @return array{0: ?object, 1: list<object>}
     */
    public function find(?object $model): array
    {
        if (null === $model) {
            return [null, []];
        }
        $data = clone $model;
        $data->content = $data->content ?? $data->generic ?? '';
        $data->title = $data->title ?? $data->generic ?? '';
        $data->excerpt = $data->excerpt ?? $data->generic ?? '';

        // Translations whose title, excerpt OR content matches, scoped by state and parent: ThreadTranslations::
        // search() (step R28) - "(a OR b OR c) AND scope", which the finder language cannot say.
        $states = $this->access->isGranted(UserRole::ADMIN) ? [] : [ThreadState::PUBLISH];
        $found = $this->translations->search(['title' => $data->title, 'excerpt' => $data->excerpt, 'content' => $data->content], $states, $data->parent_id ?: null);
        $threads = array_map(static fn ($translation) => $translation->getTranslatable(), $found);
        usort($threads, function ($a, $b) {
            $aRepository = $this->repositories->of($a::class);
            $bRepository = $this->repositories->of($b::class);
            \assert($aRepository instanceof Threads && $bRepository instanceof Threads);

            return $aRepository->getHierarchy() < $bRepository->getHierarchy() ? -1 : 1;
        });

        return [$data, array_values($threads)];
    }
}
