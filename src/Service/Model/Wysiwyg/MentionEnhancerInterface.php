<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Repository\Thread\Mentions;

interface MentionEnhancerInterface
{
    /** The mentions, by their interface (step R28). */
    public function mentions(): Mentions;

    /** @deprecated since omnibase 4.0 (step R28): mentions() */
    public function getRepository(): Mentions;

    public function extractMentionees(string|array|null $strOrArray, array $attributes = []): array;

    public function enhance(string|array|null $strOrArray, array $attributes = []): string|array|null;
}
