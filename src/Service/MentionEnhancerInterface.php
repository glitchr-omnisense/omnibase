<?php

namespace Base\Service;

use Base\Repository\Thread\MentionRepository;

/**
 *
 */
interface MentionEnhancerInterface
{
    public function getRepository(): MentionRepository;
    public function extractMentionees(string|array|null $strOrArray, array $attributes = []): array;
    public function highlight(string|array|null $strOrArray, array $attributes = []): string|array|null;
}
