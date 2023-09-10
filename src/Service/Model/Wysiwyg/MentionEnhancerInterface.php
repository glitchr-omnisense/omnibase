<?php

namespace Base\Service\Model\Wysiwyg;
use Base\Repository\Thread\MentionRepository;

/**
 *
 */
interface MentionEnhancerInterface
{
    public function getRepository(): MentionRepository;
    public function extractMentionees(string|array|null $strOrArray, array $attributes = []): array;
    public function enhance(string|array|null $strOrArray, array $attributes = []): string|array|null;
}
